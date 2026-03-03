<?php

namespace Neuralpin\Notifyli\Services;

class WebSocketFrameHandler
{
    /**
     * Decode WebSocket frame (unmask)
     */
    public function decode(string $text): string
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
     * Encode WebSocket frame (mask)
     */
    public function encode(string $text): string
    {
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($text);

        if ($length <= 125) {
            $header = pack('CC', $b1, $length);
        } else if ($length > 125 && $length < 65536) {
            $header = pack('CCn', $b1, 126, $length);
        } else if ($length >= 65536) {
                $header = pack('CCNN', $b1, 127, 0, $length);
        }

        return $header . $text;
    }

    /**
     * Create WebSocket handshake response
     */
    public function createHandshakeResponse(string $secKey, string $host, string $location): string
    {
        $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

        return "HTTP/1.1 101 Web Socket Protocol Handshake\r\n".
            "Upgrade: websocket\r\n".
            "Connection: Upgrade\r\n".
            "WebSocket-Origin: $host\r\n".
            "WebSocket-Location: $location\r\n".
            "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
    }

    /**
     * Parse headers from handshake request
     */
    public function parseHeaders(string $header): array
    {
        $headers = [];
        $lines = preg_split("/\r\n/", $header);

        foreach ($lines as $line) {
            $line = chop($line);
            if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)){
                $headers[$matches[1]] = $matches[2];
            }
        }

        return $headers;
    }
}
