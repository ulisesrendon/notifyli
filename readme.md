# Notifyli: Real-time Communication with Redis Notifications

A WebSocket-based real-time messaging system that enables users to communicate in private and public channels. Built-in Redis broker allows external systems to send notifications to specific users in their designated channels.

## Overview

**Primary Goal**: Enable real-time user-to-user communication in private/public channels via WebSocket.

**Secondary Feature**: Redis-based notification broker for sending automated messages from external systems to connected users.

### Key Architecture

- **User Identification**: Each user must identify themselves with a `user_id` and connect to a specific `room` (channel)
- **Private Channels**: Users typically connect to personal `/user/{user_id}` rooms to receive notifications
- **Public Channels**: Users can join shared rooms for group communication
- **External Integration**: Third-party systems orchestrate notifications via Redis broker, targeting specific users and channels

**Important**: The UI MUST initialize with a `user_id` and `room` before the WebSocket connection is established. This allows the system to:
1. Route direct notifications to the correct user
2. Allow external systems to target specific users for automated notifications
3. Maintain user state in Redis for offline notification handling

## Example Implementation Files

This repository includes three example implementations that demonstrate how to integrate the system:

- **`server.php`** - Example WebSocket server initialization. Demonstrates how to configure and start the messaging server with your environment settings.

- **`notification-bridge.php`** - Example notification bridge implementation. Shows how to listen to Redis pub/sub and forward notifications to connected users via WebSocket.

- **`public/index.php`** - Example client UI implementation. Demonstrates user authentication with `user_id`, room selection, and real-time message handling.

These are reference implementations. Adapt them to your specific infrastructure and requirements.

## Cloud Production Deployment

This guide covers deploying all three components in a production cloud environment.

### Prerequisites

