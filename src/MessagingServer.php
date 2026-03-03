<?php

namespace Neuralpin\Notifyli;

use Socket;
use resource;

class MessagingServer
{
    protected bool|resource|Socket $socket;
    protected array $clients;
    protected array $rooms;
    protected array $roomsPerSID;
    protected string $host;
    protected int $port;
    protected string $location;
    protected $null;

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
        socket_close($this->socket);
    }

    /**
     * Creates and returns a Socket instance
     * @return bool|Socket
     */
    public function socketCreate(): bool|Socket
    {
        //Create TCP/IP stream socket
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        //reuseable port
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

        //bind socket to specified host
        socket_bind($socket, '0.0.0.0', $this->port);

        //listen to port
        socket_listen($socket);

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

            //check for any incoming data
            while (socket_recv($changedSocket, $data, 5242880, 0) >= 1) {
                $message = json_decode($this->unmask($data), true);
                // Group clients per room / disconnect if no room
                if (!isset($message['room'])) {
                    socket_close($this->clients[$sid]);
                    unset($this->clients[$sid]);
                    break 2;
                }
                if (!isset($this->rooms[$message['room']][$sid])) {
                    $this->rooms[$message['room']][$sid] = $sid;
                    $this->roomsPerSID[$sid][] = $message['room'];
                }

                $this->sendRoomMessage($message);
                break 2;
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
        $length = ord($text[1]) & 127;
        if ($length == 126) {
            $masks = substr($text, 4, 4);
            $data = substr($text, 8);
        } elseif ($length == 127) {
            $masks = substr($text, 10, 4);
            $data = substr($text, 14);
        } else {
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
        $header = socket_read($clientConnection, 5242880);

        $headers = [];
        $lines = preg_split("/\r\n/", $header);
        foreach ($lines as $line) {
            $line = chop($line);
            if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)){
                $headers[$matches[1]] = $matches[2];
            }
        }

        $secKey = $headers['Sec-WebSocket-Key'] ?? 1;
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

            $total = count($this->rooms[$data['room']]);
            $date = date('Y-m-d H:i:s');
            echo "{$date}: Escribiendo en el room {$data['room']}, con {$total} participantes\n";

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

    protected function forgetInactiveClients(Socket $changedSocket, $sid)
    {
        try {
            $data = socket_read($changedSocket, 5242880, PHP_NORMAL_READ);
        } catch (\Exception | \Throwable $e) {
            $data = false;
        }

        $date = date('Y-m-d H:i:s');
        echo "{$date} : $data\r\n";

        // check for disconnected clients
        if ($data === false) {
            echo 'Desconectando cliente inactivo\r\n';
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
