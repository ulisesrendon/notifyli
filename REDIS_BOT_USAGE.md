# Redis Messenger Bot

## Overview

The bot.php script has been enhanced to act as a Redis Pub/Sub subscriber that listens for messages destined for connected users and forwards them through existing WebSocket connections.

## How It Works

1. **Connects to WebSocket Server**: The bot maintains a persistent connection to the messaging server
2. **Subscribes to Redis Channel**: Listens on the `notifyli:messages` Redis channel (configurable)
3. **Monitors User Connections**: Tracks which users are connected via Redis state storage
4. **Forwards Messages**: When a message arrives for a user:
   - Checks if the user is connected in the target room
   - If connected, forwards the message through the WebSocket connection
   - If not connected, the message is not delivered (can be stored separately if needed)

## Configuration

Set these environment variables in your `.env` file:

```env
# Enable Redis for the bot
REDIS_ENABLED=true
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
# REDIS_PASSWORD=null  # Optional

# Bot configuration
BOT_NAME=NotifyliBot
BOT_ROOM=1  # Default room for the bot

# WebSocket server configuration
APP_WS_SERVER_PROTOCOL=ws
APP_WS_SERVER_DOMAIN=localhost
APP_PORT=7000
```

## Message Format

### Publishing a Message to Redis

To send a message to a user, publish a JSON message to the Redis channel `notifyli:messages`:

```php
<?php
$redis = new \Predis\Client([
    'scheme' => 'tcp',
    'host' => 'localhost',
    'port' => 6379,
]);

$message = [
    'user_id' => 123,          // ID of the user to receive the message
    'room' => '1',             // Room where the user is connected
    'message' => 'Hello!',     // Message content
    'from' => 'system',        // Sender name
    'timestamp' => time(),     // Optional
];

$redis->publish('notifyli:messages', json_encode($message));
```

### Message Structure

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `user_id` | int | ✓ | The ID of the user to send the message to |
| `room` | string | ✓ | The room/channel where the user is connected |
| `message` | string | ✓ | The message content to send |
| `from` | string | ✗ | Sender identifier (defaults to 'system') |
| `timestamp` | int | ✗ | Optional timestamp |

## Running the Bot

```bash
php bot.php
```

The bot will:
1. Connect to the WebSocket server
2. Subscribe to the Redis message channel
3. Send keepalive messages every 25 seconds (configurable)
4. Listen for Redis messages and forward them to connected users
5. Automatically reconnect if the connection drops

## Architecture

### Services

#### `RedisMessageBroker` (`src/Services/RedisMessageBroker.php`)

Provides utilities for interacting with the Redis message system:

- `subscribe(callable $onMessage)`: Listen for messages with a callback
- `publish(int $userId, string $room, string $message, string $from)`: Publish a message
- `isUserConnected(int $userId, string $room)`: Check if a user is connected
- `getUserConnection(int $userId, string $room)`: Get connection info for a user

#### `ReusableWebSocketClient` (`src/Clients/ReusableWebSocketClient.php`)

Handles WebSocket communication:

- `connect()`: Establish connection
- `sendJson(array $data)`: Send a JSON message
- `receiveJson(int $timeout)`: Receive JSON with timeout
- `isConnected()`: Check connection status
- `disconnect()`: Close connection

#### `RedisStateManager` (`src/Services/RedisStateManager.php`)

Manages connection state in Redis:

- `registerConnection(int $clientId, string $room, string $name)`: Register a new connection
- `removeConnection(int $clientId, string $room)`: Remove a connection
- `getRoomConnections(string $room)`: Get all connections in a room
- `getRoomConnectionCount(string $room)`: Count connections in a room

## Example Workflow

1. **User connects**: The messaging server stores the connection in Redis via `RedisStateManager::registerConnection()`
2. **Message arrives**: External system publishes to Redis channel `notifyli:messages`
3. **Bot receives**: Redis subscription picks up the message
4. **Bot checks**: Uses `RedisMessageBroker::isUserConnected()` to verify user is online
5. **Bot forwards**: Sends the message through WebSocket with `user_id` field
6. **Server delivers**: The messaging server routes the message to the specific user

## Logging

The bot logs all events to STDOUT with timestamps:

```
[2024-01-15 10:23:45] Connected to ws://localhost:7000
[2024-01-15 10:23:45] Bot subscribed to Redis messages on channel: notifyli:messages
[2024-01-15 10:23:50] User 123 is connected in room 1. Forwarding message...
[2024-01-15 10:23:50] Message forwarded to user 123
```

## Error Handling

- If Redis is not available, the bot continues in normal mode without Redis messaging
- Connection failures automatically trigger a reconnect after 3 seconds (configurable)
- Redis timeouts (1 second) don't block WebSocket operations
- All exceptions are logged with timestamps

## Performance Considerations

1. **Non-blocking**: Redis subscription with 1-second timeout prevents blocking WebSocket operations
2. **Iterative Message Processing**: One message is processed per loop iteration to allow keepalives and WebSocket checks
3. **Minimal Overhead**: Only checks Redis for messages that target specific users with active connections
4. **Clean Disconnection**: Properly cleans up Redis and WebSocket connections on shutdown

## Troubleshooting

### Bot not receiving messages
- Verify Redis is running and accessible: `redis-cli ping`
- Check environment variables are set correctly
- Ensure users are registered in Redis: use `HGETALL notifyli:connections:room_id` in redis-cli

### WebSocket connection drops
- Check WebSocket server is running
- Verify network connectivity
- Check bot logs for specific error messages

### Performance issues
- Reduce keepalive interval (`$keepaliveSeconds`) if needed
- Ensure Redis server has adequate resources
- Monitor message queue size in Redis

## API Reference

### Publishing Messages

```php
$broker = new \Neuralpin\Notifyli\Services\RedisMessageBroker(
    enabled: true,
    host: 'localhost',
    port: 6379,
);

$broker->publish(
    userId: 123,
    room: '1',
    message: 'Hello World',
    from: 'system'
);
```

### Checking User Connection

```php
if ($broker->isUserConnected(123, '1')) {
    echo "User is connected";
}
```

### Getting Connection Info

```php
$info = $broker->getUserConnection(123, '1');
if ($info) {
    echo "Connected at: " . $info['connected_at'];
}
```
