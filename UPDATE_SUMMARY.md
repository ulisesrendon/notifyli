# Update Summary: User ID Notifications & Testing

## Changes Made

### 1. Web Demo Enhanced (public/index.php & public/main.js)

#### Updates to index.php
- Added **User ID input field** (required for notifications)
- Added **Room input field** (configurable, defaults to "1")
- Improved form layout with labeled date inputs

#### Updates to main.js
- Added `userId` and `room` to the user form object
- Implemented **localStorage persistence**:
  - User ID and Room are saved automatically
  - Reloaded on page refresh
- Enhanced message handling:
  - Added support for `direct_message` type
  - Direct messages labeled with "[notification]"
- Updated all WebSocket messages to include `user_id`
- Added validation for User ID (must be set before sending)

### 2. Console Testing Tool (console/send-notification.php)

**New interactive command-line tool** for testing notifications:

**Features:**
- ✅ Send notifications directly to specific users
- ✅ Interactive and direct modes
- ✅ Configurable room selection
- ✅ Custom sender names
- ✅ User connection status display
- ✅ Comprehensive error handling
- ✅ Detailed help documentation

**Usage Examples:**
```bash
# Interactive mode (prompts for message)
php console/send-notification.php -u 123

# Direct mode (all parameters specified)
php console/send-notification.php -u 123 -m "Hello!" -r "lobby" -f "Admin"

# Show help
php console/send-notification.php -h
```

**Command Options:**
- `-u, --user <id>` - User ID (required)
- `-r, --room <room>` - Room ID (default: "1")
- `-m, --message <text>` - Message content
- `-f, --from <name>` - Sender name (default: "Test Bot")
- `-h, --help` - Show help

### 3. Comprehensive Testing Guide (TESTING_GUIDE.md)

Created detailed documentation covering:
- ✅ Web interface walkthrough
- ✅ Console tool usage (5 modes)
- ✅ 5 complete testing workflows
- ✅ Monitoring Redis directly
- ✅ Troubleshooting guide
- ✅ Advanced stress testing
- ✅ API reference

## Key Features

### Web Interface Enhancements
1. **User ID Field**: Required for identifying recipients
2. **Room Selection**: Send/receive in different rooms
3. **Persistent Settings**: Auto-saves User ID & Room to localStorage
4. **Direct Notification Display**: Shows "[notification]" label for bot messages

### Testing Capabilities
1. **Interactive Mode**: Enter messages when running command
2. **Batch Mode**: Send multiple messages via script
3. **User Status Check**: Shows if user is connected
4. **Graceful Error Handling**: Clear error messages for debugging

### Data Flow
```
User A (UI)                Bot/External System (CLI)
     |                              |
     v                              v
  User ID 123                   User ID 123
  Room: "chat"                  Room: "chat"
     |                              |
     +----------> Redis +-----------+
                  Channel: "notifyli:messages"
                        |
                        v
                    bot.php
                  (listener)
                        |
                  Check connection
                        |
                        v
              WebSocket -> User A
              Show "[notification]"
```

## File Structure

```
notify/
├── public/
│   ├── index.php          (updated: added User ID & Room fields)
│   └── main.js            (updated: handle user_id, localStorage, direct_message)
├── console/
│   └── send-notification.php  (new: testing tool)
└── TESTING_GUIDE.md           (new: comprehensive guide)
```

## Testing Steps

### 1. Verify Web Interface
```bash
php server.php
# Open: http://localhost:8000/
# Set User ID: 100
# Send a message
```

### 2. Test Redis Notifications
```bash
php bot.php  # In another terminal
# Send notification via CLI
php console/send-notification.php -u 100 -m "Test message"
# Verify message appears in web interface with "[notification]" label
```

### 3. Stress Test
```bash
# Send 10 notifications rapidly
for i in {1..10}; do
  php console/send-notification.php -u 200 -m "Message $i" &
done
wait
```

## Backward Compatibility

✅ All changes are backward compatible:
- Existing chatting functionality remains unchanged
- User ID is optional for basic chat
- Required only for direct notifications
- Existing message types still work as before

## Environment Setup

Required `.env` settings:
```env
REDIS_ENABLED=true
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
BOT_NAME=NotifyliBot
APP_WS_SERVER_PROTOCOL=ws
APP_WS_SERVER_DOMAIN=localhost
APP_PORT=7000
```

## Next Steps

1. **Start the server**: `php server.php`
2. **Start the bot** (optional): `php bot.php`
3. **Open the demo**: http://localhost:8000/
4. **Test notifications**: `php console/send-notification.php -u <your-id> -m "Test"`

For detailed testing workflows, see [TESTING_GUIDE.md](TESTING_GUIDE.md)

## API Changes

### Web Message Format (User to Server)
```json
{
  "message": "Hello",
  "name": "Alice",
  "user_id": 123,
  "room": "1",
  "type": "usermsg"
}
```

### Server Message Format
```json
{
  "message": "Hello",
  "name": "Sender Name",
  "type": "direct_message"
}
```

### Redis Message Format
```json
{
  "user_id": 123,
  "room": "1",
  "message": "Hello",
  "from": "System",
  "timestamp": 1234567890
}
```

## Troubleshooting

**User ID not saving?**
- Check browser allows localStorage
- Verify User ID is set before sending message

**Notification not received?**
- Verify User ID matches in web interface
- Check bot is running
- Verify Redis is running: `redis-cli ping`
- Check logs: `tail logs/log.txt`

**Console script errors?**
- Make sure you're in the project root
- Verify Redis is configured in .env
- Run with: `php console/send-notification.php -h`

See [TESTING_GUIDE.md](TESTING_GUIDE.md) for comprehensive troubleshooting.
