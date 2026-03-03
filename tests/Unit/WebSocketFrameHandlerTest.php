<?php

use Neuralpin\Notifyli\Services\WebSocketFrameHandler;

describe('WebSocketFrameHandler', function () {
    beforeEach(function () {
        $this->handler = new WebSocketFrameHandler();
    });

    describe('decode', function () {
        it('returns empty string for data shorter than 2 bytes', function () {
            $result = $this->handler->decode('a');
            expect($result)->toBe('');
        });

        it('returns empty string for invalid short frame', function () {
            $result = $this->handler->decode('ab');
            expect($result)->toBe('');
        });

        it('decodes a simple text frame correctly', function () {
            // Create a masked WebSocket frame with "Hello"
            $text = 'Hello';
            $mask = "\x12\x34\x56\x78";
            $masked = '';
            for ($i = 0; $i < strlen($text); $i++) {
                $masked .= $text[$i] ^ $mask[$i % 4];
            }

            // Frame: FIN + text opcode, mask bit + length, mask key, masked data
            $frame = "\x81" . chr(0x80 | strlen($text)) . $mask . $masked;

            $result = $this->handler->decode($frame);
            expect($result)->toBe('Hello');
        });

        it('handles medium length frames (126)', function () {
            // Data length exactly 126 should trigger medium frame format
            $text = str_repeat('A', 126);
            $mask = "\x12\x34\x56\x78";
            $masked = '';
            for ($i = 0; $i < strlen($text); $i++) {
                $masked .= $text[$i] ^ $mask[$i % 4];
            }

            // Frame with 16-bit length
            $frame = "\x81\xFE" . pack('n', 126) . $mask . $masked;

            $result = $this->handler->decode($frame);
            expect($result)->toBe($text);
        });
    });

    describe('encode', function () {
        it('encodes short text correctly', function () {
            $text = 'Hello';
            $result = $this->handler->encode($text);

            // Check frame structure
            expect(strlen($result))->toBe(strlen($text) + 2); // header + text
            expect(ord($result[0]))->toBe(0x81); // FIN + text opcode
            expect(ord($result[1]))->toBe(strlen($text)); // length
        });

        it('encodes medium length text (126-65535 bytes)', function () {
            $text = str_repeat('A', 200);
            $result = $this->handler->encode($text);

            // Check frame structure for medium length
            expect(ord($result[0]))->toBe(0x81); // FIN + text opcode
            expect(ord($result[1]))->toBe(126); // medium length indicator
            // Next 2 bytes should contain the length
            expect(strlen($result))->toBe(strlen($text) + 4); // header + text
        });

        it('encodes large text (>=65536 bytes)', function () {
            $text = str_repeat('A', 70000);
            $result = $this->handler->encode($text);

            // Check frame structure for large length
            expect(ord($result[0]))->toBe(0x81); // FIN + text opcode
            expect(ord($result[1]))->toBe(127); // large length indicator
            expect(strlen($result))->toBe(strlen($text) + 10); // header + text
        });

        it('can encode and decode round trip', function () {
            $original = 'Hello, WebSocket World!';
            $encoded = $this->handler->encode($original);

            // To decode, we need to add masking
            $mask = "\x00\x00\x00\x00";
            $masked = '';
            for ($i = 0; $i < strlen($original); $i++) {
                $masked .= $original[$i] ^ $mask[$i % 4];
            }
            $frameWithMask = "\x81" . chr(0x80 | strlen($original)) . $mask . $masked;

            $decoded = $this->handler->decode($frameWithMask);
            expect($decoded)->toBe($original);
        });
    });

    describe('createHandshakeResponse', function () {
        it('creates valid WebSocket handshake response', function () {
            $secKey = 'dGhlIHNhbXBsZSBub25jZQ==';
            $host = 'example.com';
            $location = 'ws://example.com/socket';

            $response = $this->handler->createHandshakeResponse($secKey, $host, $location);

            expect($response)->toContain('HTTP/1.1 101 Web Socket Protocol Handshake');
            expect($response)->toContain('Upgrade: websocket');
            expect($response)->toContain('Connection: Upgrade');
            expect($response)->toContain("WebSocket-Origin: $host");
            expect($response)->toContain("WebSocket-Location: $location");
            expect($response)->toContain('Sec-WebSocket-Accept:');
        });

        it('generates correct Sec-WebSocket-Accept value', function () {
            $secKey = 'dGhlIHNhbXBsZSBub25jZQ==';
            $expectedAccept = 's3pPLMBiTxaQ9kYGzzhZRbK+xOo='; // Standard test value

            $response = $this->handler->createHandshakeResponse($secKey, 'host', 'location');

            expect($response)->toContain("Sec-WebSocket-Accept:$expectedAccept");
        });
    });

    describe('parseHeaders', function () {
        it('parses HTTP headers correctly', function () {
            $headerText = "GET /chat HTTP/1.1\r\n" .
                         "Host: example.com\r\n" .
                         "Upgrade: websocket\r\n" .
                         "Connection: Upgrade\r\n" .
                         "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\n" .
                         "Sec-WebSocket-Version: 13\r\n\r\n";

            $headers = $this->handler->parseHeaders($headerText);

            expect($headers)->toHaveKey('Host');
            expect($headers['Host'])->toBe('example.com');
            expect($headers)->toHaveKey('Upgrade');
            expect($headers['Upgrade'])->toBe('websocket');
            expect($headers)->toHaveKey('Sec-WebSocket-Key');
            expect($headers['Sec-WebSocket-Key'])->toBe('dGhlIHNhbXBsZSBub25jZQ==');
        });

        it('handles empty headers', function () {
            $headers = $this->handler->parseHeaders('');
            expect($headers)->toBe([]);
        });

        it('handles malformed headers gracefully', function () {
            $headerText = "GET /chat HTTP/1.1\r\n" .
                         "InvalidHeaderWithoutColon\r\n" .
                         "Valid-Header: value\r\n\r\n";

            $headers = $this->handler->parseHeaders($headerText);

            expect($headers)->toHaveKey('Valid-Header');
            expect($headers['Valid-Header'])->toBe('value');
            expect($headers)->not->toHaveKey('InvalidHeaderWithoutColon');
        });
    });
});
