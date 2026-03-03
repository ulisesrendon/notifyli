<?php

use Neuralpin\Notifyli\Clients\ReusableWebSocketClient;
use Neuralpin\Notifyli\Services\RedisMessageBroker;
use Predis\Client as RedisClient;

require __DIR__.'/config/app.php';

$scheme = $_ENV['APP_WS_SERVER_PROTOCOL'] ?? 'ws';
$host = $_ENV['APP_WS_SERVER_DOMAIN'] ?? 'localhost';
$port = (int) ($_ENV['APP_PORT'] ?? 7000);
$room = $_ENV['BOT_ROOM'] ?? '1';
$botName = $_ENV['BOT_NAME'] ?? 'NotifyliBot';
$keepaliveSeconds = 25;
$reconnectWaitSeconds = 3;

// Redis configuration
$redisEnabled = (bool) ($_ENV['REDIS_ENABLED'] ?? false);
$redisHost = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
$redisPort = (int) ($_ENV['REDIS_PORT'] ?? 6379);
$redisPassword = $_ENV['REDIS_PASSWORD'] ?? null;

// Initialize Redis message broker
$messageBroker = new RedisMessageBroker(
    enabled: $redisEnabled,
    host: $redisHost,
    port: $redisPort,
    password: $redisPassword,
);

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

        // Setup Redis subscription if enabled
        $pubsub = null;
        $pubsubRedis = null;
        $subscriptionConfirmed = false;

        if ($messageBroker->isEnabled()) {
            try {
                $pubsubRedis = new RedisClient([
                    'scheme' => 'tcp',
                    'host' => $redisHost,
                    'port' => $redisPort,
                    'password' => $redisPassword,
                    'read_write_timeout' => -1, // Block forever - required for pub/sub to maintain subscription
                ]);

                $pubsub = $pubsubRedis->pubSubLoop();
                $pubsub->subscribe($messageBroker->getMessageChannel());

                echo '[' . date('Y-m-d H:i:s') . "] ✅ Redis subscription initiated for channel: {$messageBroker->getMessageChannel()}\n";
            } catch (\Throwable $e) {
                echo '[' . date('Y-m-d H:i:s') . "] ⚠️  Redis subscription unavailable: " . $e->getMessage() . "\n";
                $pubsub = null;
                $pubsubRedis = null;
            }
        }

        while ($client->isConnected()) {
            // Send keepalive if needed
            if ((time() - $lastKeepaliveAt) >= $keepaliveSeconds) {
                $client->sendJson([
                    'message' => '...',
                    'name' => $botName,
                    'room' => $room,
                    'type' => 'keepalive',
                ]);
                $lastKeepaliveAt = time();
            }

            // Check for WebSocket messages
            $incoming = $client->receiveJson(1);
            if ($incoming !== null) {
                $sender = (string) ($incoming['name'] ?? '');
                if ($sender !== $botName) {
                    echo '[' . date('Y-m-d H:i:s') . "] Received WS message from: $sender\n";
                }
            }

            // Check for Redis messages
            if ($pubsub !== null) {
                try {
                    // Check for messages with non-blocking behavior
                    foreach ($pubsub as $message) {
                        // Handle both object and array formats from Predis
                        $kind = is_object($message) ? ($message->kind ?? null) : ($message['kind'] ?? null);
                        $payload = is_object($message) ? ($message->payload ?? null) : ($message['payload'] ?? null);

                        // Handle subscription confirmation (only once)
                        if ($kind === 'subscribe' && !$subscriptionConfirmed) {
                            $subscriptionConfirmed = true;
                            echo '[' . date('Y-m-d H:i:s') . "] ✅ Redis subscription confirmed\n";
                            // Don't break - continue listening for messages
                            continue;
                        }

                        if ($kind === 'message' && $payload) {
                            echo '[' . date('Y-m-d H:i:s') . "] 🔔 Received Redis message\n";

                            $data = json_decode($payload, true);

                            if ($data && isset($data['user_id'])) {
                                $userId = (int) $data['user_id'];
                                $targetRoom = (string) ($data['room'] ?? $room);
                                $messageText = (string) ($data['message'] ?? '');
                                $from = (string) ($data['from'] ?? 'system');

                                // Check user connected in the room
                                if ($messageBroker->isUserConnected($userId, $targetRoom)) {
                                    echo '[' . date('Y-m-d H:i:s') . "] User $userId is connected in room $targetRoom. Forwarding message...\n";

                                    // Send message to user through WebSocket
                                    $client->sendJson([
                                        'user_id' => $userId,
                                        'message' => $messageText,
                                        'name' => $from,
                                        'room' => $targetRoom,
                                        'type' => 'direct_message',
                                    ]);

                                    echo '[' . date('Y-m-d H:i:s') . "] ✅ Message forwarded to user $userId\n";
                                } else {
                                    echo '[' . date('Y-m-d H:i:s') . "] ⚠️  User $userId not connected in room $targetRoom. Message not delivered.\n";
                                }
                            }
                        }

                        // Don't break - keep the subscription active by continuing to iterate
                    }
                } catch (\Throwable $e) {
                    // Timeout is expected and normal - Redis is just idle
                    $errorMsg = $e->getMessage();

                    // Only log unexpected errors
                    if (!preg_match('/reading line|timed out|stream|temporarily unavailable|broken pipe|connection/i', $errorMsg)) {
                        echo '[' . date('Y-m-d H:i:s') . "] Redis error: " . $errorMsg . "\n";
                    }

                    // Small sleep to prevent busy loop
                    usleep(50000); // 50ms
                }
            }
        }
    } catch (\Throwable $e) {
        echo '[' . date('Y-m-d H:i:s') . '] Bot error: ' . $e->getMessage() . "\n";
    } finally {
        // Cleanup Redis subscription
        if ($pubsub !== null) {
            try {
                $pubsub->stop();
            } catch (\Throwable $e) {
                // Already stopped
            }
        }

        // Disconnect Redis client
        if ($pubsubRedis !== null) {
            try {
                $pubsubRedis->disconnect();
            } catch (\Throwable $e) {
                // Already disconnected
            }
        }

        // Disconnect WebSocket client
        try {
            $client->disconnect();
        } catch (\Throwable $e) {
            // Already disconnected
        }
    }

    sleep($reconnectWaitSeconds);
}

