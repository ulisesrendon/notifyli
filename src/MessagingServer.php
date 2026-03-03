<?php

namespace Neuralpin\Notifyli;

use Neuralpin\Notifyli\Adapters\SocketAdapter;
use Neuralpin\Notifyli\Contracts\SocketInterface;
use Neuralpin\Notifyli\Services\RoomManager;
use Neuralpin\Notifyli\Services\WebSocketFrameHandler;
use Socket;

class MessagingServer
{
    private const BUFFER_SIZE = 5242880; // 5MB

    protected Socket $socket;
    protected array $clients;
    protected string $host;
    protected int $port;
    protected string $location;
    protected ?array $null;
    protected SocketInterface $socketAdapter;
    protected RoomManager $roomManager;
    protected WebSocketFrameHandler $frameHandler;
    protected bool $serverSocketClosed = false;

    public function __construct(
        string $host = 'localhost',
        string $location = 'localhost',
        int $port = 7000,
        ?SocketInterface $socketAdapter = null,
        ?RoomManager $roomManager = null,
        ?WebSocketFrameHandler $frameHandler = null,
    )
    {
        $this->host = $host;
        $this->port = $port;
        $this->location = $location;
        $this->null = null;
        $this->clients = [];

        // Dependency injection with defaults
        $this->socketAdapter = $socketAdapter ?? new SocketAdapter();
        $this->roomManager = $roomManager ?? new RoomManager();
        $this->frameHandler = $frameHandler ?? new WebSocketFrameHandler();

        /* Allow the script to hang around waiting for connections. */
        set_time_limit(0);

        /* Turn on implicit output flushing so we see what comes in. */
        ob_implicit_flush();

        //create & add listening socket to the list
        $this->socket = $this->createSocket();
        $this->clients[] = $this->socket;
    }

    public function close(): void
    {
        if ($this->serverSocketClosed) {
            return;
        }

        if ($this->socket instanceof Socket) {
            try {
                $this->socketAdapter->close($this->socket);
            } catch (\Throwable $e) {
                // already closed
            }
        }

        $this->serverSocketClosed = true;
    }

    /**
     * Creates and returns a Socket instance
     */
    protected function createSocket(): Socket
    {
        //Create TCP/IP stream socket
        $socket = $this->socketAdapter->create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            throw new \RuntimeException('Failed to create socket: ' . $this->socketAdapter->strError($this->socketAdapter->lastError()));
        }

        //reuseable port
        if (!$this->socketAdapter->setOption($socket, SOL_SOCKET, SO_REUSEADDR, 1)) {
            throw new \RuntimeException('Failed to set socket option: ' . $this->socketAdapter->strError($this->socketAdapter->lastError($socket)));
        }

        //bind socket to specified host
        if (!$this->socketAdapter->bind($socket, '0.0.0.0', $this->port)) {
            throw new \RuntimeException('Failed to bind socket to port ' . $this->port . ': ' . $this->socketAdapter->strError($this->socketAdapter->lastError($socket)));
        }

        //listen to port
        if (!$this->socketAdapter->listen($socket)) {
            throw new \RuntimeException('Failed to listen on socket: ' . $this->socketAdapter->strError($this->socketAdapter->lastError($socket)));
        }

        return $socket;
    }

    public function process()
    {
        //manage multiple connections
        $changed = $this->clients;

        //returns the socket resources in $changed array
        $this->socketAdapter->select($changed, $this->null, $this->null, 0, 10);

        //check for new socket
        if (in_array($this->socket, $changed)) {
            $socket_new = $this->socketAdapter->accept($this->socket); //accept new socket
            $this->clients[] = $socket_new; //add socket to client array

            $this->performHandshake($socket_new); //perform websocket handshake

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

            // Read incoming data (or detect disconnection)
            $bytesReceived = $this->socketAdapter->recv($changedSocket, $data, self::BUFFER_SIZE, 0);

            // 0/false indicates disconnected or read error
            if ($bytesReceived === false || $bytesReceived === 0) {
                $this->closeClient($sid);
                continue;
            }

            $decodedData = $this->frameHandler->decode($data);
            $message = json_decode($decodedData, true);

            // Validate JSON decode was successful
            if (!is_array($message)) {
                $this->closeClient($sid);
                continue;
            }

            // Group clients per room / disconnect if no room
            if (!isset($message['room'])) {
                $this->closeClient($sid);
                continue;
            }

            $this->roomManager->addClientToRoom((string) $message['room'], $sid);
            $this->sendRoomMessage($message);
        }
    }

    /**
     * Perform WebSocket handshake with new client
     */
    protected function performHandshake(Socket $clientConnection): bool
    {
        $header = $this->socketAdapter->read($clientConnection, self::BUFFER_SIZE);

        $headers = $this->frameHandler->parseHeaders($header);

        // Validate WebSocket key is present
        if (!isset($headers['Sec-WebSocket-Key'])) {
            $errorMessage = "HTTP/1.1 400 Bad Request\r\n\r\n";
            $this->socketAdapter->write($clientConnection, $errorMessage, strlen($errorMessage));
            $this->socketAdapter->close($clientConnection);
            return false;
        }

        $secKey = $headers['Sec-WebSocket-Key'];
        $message = $this->frameHandler->createHandshakeResponse($secKey, $this->host, $this->location);

        $result = $this->socketAdapter->write($clientConnection, $message, strlen($message));
        return $result !== false;
    }

    /**
     * Send message to all clients in a room
     */
    protected function sendRoomMessage(array $data): bool
    {
        $data['type'] ??= '';
        if ($data['type'] != 'keepalive') {

            // Check if room exists
            if (!$this->roomManager->roomExists($data['room'])) {
                return false;
            }

            $total = $this->roomManager->getRoomClientCount($data['room']);
            $date = date('Y-m-d H:i:s');
            echo "{$date}: Writing in room {$data['room']}, with {$total} participants\n";

            $message = $this->frameHandler->encode(json_encode($data));
            foreach ($this->roomManager->getRoomClients($data['room']) as $sid) {
                if (isset($this->clients[$sid])){
                    try{
                        $this->socketAdapter->write($this->clients[$sid], $message, strlen($message));
                    }catch(\Exception|\Throwable $e){
                        var_dump($e->getMessage());
                    }
                }
            }
        }

        return true;
    }

    /**
     * Close a client connection and clean up
     */
    protected function closeClient(int $sid): void
    {
        if (!isset($this->clients[$sid])) {
            return;
        }

        // Remove client from rooms
        $this->roomManager->removeClient($sid);

        // Close socket
        try {
            $this->socketAdapter->close($this->clients[$sid]);
        } catch (\Exception | \Throwable $e) {
            // Socket already closed or error
        }

        // Remove from clients array
        unset($this->clients[$sid]);
    }

    /**
     * Get room manager for testing
     */
    public function getRoomManager(): RoomManager
    {
        return $this->roomManager;
    }

    /**
     * Get frame handler for testing
     */
    public function getFrameHandler(): WebSocketFrameHandler
    {
        return $this->frameHandler;
    }

    /**
     * Get clients array for testing
     */
    public function getClients(): array
    {
        return $this->clients;
    }

    public function __destruct()
    {
        $this->close();
    }
}
