<?php

require __DIR__.'/config/app.php';

use Predis\Client as RedisClient;

echo "=== Testing Redis Pub/Sub ===\n\n";

if (isset($argv[1]) && $argv[1] === 'publish') {
    // Publisher mode
    $publisher = new RedisClient([
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
    ]);

    echo "Publishing test message to 'test-channel'...\n";
    $result = $publisher->publish('test-channel', json_encode(['test' => 'Hello from Redis!', 'time' => time()]));
    echo "✅ Message published! Subscribers: $result\n";
    exit(0);
}

// Subscriber mode (default)
echo "Starting subscriber...\n";
$subscriber = new RedisClient([
    'scheme' => 'tcp',
    'host' => '127.0.0.1',
    'port' => 6379,
    'read_write_timeout' => 3,
]);

echo "Subscriber created\n";

// Start subscription
$pubsub = $subscriber->pubSubLoop();
$pubsub->subscribe('test-channel');

echo "✅ Subscribed to 'test-channel'\n";
echo "Waiting for messages... (press Ctrl+C to stop)\n";
echo "In another terminal, run: php test-redis-pubsub.php publish\n\n";

// Listen for messages
$messageCount = 0;

try {
    foreach ($pubsub as $message) {
        $messageCount++;

        // Handle both object and array formats from Predis
        $kind = is_object($message) ? ($message->kind ?? null) : ($message['kind'] ?? null);
        $channel = is_object($message) ? ($message->channel ?? null) : ($message['channel'] ?? null);
        $payload = is_object($message) ? ($message->payload ?? null) : ($message['payload'] ?? null);

        echo "[Message #$messageCount] kind=$kind, channel=$channel\n";

        if ($kind === 'subscribe') {
            echo "  → Subscription confirmed for channel: $channel\n\n";
            continue;
        }

        if ($kind === 'message') {
            echo "  → ✅ GOT MESSAGE: $payload\n\n";
        }
    }
} catch (\Throwable $e) {
    echo "\nError: " . $e->getMessage() . "\n";
}

// Cleanup
$pubsub->stop();

echo "\n=== Test Complete ===\n";
echo "Total messages received: $messageCount\n";