- Cloud server (Ubuntu 20.04+ recommended) with root access
- PHP 8.2 or higher installed
- Redis server installed and running
- Nginx web server
- Domain name with DNS configured
- SSL certificates (Let's Encrypt recommended)

### Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                    Nginx (Port 80/443)                  │
│  ┌──────────────────────┬───────────────────────────┐  │
│  │   Web UI (PHP-FPM)   │   WebSocket Proxy (7000)  │  │
│  │  public/index.php    │   → server.php daemon     │  │
│  └──────────────────────┴───────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
                              │
                              ├──►  Redis Pub/Sub
                              │
                 ┌────────────┴────────────┐
                 │ notification-bridge.php │
                 │  (Notification Bridge)  │
                 └─────────────────────────┘
```

### Step 1: Server Preparation

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install dependencies
sudo apt install -y nginx php8.2-fpm php8.2-cli php8.2-redis php8.2-mbstring \
    php8.2-xml php8.2-curl redis-server git composer

# Enable Redis
sudo systemctl enable redis-server
sudo systemctl start redis-server

# Verify Redis is running
redis-cli ping
# Expected: PONG
```

### Step 2: Deploy Application Code

```bash
# Create application directory
sudo mkdir -p /var/www/notifyli
sudo chown -R www-data:www-data /var/www/notifyli

# Clone or copy your application
cd /var/www/notifyli
# ... upload your code here ...

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Set proper permissions
sudo chown -R www-data:www-data /var/www/notifyli
sudo chmod -R 755 /var/www/notifyli
```

### Step 3: Configure Environment

Create `/var/www/notifyli/.env`:

```env
# WebSocket Server Configuration
APP_WS_SERVER_PROTOCOL=wss  # Use wss for secure WebSocket
APP_WS_SERVER_DOMAIN=your-domain.com
APP_PORT=7000

# Redis Configuration
REDIS_ENABLED=true
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

# Notification Bridge Configuration
BOT_NAME=NotificationBot
BOT_ROOM=1
```

### Step 4: Configure Nginx (Web UI + WebSocket Proxy)

Create `/etc/nginx/sites-available/notifyli.conf`:

```nginx
# WebSocket upgrade handling
map $http_upgrade $connection_upgrade {
    default upgrade;
    '' close;
}

# HTTP to HTTPS redirect
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com;
    
    return 301 https://$server_name$request_uri;
}

# Main HTTPS server
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name your-domain.com;

    # SSL Configuration (Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Logs
    access_log /var/log/nginx/notifyli-access.log;
    error_log /var/log/nginx/notifyli-error.log;

    # Document root for web UI
    root /var/www/notifyli/public;
    index index.php index.html;

    # Serve static files and PHP
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM Configuration (Web UI Client)
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Increase timeouts for long-polling if needed
        fastcgi_read_timeout 300;
    }

    # WebSocket Proxy to server.php daemon
    location /ws {
        proxy_pass http://127.0.0.1:7000;
        proxy_http_version 1.1;
        
        # WebSocket upgrade headers
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        
        # Standard proxy headers
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # Timeouts for long-lived connections
        proxy_connect_timeout 7d;
        proxy_send_timeout 7d;
        proxy_read_timeout 7d;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ /\.env {
        deny all;
    }
}
```

Enable the site:
```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/notifyli.conf /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# Reload nginx
sudo systemctl reload nginx
```

### Step 5: SSL Certificate (Let's Encrypt)

```bash
# Install certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtain certificate (interactive)
sudo certbot --nginx -d your-domain.com

# Auto-renewal is configured automatically
# Test renewal
sudo certbot renew --dry-run
```

### Step 6: Configure Firewall

```bash
# Allow necessary ports
sudo ufw allow 22/tcp      # SSH
sudo ufw allow 80/tcp      # HTTP
sudo ufw allow 443/tcp     # HTTPS
sudo ufw allow 7000/tcp    # WebSocket (if direct access needed)

# Enable firewall
sudo ufw enable
sudo ufw status
```

### Step 7: Create Log Directories

```bash
# Create log directory
sudo mkdir -p /var/log/notifyli

# Set permissions
sudo chown -R www-data:www-data /var/log/notifyli
sudo chmod 755 /var/log/notifyli
```

### Step 8: Configure Supervisor

Install Supervisor:
```bash
sudo apt install -y supervisor
```

Create `/etc/supervisor/conf.d/notifyli.conf`:

```ini
[program:notifyli-websocket]
command=/usr/bin/php -q /var/www/notifyli/server.php
directory=/var/www/notifyli
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/notifyli/websocket-server.log
stderr_logfile=/var/log/notifyli/websocket-server-error.log
stopasgroup=true
killasgroup=true

[program:notifyli-notification-bridge]
command=/usr/bin/php -q /var/www/notifyli/notification-bridge.php
directory=/var/www/notifyli
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/notifyli/notification-bridge.log
stderr_logfile=/var/log/notifyli/notification-bridge-error.log
stopasgroup=true
killasgroup=true
```

### Step 9: Enable and Start Services

```bash
# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update

# Start programs
sudo supervisorctl start notifyli-websocket
sudo supervisorctl start notifyli-notification-bridge

# Check status
sudo supervisorctl status
```

### Step 10: Verify Deployment

```bash
# Check WebSocket server is listening
sudo netstat -tulpn | grep :7000

# Check logs
sudo supervisorctl tail -f notifyli-websocket
sudo supervisorctl tail -f notifyli-notification-bridge

# Or check log files
sudo tail -f /var/log/notifyli/websocket-server.log
sudo tail -f /var/log/notifyli/notification-bridge.log

# Test Redis connection
redis-cli ping

# Monitor Redis pub/sub
redis-cli SUBSCRIBE notifyli:messages
```

### Managing Services

```bash
# Start services
sudo supervisorctl start notifyli-websocket notifyli-notification-bridge

# Stop services
sudo supervisorctl stop notifyli-websocket notifyli-notification-bridge

# Restart services
sudo supervisorctl restart notifyli-websocket notifyli-notification-bridge

# View logs
sudo tail -n 100 /var/log/notifyli/websocket-server.log
sudo tail -n 100 /var/log/notifyli/notification-bridge.log

# Follow logs in real-time
sudo supervisorctl tail -f notifyli-websocket
sudo supervisorctl tail -f notifyli-notification-bridge
```

### Maintenance & Monitoring

**Daily Restart (Required)**

Create a cron job to restart services daily at 3 AM to mitigate PHP daemon memory growth over time:

```bash
sudo crontab -e
```

Add:
```cron
# Restart Notifyli services daily at 3 AM
0 3 * * * supervisorctl restart notifyli-websocket notifyli-notification-bridge
```

**Log Rotation**

Create `/etc/logrotate.d/notifyli`:

```
/var/log/notifyli/*.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0644 www-data www-data
    sharedscripts
    postrotate
        supervisorctl restart notifyli-websocket > /dev/null 2>&1 || true
        supervisorctl restart notifyli-notification-bridge > /dev/null 2>&1 || true
    endscript
}
```

**Monitoring**

```bash
# Check service health
supervisorctl status notifyli-websocket
supervisorctl status notifyli-notification-bridge

# Monitor resource usage
top -p $(pgrep -f "server.php")
top -p $(pgrep -f "notification-bridge.php")

# Check Redis memory usage
redis-cli INFO memory

# Monitor active connections
redis-cli CLIENT LIST
```

## Integration Guide

### Client UI Integration (Example: public/index.php)

The example UI demonstrates key integration points:

**Required Fields**:
- `user_id`: Unique identifier for the user (required for targeted notifications)
- `room`: Channel identifier (e.g., "lobby", "/user/123")
- `name`: Display name for messages

**WebSocket Connection**:
```javascript
const ws = new WebSocket('wss://your-domain.com/ws');

ws.onopen = () => {
    // Send initial authentication/join message
    ws.send(JSON.stringify({
        type: 'usermsg',
        user_id: userId,
        room: roomId,
        name: userName,
        message: 'joined'
    }));
};

ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    
    // Handle direct notifications
    if (data.type === 'direct_message') {
        showNotification(data.message, data.name);
    } else {
        // Handle regular chat messages
        displayMessage(data.message, data.name);
    }
};
```

**Message Types**:

Regular chat message (user to user):
```json
{
  "type": "usermsg",
  "user_id": 123,
  "room": "lobby",
  "message": "Hello!",
  "name": "Alice"
}
```

Direct notification (system to user):
```json
{
  "type": "direct_message",
  "user_id": 123,
  "message": "Order shipped!",
  "from": "OrderSystem"
}
```

### External System Integration (Publishing Notifications)

External applications can send notifications to connected users via Redis pub/sub.

**PHP Integration**:

```php
<?php
require 'vendor/autoload.php';

use Neuralpin\Notifyli\Services\RedisMessageBroker;

// Initialize broker
$broker = new RedisMessageBroker(
    enabled: true,
    host: '127.0.0.1',
    port: 6379,
    password: null
);

// Send notification to specific user
$broker->publish(
    userId: 123,
    room: '/user/123',  // Private notification channel
    message: 'Your order #12345 has been shipped!',
    from: 'OrderSystem'
);

// Check if user is currently connected
if ($broker->isUserConnected(123, '/user/123')) {
    echo "User is online - notification delivered\n";
} else {
    echo "User is offline - notification queued\n";
}
```

**Direct Redis Integration (Any Language)**:

```bash
# Using redis-cli
redis-cli PUBLISH notifyli:messages '{"user_id":123,"room":"/user/123","message":"Hello!","from":"System"}'
```

```python
# Python example
import redis
import json

r = redis.Redis(host='localhost', port=6379, decode_responses=True)

notification = {
    'user_id': 123,
    'room': '/user/123',
    'message': 'Your order has been shipped!',
    'from': 'OrderSystem',
}

r.publish('notifyli:messages', json.dumps(notification))
```

```javascript
// Node.js example
const redis = require('redis');
const publisher = redis.createClient();

await publisher.connect();

const notification = {
  user_id: 123,
  room: '/user/123',
  message: 'Your order has been shipped!',
  from: 'OrderSystem'
};

await publisher.publish('notifyli:messages', JSON.stringify(notification));
```

**Message Format**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `user_id` | int | ✓ | Target user ID |
| `room` | string | ✓ | Target channel (e.g., "/user/123" for private) |
| `message` | string | ✓ | Notification content |
| `from` | string | ✗ | Sender identifier (default: "system") |
| `timestamp` | int | ✗ | Unix timestamp |

### External Redis Check by `client_id`

If your external system is connected to the same Redis instance, you can detect active users by searching `client_id` inside the room hashes (`notifyli:connections:{room}`).

**Single room check**:
```bash
redis-cli HGETALL notifyli:connections:/user/123 | grep '"client_id":"abc123"'
```

**All rooms check (returns matching room key, user field, and payload)**:
```bash
redis-cli EVAL "
local out = {}
local cursor = '0'
repeat
    local scan = redis.call('SCAN', cursor, 'MATCH', 'notifyli:connections:*', 'COUNT', 100)
    cursor = scan[1]
    for _, key in ipairs(scan[2]) do
        local entries = redis.call('HGETALL', key)
        for i = 1, #entries, 2 do
            local field = entries[i]
            local value = entries[i + 1]
            if string.find(value, '\"client_id\":\"abc123\"', 1, true) then
                table.insert(out, key)
                table.insert(out, field)
                table.insert(out, value)
            end
        end
    end
until cursor == '0'
return out
" 0
```

If this returns data, the user session with that `client_id` is currently connected.

### Testing Notifications (console/send-notification.php)

For development and testing:

```bash
# Send test notification
php console/send-notification.php -u 123 -r "/user/123" -m "Test message" -f "TestBot"

# Interactive mode (prompts for details)
php console/send-notification.php -u 123

# Options:
#   -u, --user    User ID (required)
#   -r, --room    Room/channel (default: "1")
#   -m, --message Message content
#   -f, --from    Sender name (default: "Test Bot")
#   -h, --help    Show help
```

## Production Operations

### Service Management

```bash
# Start services
sudo supervisorctl start notifyli-websocket notifyli-notification-bridge

# Stop services
sudo supervisorctl stop notifyli-websocket notifyli-notification-bridge

# Restart services
sudo supervisorctl restart notifyli-websocket notifyli-notification-bridge

# Check service status
sudo supervisorctl status notifyli-websocket
sudo supervisorctl status notifyli-notification-bridge
```

### Monitoring

**Check Service Health**:
```bash
# Check if services are running
supervisorctl status notifyli-websocket
supervisorctl status notifyli-notification-bridge

# View service logs
sudo tail -n 100 /var/log/notifyli/websocket-server.log
sudo tail -n 100 /var/log/notifyli/notification-bridge.log

# Follow logs in real-time
sudo supervisorctl tail -f notifyli-websocket
sudo supervisorctl tail -f notifyli-notification-bridge
```

**Monitor Redis**:
```bash
# Check Redis connection
redis-cli ping
# Expected: PONG

# View connected users in a room
redis-cli HGETALL notifyli:connections:1

# Monitor pub/sub messages in real-time
redis-cli SUBSCRIBE notifyli:messages

# Check Redis memory usage
redis-cli INFO memory

# List active client connections
redis-cli CLIENT LIST
```

**Monitor WebSocket Server**:
```bash
# Check if port 7000 is listening
sudo netstat -tulpn | grep :7000
# Or
sudo ss -tulpn | grep :7000

# Check process resource usage
top -p $(pgrep -f "server.php")
ps aux | grep "server.php"
```

**Monitor Nginx**:
```bash
# Check nginx status
sudo systemctl status nginx

# View access logs
sudo tail -f /var/log/nginx/notifyli-access.log

# View error logs
sudo tail -f /var/log/nginx/notifyli-error.log

# Test configuration
sudo nginx -t

# Reload nginx configuration
sudo systemctl reload nginx
```

### Troubleshooting

**WebSocket Server Won't Start**:
```bash
# Check for port conflicts
sudo lsof -i :7000

# Check logs for errors
sudo tail -n 50 /var/log/notifyli/websocket-server-error.log

# Verify Redis is running
systemctl status redis-server

# Check application logs
sudo tail -f /var/log/notifyli/websocket-server-error.log
```

**Notification Bridge Won't Connect**:
```bash
# Ensure WebSocket server is running first
supervisorctl status notifyli-websocket

# Check bridge logs
sudo tail -n 50 /var/log/notifyli/notification-bridge-error.log

# Verify Redis connectivity
redis-cli ping

# Check application logs
sudo tail -f /var/log/notifyli/notification-bridge-error.log
```

**Notifications Not Delivered**:
```bash
# 1. Verify user is connected
redis-cli HGETALL notifyli:connections:1

# 2. Test Redis pub/sub manually
redis-cli PUBLISH notifyli:messages '{"user_id":123,"room":"1","message":"Test","from":"Manual"}'

# 3. Check bridge is subscribed
sudo grep "subscribed" /var/log/notifyli/notification-bridge.log

# 4. Verify correct user_id and room
# User must have connected with matching user_id and room
```

**High Memory Usage**:
```bash
# Check process memory
ps aux | grep -E "server.php|notification-bridge.php"

# Restart services
sudo supervisorctl restart notifyli-websocket notifyli-notification-bridge

# Monitor Redis memory
redis-cli INFO memory

# Clear old connections if needed (caution: clears all)
redis-cli FLUSHDB
```

**SSL Certificate Issues**:
```bash
# Check certificate expiry
sudo certbot certificates

# Renew certificates
sudo certbot renew

# Test certificate renewal
sudo certbot renew --dry-run

# Restart nginx after renewal
sudo systemctl reload nginx
```

### Performance Tuning

**PHP-FPM Optimization** (`/etc/php/8.2/fpm/pool.d/www.conf`):
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

**Nginx Optimization**:
```nginx
# Add to http block in /etc/nginx/nginx.conf
worker_processes auto;
worker_connections 4096;
keepalive_timeout 65;

# Enable gzip compression
gzip on;
gzip_types text/plain text/css application/json application/javascript;
```

**Redis Optimization** (`/etc/redis/redis.conf`):
```
# Increase max connections
maxclients 10000

# Set max memory limit
maxmemory 256mb
maxmemory-policy allkeys-lru

# Persistence (adjust based on needs)
save 900 1
save 300 10
save 60 10000
```

**System Limits** (`/etc/security/limits.conf`):
```
www-data soft nofile 65536
www-data hard nofile 65536
```

### Backup & Recovery

**Backup Redis Data**:
```bash
# Manual backup
redis-cli SAVE
sudo cp /var/lib/redis/dump.rdb /backup/redis-$(date +%Y%m%d).rdb

# Automated daily backup (cron)
0 4 * * * redis-cli SAVE && cp /var/lib/redis/dump.rdb /backup/redis-$(date +\%Y\%m\%d).rdb
```

**Backup Application**:
```bash
# Backup application directory
sudo tar -czf /backup/notifyli-$(date +%Y%m%d).tar.gz /var/www/notifyli
```

## API Reference

### PHP API (for External Systems)

```php
use Neuralpin\Notifyli\Services\RedisMessageBroker;

$broker = new RedisMessageBroker(
    enabled: true,
    host: '127.0.0.1',
    port: 6379,
    password: null
);

// Publish notification
$broker->publish(userId: 123, room: '/user/123', message: 'Hello', from: 'System');

// Check user connection
$isConnected = $broker->isUserConnected(123, '/user/123');

// Get connection details
$info = $broker->getUserConnection(123, '/user/123');
```

### WebSocket Protocol

**Client → Server** (user sends message):
```json
{
  "type": "usermsg",
  "user_id": 123,
  "room": "lobby",
  "message": "Hello everyone!",
  "name": "Alice"
}
```

**Server → Client** (broadcast or notification):
```json
{
  "message": "Hello everyone!",
  "name": "Alice",
  "timestamp": 1234567890,
  "type": "direct_message"
}
```

### Redis Commands

```bash
# View all users in room "1"
redis-cli HGETALL notifyli:connections:1

# Subscribe to messages
redis-cli SUBSCRIBE notifyli:messages

# Publish test message
redis-cli PUBLISH notifyli:messages '{"user_id":123,"room":"1","message":"Test","from":"CLI"}'

# Monitor all Redis commands (debug mode)
redis-cli MONITOR
```

## Project Structure

```
notifyli/
├── public/
│   ├── index.php          # Example Web UI client
│   ├── main.js            # Client-side WebSocket logic
│   └── main.css           # Styling
├── src/
│   ├── MessagingServer.php              # Main WebSocket server
│   ├── Adapters/
│   │   └── SocketAdapter.php            # Socket handling
│   ├── Clients/
│   │   └── ReusableWebSocketClient.php  # WebSocket client for notification bridge
│   ├── Contracts/
│   │   └── SocketInterface.php          # Interface definitions
│   └── Services/
│       ├── RedisMessageBroker.php       # Redis pub/sub interface
│       ├── RedisStateManager.php        # Connection state tracking
│       ├── RoomManager.php              # Room/channel management
│       └── WebSocketFrameHandler.php    # Protocol handling
├── console/
│   └── send-notification.php   # CLI tool for testing
├── tests/
│   ├── Unit/              # Unit tests
│   └── Integration/       # Integration tests
├── server.php             # Example WebSocket server initialization
├── notification-bridge.php # Example notification bridge implementation
└── composer.json          # PHP dependencies
```

## Architecture Components

### 1. WebSocket Server (server.php)
- Handles persistent connections from clients
- Routes messages between users in same room
- Tracks connections in Redis for notification routing
- Non-blocking I/O for high concurrency

### 2. Notification Bridge (notification-bridge.php)
- Subscribes to Redis pub/sub channel `notifyli:messages`
- Receives notifications from external systems
- Verifies user is connected before forwarding
- Maintains persistent WebSocket connection to server
- Auto-reconnects on connection failure

### 3. Web UI Client (public/index.php)
- Authenticates user with `user_id` and `room`
- Establishes WebSocket connection
- Sends/receives real-time messages
- Displays notifications from external systems
- Persists settings in localStorage

### 4. Redis State Manager
- Stores active user connections
- Key format: `notifyli:connections:{room}` (Hash)
- Fields: `user_{id}` → JSON (name, connected_at, client_id)
- TTL: Removed on disconnect

### 5. Redis Message Broker
- Pub/sub channel: `notifyli:messages`
- Message routing logic
- Connection state queries
- Used by external systems to send notifications

## Performance Characteristics

- **Concurrent Connections**: 1000+ per server (tested)
- **Message Latency**: <10ms (local network)
- **Redis Overhead**: ~1ms per pub/sub message
- **Memory**: ~2-5KB per WebSocket connection
- **Keepalive Interval**: 25 seconds (configurable)
- **Connection Timeout**: 7 days (nginx proxy)

## Security Considerations

- SSL/TLS encryption for WebSocket connections (WSS)
- Deny access to `.env` and sensitive files in nginx
- Run services as `www-data` (limited privileges)
- Supervisor-managed daemons with auto-restart
- Firewall rules (UFW) to restrict access
- Redis should bind to 127.0.0.1 (not exposed publicly)
- Rotate logs to prevent disk exhaustion
- SSL certificates auto-renewed (Let's Encrypt)

## License

This project is licensed under GPL-3.0-or-later.
