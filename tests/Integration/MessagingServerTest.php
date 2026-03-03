<?php

use Neuralpin\Notifyli\MessagingServer;

describe('MessagingServer Integration', function () {
    it('completes a valid websocket handshake', function () {
        $port = random_int(20000, 35000);
        $server = new MessagingServer(
            host: 'localhost',
            location: 'ws://localhost/websocket',
            port: $port,
        );

        $client = stream_socket_client("tcp://127.0.0.1:$port", $errno, $errstr, 1);
        expect($client)->not->toBeFalse();

        $request = "GET /websocket HTTP/1.1\r\n".
            "Host: localhost\r\n".
            "Upgrade: websocket\r\n".
            "Connection: Upgrade\r\n".
            "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\n".
            "Sec-WebSocket-Version: 13\r\n\r\n";

        fwrite($client, $request);
        stream_set_timeout($client, 1);

        $server->process();

        $response = fread($client, 4096);

        expect($response)->toContain('HTTP/1.1 101 Web Socket Protocol Handshake');
        expect($response)->toContain('Upgrade: websocket');
        expect($response)->toContain('Connection: Upgrade');
        expect($response)->toContain('Sec-WebSocket-Accept:s3pPLMBiTxaQ9kYGzzhZRbK+xOo=');

        fclose($client);
        $server->close();
    });

    it('rejects handshake when Sec-WebSocket-Key is missing', function () {
        $port = random_int(35001, 45000);
        $server = new MessagingServer(
            host: 'localhost',
            location: 'ws://localhost/websocket',
            port: $port,
        );

        $client = stream_socket_client("tcp://127.0.0.1:$port", $errno, $errstr, 1);
        expect($client)->not->toBeFalse();

        $request = "GET /websocket HTTP/1.1\r\n".
            "Host: localhost\r\n".
            "Upgrade: websocket\r\n".
            "Connection: Upgrade\r\n".
            "Sec-WebSocket-Version: 13\r\n\r\n";

        fwrite($client, $request);
        stream_set_timeout($client, 1);

        $server->process();

        $response = fread($client, 4096);

        expect($response)->toContain('HTTP/1.1 400 Bad Request');

        fclose($client);
        $server->close();
    });
});
