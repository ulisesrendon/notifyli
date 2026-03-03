<?php

use Neuralpin\Notifyli\Clients\ReusableWebSocketClient;

require __DIR__.'/config/app.php';

$scheme = $_ENV['APP_WS_SERVER_PROTOCOL'] ?? 'ws';
$host = $_ENV['APP_WS_SERVER_DOMAIN'] ?? 'localhost';
$port = (int) ($_ENV['APP_PORT'] ?? 7000);
$room = '1';
$botName = $_ENV['BOT_NAME'] ?? 'NotifyliBot';
$keepaliveSeconds = 25;
$reconnectWaitSeconds = 3;

while (true) {
    $client = new ReusableWebSocketClient(
        host: $host,
        port: $port,
        path: '/',
        scheme: $scheme,
    );

    try {
        $client->connect();
        echo '[' . date('Y-m-d H:i:s') . "] Connected to {$scheme}://{$host}:{$port}\n";

        $client->sendJson([
            'message' => '...',
            'name' => $botName,
            'room' => $room,
            'type' => 'keepalive',
        ]);

        $lastKeepaliveAt = time();

        while ($client->isConnected()) {
            if ((time() - $lastKeepaliveAt) >= $keepaliveSeconds) {
                $client->sendJson([
                    'message' => '...',
                    'name' => $botName,
                    'room' => $room,
                    'type' => 'keepalive',
                ]);
                $lastKeepaliveAt = time();
            }

            $incoming = $client->receiveJson(1);
            if ($incoming === null) {
                continue;
            }

            $sender = (string) ($incoming['name'] ?? '');
            $message = (string) ($incoming['message'] ?? '');

            if ($sender === $botName) {
                continue;
            }

            if (stripos($message, 'hello') !== false) {
                $replyTo = $sender !== '' ? $sender : 'there';
                $client->sendJson([
                    'message' => "Hello {$replyTo}! 👋",
                    'name' => $botName,
                    'room' => $room,
                    'type' => 'usermsg',
                ]);

                echo '[' . date('Y-m-d H:i:s') . "] Replied to message containing 'hello'\n";
            }
        }
    } catch (\Throwable $e) {
        echo '[' . date('Y-m-d H:i:s') . '] Bot error: ' . $e->getMessage() . "\n";
    } finally {
        $client->disconnect();
    }

    sleep($reconnectWaitSeconds);
}
