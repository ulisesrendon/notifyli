<?php

namespace Neuralpin\Notifyli\Clients;

class ReusableWebSocketClient
{
    private string $host;
    private int $port;
    private string $path;
    private string $scheme;
    private int $connectTimeout;

    /** @var resource|null */
    private $stream = null;

    public function __construct(
        string $host,
        int $port,
        string $path = '/',
        string $scheme = 'ws',
        int $connectTimeout = 10,
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->path = $path;
        $this->scheme = strtolower($scheme);
        $this->connectTimeout = $connectTimeout;
    }

    public function connect(): void
    {
        if ($this->isConnected()) {
            return;
        }

        $transport = $this->scheme === 'wss' ? 'tls' : 'tcp';
        $uri = sprintf('%s://%s:%d', $transport, $this->host, $this->port);

        $stream = @stream_socket_client($uri, $errorCode, $errorMessage, $this->connectTimeout);
        if (!is_resource($stream)) {
            throw new \RuntimeException(sprintf(
                'WebSocket connection failed (%s): %s',
                (string) $errorCode,
                $errorMessage
            ));
        }

        stream_set_blocking($stream, true);
        stream_set_timeout($stream, $this->connectTimeout);

        $this->performHandshake($stream);
        $this->stream = $stream;
    }

    public function disconnect(): void
    {
        if (!is_resource($this->stream)) {
            $this->stream = null;
            return;
        }

        @fwrite($this->stream, $this->encodeFrame('', 0x8));
        @fclose($this->stream);
        $this->stream = null;
    }

    public function isConnected(): bool
    {
        return is_resource($this->stream) && !feof($this->stream);
    }

    public function send(string $message): void
    {
        $this->ensureConnected();

        $frame = $this->encodeFrame($message, 0x1);
        $written = @fwrite($this->stream, $frame);

        if ($written === false) {
            throw new \RuntimeException('Unable to send WebSocket frame.');
        }
    }

    public function sendJson(array $payload): void
    {
        $json = json_encode($payload);
        if ($json === false) {
            throw new \RuntimeException('Unable to JSON encode payload.');
        }

        $this->send($json);
    }

    public function receiveText(int $timeoutSeconds = 1): ?string
    {
        $this->ensureConnected();

        stream_set_timeout($this->stream, $timeoutSeconds);

        while (true) {
            $read = [$this->stream];
            $changed = @stream_select($read, $write, $except, $timeoutSeconds);

            if ($changed === false) {
                throw new \RuntimeException('Failed waiting for socket data.');
            }

            if ($changed === 0) {
                return null;
            }

            $frame = $this->readFrame();
            if ($frame === null) {
                return null;
            }

            $opcode = $frame['opcode'];
            $payload = $frame['payload'];

            if ($opcode === 0x8) {
                $this->disconnect();
                return null;
            }

            if ($opcode === 0x9) {
                $pongFrame = $this->encodeFrame($payload, 0xA);
                @fwrite($this->stream, $pongFrame);
                continue;
            }

            if ($opcode === 0xA) {
                continue;
            }

            if ($opcode === 0x1) {
                return $payload;
            }
        }
    }

    public function receiveJson(int $timeoutSeconds = 1): ?array
    {
        $text = $this->receiveText($timeoutSeconds);
        if ($text === null) {
            return null;
        }

        $data = json_decode($text, true);
        return is_array($data) ? $data : null;
    }

    private function ensureConnected(): void
    {
        if (!$this->isConnected()) {
            throw new \RuntimeException('WebSocket is not connected.');
        }
    }

    /**
     * @param resource $stream
     */
    private function performHandshake($stream): void
    {
        $key = base64_encode(random_bytes(16));

        $request = "GET {$this->path} HTTP/1.1\r\n" .
            "Host: {$this->host}:{$this->port}\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Key: {$key}\r\n" .
            "Sec-WebSocket-Version: 13\r\n\r\n";

        $written = @fwrite($stream, $request);
        if ($written === false) {
            throw new \RuntimeException('Failed to send WebSocket handshake request.');
        }

        $response = '';
        while (!str_contains($response, "\r\n\r\n")) {
            $chunk = fgets($stream, 2048);
            if ($chunk === false) {
                break;
            }
            $response .= $chunk;
        }

        if (!preg_match('#^HTTP/1\\.1 101#', $response)) {
            throw new \RuntimeException('WebSocket handshake rejected by server: ' . trim($response));
        }

        if (!preg_match('/Sec-WebSocket-Accept:\\s*(.*)\\r\\n/i', $response, $matches)) {
            throw new \RuntimeException('Missing Sec-WebSocket-Accept header in handshake response.');
        }

        $expectedAccept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        if (trim($matches[1]) !== $expectedAccept) {
            throw new \RuntimeException('Invalid Sec-WebSocket-Accept value from server.');
        }
    }

    private function encodeFrame(string $payload, int $opcode = 0x1): string
    {
        $finAndOpcode = 0x80 | ($opcode & 0x0f);
        $length = strlen($payload);
        $maskBit = 0x80;

        if ($length <= 125) {
            $header = pack('CC', $finAndOpcode, $maskBit | $length);
        } elseif ($length <= 65535) {
            $header = pack('CCn', $finAndOpcode, $maskBit | 126, $length);
        } else {
            $header = pack('CCNN', $finAndOpcode, $maskBit | 127, 0, $length);
        }

        $maskKey = random_bytes(4);
        $maskedPayload = '';

        for ($index = 0; $index < $length; $index++) {
            $maskedPayload .= $payload[$index] ^ $maskKey[$index % 4];
        }

        return $header . $maskKey . $maskedPayload;
    }

    private function readFrame(): ?array
    {
        $header = $this->readBytes(2);
        if ($header === null) {
            return null;
        }

        $firstByte = ord($header[0]);
        $secondByte = ord($header[1]);

        $opcode = $firstByte & 0x0f;
        $masked = ($secondByte & 0x80) === 0x80;
        $payloadLength = $secondByte & 0x7f;

        if ($payloadLength === 126) {
            $extended = $this->readBytes(2);
            if ($extended === null) {
                return null;
            }
            $payloadLength = unpack('n', $extended)[1];
        } elseif ($payloadLength === 127) {
            $extended = $this->readBytes(8);
            if ($extended === null) {
                return null;
            }
            $parts = unpack('Nhigh/Nlow', $extended);
            $payloadLength = ($parts['high'] << 32) + $parts['low'];
        }

        $maskKey = $masked ? $this->readBytes(4) : null;
        if ($masked && $maskKey === null) {
            return null;
        }

        $payload = $payloadLength > 0 ? $this->readBytes($payloadLength) : '';
        if ($payload === null) {
            return null;
        }

        if ($masked && $maskKey !== null) {
            $unmasked = '';
            for ($index = 0; $index < $payloadLength; $index++) {
                $unmasked .= $payload[$index] ^ $maskKey[$index % 4];
            }
            $payload = $unmasked;
        }

        return [
            'opcode' => $opcode,
            'payload' => $payload,
        ];
    }

    private function readBytes(int $length): ?string
    {
        $buffer = '';

        while (strlen($buffer) < $length) {
            $chunk = fread($this->stream, $length - strlen($buffer));

            if ($chunk === false || $chunk === '') {
                if (feof($this->stream)) {
                    return null;
                }

                $meta = stream_get_meta_data($this->stream);
                if (!empty($meta['timed_out'])) {
                    return null;
                }

                continue;
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }
}
