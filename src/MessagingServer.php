<?php

namespace Neuralpin\Notifyli;

use Socket;

class MessagingServer
{
    private const BUFFER_SIZE = 5242880; // 5MB

    protected Socket $socket;
    protected array $clients;
    protected array $rooms;
    protected array $roomsPerSID;
    protected string $host;
    protected int $port;
    protected string $location;
    protected ?array $null;

    public function __construct(
        string $host = 'localhost',
        string $location = 'localhost',
        int $port = 7000,
    )
    {
        $this->host = $host;
        $this->port = $port;
        $this->location = $location;
        $this->null = null;
        $this->clients = [];
        $this->rooms = [];
        $this->roomsPerSID = [];

        /* Allow the script to hang around waiting for connections. */
        set_time_limit(0);

        /* Turn on implicit output flushing so we see what comes in. */
        ob_implicit_flush();

        //create & add listening socket to the list
        $this->socket = $this->socketCreate();
        $this->clients[] = $this->socket;
    }

    public function close(): void
    {
        if ($this->socket instanceof Socket) {
            socket_close($this->socket);
        }
    }

    /**
     * Creates and returns a Socket instance
     * @return Socket
     */
    public function socketCreate(): Socket
    {
        //Create TCP/IP stream socket
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            throw new \RuntimeException('Failed to create socket: ' . socket_strerror(socket_last_error()));
        }

