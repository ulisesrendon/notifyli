<?php

require __DIR__ . '/../config/app.php';

use Neuralpin\Notifyli\Services\RedisMessageBroker;

// Parse command line arguments
$options = getopt('u:r:m:f:h', ['user:', 'room:', 'message:', 'from:', 'help']);

if (isset($options['h']) || isset($options['help'])) {
    printHelp();
    exit(0);
}

// Get parameters
$userId = $options['u'] ?? $options['user'] ?? null;
$room = $options['r'] ?? $options['room'] ?? '1';
$message = $options['m'] ?? $options['message'] ?? null;
$from = $options['f'] ?? $options['from'] ?? 'Test Bot';

// Validate user ID
if (!$userId) {
    echo "\n❌ Error: User ID is required\n";
    printHelp();
    exit(1);
}

if (!is_numeric($userId)) {
    echo "\n❌ Error: User ID must be a number\n";
    exit(1);
}

// If message not provided, prompt interactively
if (!$message) {
    echo "\n📨 Notification Sender for Notifyli\n";
    echo str_repeat("-", 50) . "\n\n";
    echo "User ID: " . $userId . "\n";
    echo "Room: " . $room . "\n";
    echo "\nEnter your message (required):\n";
    $message = trim(fgets(STDIN));

    if (empty($message)) {
        echo "\n❌ Error: Message cannot be empty\n";
        exit(1);
    }

    echo "\nSender name (default: 'Test Bot'): ";
    $senderInput = trim(fgets(STDIN));
    if (!empty($senderInput)) {
        $from = $senderInput;
    }
}

// Initialize Redis broker
$redisEnabled = (bool) ($_ENV['REDIS_ENABLED'] ?? false);
$redisHost = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
$redisPort = (int) ($_ENV['REDIS_PORT'] ?? 6379);
$redisPassword = $_ENV['REDIS_PASSWORD'] ?? null;

$broker = new RedisMessageBroker(
    enabled: $redisEnabled,
    host: $redisHost,
    port: $redisPort,
    password: $redisPassword,
);

// Check if Redis is enabled
if (!$broker->isEnabled()) {
    echo "\n❌ Error: Redis is not enabled or not connected\n";
    echo "   Please check your Redis configuration in .env\n";
    exit(1);
}

// Check if user is connected
$isConnected = $broker->isUserConnected((int)$userId, $room);

echo "\n📨 Sending Notification\n";
echo str_repeat("-", 50) . "\n";
echo "User ID: " . $userId . "\n";
echo "Room: " . $room . "\n";
echo "From: " . $from . "\n";
echo "Message: " . substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '') . "\n";
echo "User Status: " . ($isConnected ? '✅ Connected' : '⚠️  Not Currently Connected') . "\n";
echo str_repeat("-", 50) . "\n";

// Publish message to Redis
$success = $broker->publish(
    userId: (int)$userId,
    room: $room,
    message: $message,
    from: $from
);

if ($success) {
    echo "\n✅ Notification sent successfully!\n";
    echo "   The message has been published to Redis.\n";
    if (!$isConnected) {
        echo "   Note: User is not currently connected. Message will not be delivered.\n";
    }
    echo "\n";
    exit(0);
} else {
    echo "\n❌ Error: Failed to send notification\n";
    echo "   Please check your Redis connection.\n\n";
    exit(1);
}

function printHelp()
{
    echo "\n📨 Notifyli Notification Sender\n";
    echo str_repeat("=", 50) . "\n\n";

    echo "Usage:\n";
    echo "  php console/send-notification.php -u <user_id> [options]\n";
    echo "  php console/send-notification.php --user <user_id> [options]\n\n";

    echo "Required Arguments:\n";
    echo "  -u, --user <id>              The user ID to send notification to (required)\n\n";

    echo "Optional Arguments:\n";
    echo "  -r, --room <room>            The room ID (default: 1)\n";
    echo "  -m, --message <text>         The message to send\n";
    echo "  -f, --from <name>            Sender name (default: 'Test Bot')\n";
    echo "  -h, --help                   Show this help message\n\n";

    echo "Examples:\n";
    echo "  # Interactive mode\n";
    echo "  php console/send-notification.php -u 123\n\n";

    echo "  # Send message directly\n";
    echo "  php console/send-notification.php -u 123 -m \"Hello User!\"\n\n";

    echo "  # Send to specific room with custom sender\n";
    echo "  php console/send-notification.php -u 456 -r \"lobby\" -m \"Room notification\" -f \"Admin\"\n\n";

    echo "Note:\n";
    echo "  • Make sure Redis is enabled in your .env file (REDIS_ENABLED=true)\n";
    echo "  • The user must be connected to receive notifications\n";
    echo "  • Check the bot and server logs for delivery status\n\n";
}
