<?php

/**
 * EXAMPLE: WebSocket Server Initialization
 *
 * This is a reference implementation showing how to initialize and run
 * the WebSocket messaging server. Adapt this to your environment:
 *
 * - Configure host, port, and Redis settings from your .env
 * - Run as a daemon using systemd or supervisor (see README Cloud Deployment)
 * - Monitor logs and restart automatically on failure
 *
 * Production Usage:
 *   systemctl start notifyli-websocket.service
 *
 * Development Usage:
 *   php -q server.php
 */

use Neuralpin\Notifyli\MessagingServer;

require __DIR__.'/config/app.php';

$ChatServer = new MessagingServer(
    host: 'localhost',
    location: $_ENV['APP_LOCATION'],
    port: $_ENV['APP_PORT'],
    redisEnabled: (bool) ($_ENV['REDIS_ENABLED'] ?? false),
    redisHost: $_ENV['REDIS_HOST'] ?? '127.0.0.1',
    redisPort: (int) ($_ENV['REDIS_PORT'] ?? 6379),
    redisPassword: $_ENV['REDIS_PASSWORD'] ?? null,
    redisPrefix: $_ENV['REDIS_PREFIX'] ?? 'notifyli:connections:',
);

//start endless loop, so that our script doesn't stop
while (true) {
	$ChatServer->process();
}

