# Notifyli Testing Guide

## Overview

This guide explains how to test the Notifyli notification system using the provided console tools and web interface.

## Web Demo

### Accessing the Demo

1. Start your WebSocket server:
```bash
php server.php
```

2. (Optional) Start the Redis bot if testing Redis notifications:
```bash
php bot.php
```

3. Open your browser to: `http://localhost:8000/`

### Web Interface Settings

The demo interface now includes configurable fields:

- **User ID** (required): Your unique user identifier
- **Room**: The room/channel you're connecting to (default: 1)
- **Name**: Your display name in the chat
- **Message**: Your message to send

### Workflow

1. Enter your User ID (e.g., 123)
2. Enter your display name
3. Type a message and press "Send Message"
4. Your User ID and Room are automatically saved to browser localStorage

### Receiving Direct Notifications

When the bot or another system sends a notification:
- Messages appear with `[notification]` suffix
- They're delivered even if you're in a different context
- All messages show in the message box

## Console Testing Tools

### Send Notification Command

Use `console/send-notification.php` to send test notifications to connected users.

#### Interactive Mode

```bash
php console/send-notification.php -u 123
```

This will:
1. Prompt you to enter the message
2. Show sender name input (optional, defaults to "Test Bot")
3. Send the notification to user 123
4. Display delivery status

Example output:
```
📨 Notification Sender for Notifyli
--------------------------------------------------

User ID: 123
Room: 1

Enter your message (required):
Hello from Redis!

Sender name (default: 'Test Bot'): Admin

📨 Sending Notification
--------------------------------------------------
User ID: 123
Room: 1
From: Admin
Message: Hello from Redis!
User Status: ✅ Connected
--------------------------------------------------

✅ Notification sent successfully!
   The message has been published to Redis.
```

#### Direct Mode

```bash
php console/send-notification.php -u 123 -m "Hello!" -r "1" -f "Admin"
```

Sends notification directly without prompting.

#### Command Line Options

```
-u, --user <id>              The user ID to send notification to (required)
-r, --room <room>            The room ID (default: 1)
-m, --message <text>         The message to send
-f, --from <name>            Sender name (default: 'Test Bot')
-h, --help                   Show help message
```

#### Examples

**Send to user 100 in room "lobby":**
```bash
php console/send-notification.php -u 100 -r "lobby" -m "Welcome to lobby!" -f "System"
```

**Send to multiple users (run separately):**
```bash
php console/send-notification.php -u 101 -m "Broadcast message" -f "Admin"
php console/send-notification.php -u 102 -m "Broadcast message" -f "Admin"
php console/send-notification.php -u 103 -m "Broadcast message" -f "Admin"
```

**Interactive send with custom room:**
```bash
php console/send-notification.php -u 50 -r "vip" -from "VIP Bot"
```

### Help Command

```bash
php console/send-notification.php -h
php console/send-notification.php --help
```

Shows detailed usage information.

## Testing Workflow

### Test 1: Basic WebSocket Chat

**Setup:**
1. Open two browser windows to `http://localhost:8000/`
2. Set different User IDs (e.g., 101 and 102)
3. Set the same Room

**Test:**
1. In window 1: Send a message
2. Verify message appears in both windows
3. Change Room in one window (e.g., to "2")
4. Send a message - it should only appear in the new room

### Test 2: Redis Direct Notification

**Setup:**
1. Open browser to `http://localhost:8000/` (or whatever port is in your .env APP_PORT)
2. Set User ID to 200
3. Enter a display name
4. **IMPORTANT**: Send at least one message (e.g., "Hello") to register yourself in Redis
5. Keep the browser tab open

**Test:**
```bash
php console/send-notification.php -u 200 -m "Notification from Redis!" -f "System"
```

**Verify:**
- Message appears in the chat with "[notification]" label
- Appears even though you didn't send it through the chat form
- Shows the custom sender name

**Note**: Users must send at least one WebSocket message before they're registered in Redis. The keepalive messages alone don't register users - you need to send an actual chat message first.

### Test 3: Offline Notification Handling

**Setup:**
1. Set User ID to 300
2. DON'T open browser or close it

**Test:**
```bash
php console/send-notification.php -u 300 -m "You are offline" -f "Bot"
```

**Verify:**
- Command shows: "⚠️ Not Currently Connected"
- Message is not delivered (expected behavior)
- Now open browser with User ID 300 and try again

### Test 4: Multi-Room Broadcasting

**Setup:**
1. Open 3 browser windows
2. Set User IDs: 401, 402, 403
3. Windows 1+2: Room "chat-room"
4. Window 3: Room "admin-room"

**Test:**
```bash
# Send to chat-room
php console/send-notification.php -u 401 -r "chat-room" -m "Message 1"
php console/send-notification.php -u 402 -r "chat-room" -m "Message 2"

# Send to admin-room
php console/send-notification.php -u 403 -r "admin-room" -m "Admin message"
```

