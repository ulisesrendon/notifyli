<?php

require __DIR__.'/config/app.php';

use Predis\Client as RedisClient;

echo "Starting minimal Redis subscriber test...\n";

$redis = new RedisClient([
    'scheme' => 'tcp',
    'host' => '127.0.0.1',
    'port' => 6379,
    'read_write_timeout' => -1, // Block forever
]);

$pubsub = $redis->pubSubLoop();
$pubsub->subscribe('notifyli:messages');

echo "Subscribed to 'notifyli:messages' - waiting....\n";
echo "Now send a message and watch for output!\n\n";

foreach ($pubsub as $message) {
    echo "Received: " . json_encode($message) . "\n";
}