        //reuseable port
        if (!socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1)) {
            throw new \RuntimeException('Failed to set socket option: ' . socket_strerror(socket_last_error($socket)));
        }

        //bind socket to specified host
        if (!socket_bind($socket, '0.0.0.0', $this->port)) {
            throw new \RuntimeException('Failed to bind socket to port ' . $this->port . ': ' . socket_strerror(socket_last_error($socket)));
        }

        //listen to port
        if (!socket_listen($socket)) {
            throw new \RuntimeException('Failed to listen on socket: ' . socket_strerror(socket_last_error($socket)));
        }

        return $socket;
    }

    public function process()
    {
        //manage multiple connections
        $changed = $this->clients;

        //returns the socket resources in $changed array
        socket_select($changed, $this->null, $this->null, 0, 10);

        //check for new socket
        if (in_array($this->socket, $changed)) {
            $socket_new = socket_accept($this->socket); //acc ept new socket
            $this->clients[] = $socket_new; //add socket to client array

            $this->handshake($socket_new, $this->host, $this->location); //perform websocket handshake

            // socket_getpeername($socket_new, $ip); //get ip address of connected socket

            //make room for new socket
            unset($changed[array_search($this->socket, $changed)]);
        }

        //loop through all connected sockets
        foreach ($changed as $changedSocket) {

            $sid = array_search($changedSocket, $this->clients);

            // Skip if socket not found in clients array
            if ($sid === false) {
                continue;
            }

            //check for any incoming data
            while (socket_recv($changedSocket, $data, self::BUFFER_SIZE, 0) >= 1) {
                $message = json_decode($this->unmask($data), true);

                // Validate JSON decode was successful
                if (!is_array($message)) {
                    socket_close($this->clients[$sid]);
                    unset($this->clients[$sid]);
                    break;
                }

                // Group clients per room / disconnect if no room
                if (!isset($message['room'])) {
                    socket_close($this->clients[$sid]);
                    unset($this->clients[$sid]);
                    break;
                }
                if (!isset($this->rooms[$message['room']][$sid])) {
                    $this->rooms[$message['room']][$sid] = $sid;
                    $this->roomsPerSID[$sid][] = $message['room'];
                }

                $this->sendRoomMessage($message);
                break;
            }

            $this->forgetInactiveClients($changedSocket, $sid);
        }
    }

    /**
     * Message Decode
     * @param string $text
     * @return string
     */
    protected function unmask(string $text): string
    {
        // Validate minimum length
        if (strlen($text) < 2) {
            return '';
        }

        $length = ord($text[1]) & 127;
        if ($length == 126) {
            if (strlen($text) < 8) {
                return '';
            }
            $masks = substr($text, 4, 4);
            $data = substr($text, 8);
        } elseif ($length == 127) {
            if (strlen($text) < 14) {
                return '';
            }
            $masks = substr($text, 10, 4);
            $data = substr($text, 14);
        } else {
            if (strlen($text) < 6) {
                return '';
            }
            $masks = substr($text, 2, 4);
            $data = substr($text, 6);
        }
        $text = "";
        for ($i = 0; $i < strlen($data); ++$i) {
            $text .= $data[$i] ^ $masks[$i % 4];
        }
        return $text;
    }

    /**
     * Message Encode
     * @param string $text
     * @return string
     */
    protected function mask(string $text): string
    {
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($text);

        if ($length <= 125) {
            $header = pack('CC', $b1, $length);
        } else if ($length > 125 && $length < 65536) {
            $header = pack('CCn', $b1, 126, $length);
        } else if ($length >= 65536) {
            $header = pack('CCNN', $b1, 127, $length);
        }
        return $header . $text;
    }

    /**
     * Handshake new client
     * @param \Socket $clientConnection
     * @param string $host
     * @param string $location
     * @return bool|int
     */
    protected function handshake(Socket $clientConnection, string $host, string $location): bool|int
    {
        $header = socket_read($clientConnection, self::BUFFER_SIZE);

        $headers = [];
        $lines = preg_split("/\r\n/", $header);
        foreach ($lines as $line) {
            $line = chop($line);
            if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)){
                $headers[$matches[1]] = $matches[2];
            }
        }

        // Validate WebSocket key is present
        if (!isset($headers['Sec-WebSocket-Key'])) {
            $errorMessage = "HTTP/1.1 400 Bad Request\r\n\r\n";
            socket_write($clientConnection, $errorMessage, strlen($errorMessage));
            socket_close($clientConnection);
            return false;
        }

        $secKey = $headers['Sec-WebSocket-Key'];
        $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

        //handshaking header
        $message = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n".
            "Upgrade: websocket\r\n".
            "Connection: Upgrade\r\n".
            "WebSocket-Origin: $host\r\n".
            "WebSocket-Location: $location\r\n".
            "Sec-WebSocket-Accept:$secAccept\r\n\r\n";

        return socket_write($clientConnection, $message, strlen($message));
    }

    /**
     * Summary of sendRoomMessage
     * @param array $data
     * @return bool
     */
    protected function sendRoomMessage(array $data): bool
    {
        $data['type'] ??= '';
        if ($data['type'] != 'keepalive') {

            // Check if room exists
            if (!isset($this->rooms[$data['room']])) {
                return false;
            }

            $total = count($this->rooms[$data['room']]);
            $date = date('Y-m-d H:i:s');
            echo "{$date}: Writing in room {$data['room']}, with {$total} participants\n";

            $message = $this->mask(json_encode($data));
            foreach ($this->rooms[$data['room']] as $sid) {
                if (isset($this->clients[$sid])){
                    try{
                        socket_write($this->clients[$sid], $message, strlen($message));
                    }catch(\Exception|\Throwable $e){
                        var_dump($e->getMessage());
                    }
                }
            }
        }

        return true;
    }

    protected function forgetInactiveClients(Socket $changedSocket, int|false $sid): void
    {
        // Skip if invalid SID
        if ($sid === false) {
            return;
        }

        try {
            $data = socket_read($changedSocket, self::BUFFER_SIZE, PHP_NORMAL_READ);
        } catch (\Exception | \Throwable $e) {
            $data = false;
        }

        $date = date('Y-m-d H:i:s');
        echo "{$date} : $data\r\n";

        // check for disconnected clients
        if ($data === false) {
            echo 'Disconnecting inactive client\r\n';
            //socket_getpeername($changed_socket, $ip);
            // remove client from $rooms array
            if (isset($this->roomsPerSID[$sid])) {
                foreach ($this->roomsPerSID[$sid] as $room_id) {
                    unset($this->rooms[$room_id][$sid]);
                }
                unset($this->roomsPerSID[$sid]);
            }
            // remove client from $this->clients array
            unset($this->clients[$sid]);
            try{
                socket_close($changedSocket);
            } catch (\Exception | \Throwable $e) {

            }
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