**Verify:**
- Windows 1+2 receive messages to "chat-room"
- Window 3 receives message to "admin-room"
- No cross-room message leakage

### Test 5: Concurrent Notifications

**Setup:**
1. Open browser with User ID 500
2. Set Room to "test"

**Test:**
```bash
php console/send-notification.php -u 500 -r "test" -m "Message 1" & \
php console/send-notification.php -u 500 -r "test" -m "Message 2" & \
php console/send-notification.php -u 500 -r "test" -m "Message 3"
```

**Verify:**
- All 3 messages appear (order may vary due to concurrency)
- No messages are lost
- No errors in the bot or server logs

## Monitoring

### Check User Connections

View all connected users in a room via Redis CLI:

```bash
redis-cli
> HGETALL notifyli:connections:1
```

Shows all users with their connection details.

### Monitor Redis Messages

In another terminal, watch the message channel:

```bash
redis-cli
> SUBSCRIBE notifyli:messages
```

Shows all messages published through the system.

### Check Bot Status

Monitor the bot's activity:

```bash
# Watch bot output
tail -f logs/bot.log

# If bot isn't started yet, start it in a separate terminal:
php bot.php
```

## Troubleshooting

### Notification Not Received

**Issue**: Sent notification but didn't appear in browser

**Checks:**
1. **Verify Redis is enabled** in your .env file:
   ```bash
   # In .env file, make sure this is set to true
   REDIS_ENABLED=true
   ```
2. **User must be registered**: Open browser, enter User ID, and **send at least one message** (not just keep browser open)
3. Verify User ID matches:
   ```bash
   redis-cli HGETALL notifyli:connections:1
   ```
4. Check bot is running:
   ```bash
   # Windows
   tasklist | findstr php
   # Linux/Mac
   ps aux | grep "php bot.php"
   ```
5. Verify Redis is running:
   ```bash
   redis-cli ping
   # Should return: PONG
   ```
6. Check logs for errors:
   ```bash
   tail logs/log.txt
   ```

**Common Fix**: Restart server and bot after enabling Redis in .env

### User Shows as Not Connected

**Possible causes:**
- User hasn't connected to WebSocket yet
- User is in a different room
- WebSocket connection was dropped

**Solution:**
- Make sure browser is open to `http://localhost:8000/`
- Check User ID matches what you're sending to
- Check Room ID matches

### Command Won't Run

**Issue**: "command not found" or permission error

**Solution:**
```bash
# Make sure you're in the right directory
cd /path/to/notify

# Run with full PHP path
php console/send-notification.php -u 123

# Or with explicit namespace
php ./console/send-notification.php -u 123
```

### Redis Connection Error

**Issue**: "Redis is not enabled or not connected"

**Checks:**
1. Verify Redis is running:
   ```bash
   redis-cli ping
   ```
2. Check .env file settings:
   ```bash
   grep REDIS_ .env
   ```
3. Ensure `REDIS_ENABLED=true`
4. Verify host and port are correct

## Advanced Testing

### Stress Testing

Send multiple notifications rapidly:

```bash
for i in {1..10}; do
  php console/send-notification.php -u 100 -m "Message $i" &
done
wait
```

### Multi-User Testing

Create a test script that simulates multiple users:

```bash
#!/bin/bash
# test-users.sh
for user_id in {1..5}; do
  php console/send-notification.php -u $user_id -m "Test message for user $user_id" -f "Test$user_id" &
done
wait
```

Run it:
```bash
chmod +x test-users.sh
./test-users.sh
```

## Best Practices

1. **Always set User ID**: The system relies on User IDs for routing
2. **Match rooms**: Sender and receiver must be in the same room
3. **Check bot status**: Verify bot is running for Redis notifications
4. **Monitor logs**: Keep an eye on `logs/log.txt` during testing
5. **Test offline scenarios**: Verify behavior when users disconnect
6. **Clear messages**: Refresh browser if needed to clear old test messages

## API Reference

### Web Interface

**JS API Available:**
```javascript
// In browser console, you can access the chat app:
chatApp.userForm.userId        // Current user ID
chatApp.userForm.room          // Current room
chatApp.messages               // All messages
chatApp.connectionStatus       // 'connected' or 'disconnected'
```

### Console Script

```bash
# Full usage
php console/send-notification.php \
  --user <id> \
  --room <room> \
  --message <text> \
  --from <name>
```

### PHP API

```php
$broker = new \Neuralpin\Notifyli\Services\RedisMessageBroker(
    enabled: true,
    host: 'localhost',
    port: 6379
);

// Send notification
$broker->publish(
    userId: 123,
    room: 'chat-room',
    message: 'Hello!',
    from: 'Bot'
);

// Check if user is connected
if ($broker->isUserConnected(123, 'chat-room')) {
    echo "User is online!";
}
```
