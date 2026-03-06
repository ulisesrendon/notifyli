<?php

namespace Neuralpin\Notifyli\Services;

use Predis\Client as RedisClient;
use Predis\PredisException;

class RedisMessageBroker
{
    private ?RedisClient $redis = null;
    private bool $enabled = false;
    private string $messageChannel;
    private string $connectionsPrefix;

    public function __construct(
        bool $enabled = false,
        string $host = '127.0.0.1',
        int $port = 6379,
        ?string $password = null,
        string $messageChannel = 'notifyli:messages',
        string $connectionsPrefix = 'notifyli:connections:',
    )
    {
        $this->enabled = $enabled;
        $this->messageChannel = $messageChannel;
        $this->connectionsPrefix = $connectionsPrefix;

        if (!$this->enabled) {
            return;
        }

        try {
            $this->redis = new RedisClient([
                'scheme' => 'tcp',
                'host' => $host,
                'port' => $port,
                'password' => $password,
            ]);

            // Test connection
            $this->redis->ping();
        } catch (PredisException | \Throwable $e) {
            $this->redis = null;
            $this->enabled = false;
            echo '[' . date('Y-m-d H:i:s') . "] Redis connection failed: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Subscribe to message channel and listen for messages
        * Callback receives: ['user_id' => string|int, 'room' => string, 'message' => string, 'from' => string]
     */
    public function subscribe(callable $onMessage): void
    {
        if (!$this->enabled || $this->redis === null) {
            echo '[' . date('Y-m-d H:i:s') . "] Redis message broker not enabled\n";
            return;
        }

        try {
            $pubsub = $this->redis->pubSubLoop();
            $pubsub->subscribe($this->messageChannel);

            echo '[' . date('Y-m-d H:i:s') . "] Subscribed to Redis channel: {$this->messageChannel}\n";

            foreach ($pubsub as $message) {
                // $message is an array with keys: kind, channel, payload
                if ($message['kind'] === 'subscribe') {
                    continue;
                }

                if ($message['kind'] === 'message') {
                    try {
                        $data = json_decode($message['payload'], true);
                        if ($data && isset($data['user_id'])) {
                            // Call the callback with the message data
                            $onMessage($data);
                        }
                    } catch (\Throwable $e) {
                        echo '[' . date('Y-m-d H:i:s') . "] Message processing error: " . $e->getMessage() . "\n";
                    }
                }
            }
        } catch (PredisException | \Throwable $e) {
            echo '[' . date('Y-m-d H:i:s') . "] Redis subscribe error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Post a message to the message channel
     */
    public function publish(string|int $userId, string $room, string $message, string $from = 'system'): bool
    {
        if (!$this->enabled || $this->redis === null) {
            return false;
        }

        try {
            $payload = json_encode([
                'user_id' => (string) $userId,
                'room' => $room,
                'message' => $message,
                'from' => $from,
                'timestamp' => time(),
            ]);

            $subscribers = $this->redis->publish($this->messageChannel, $payload);
            echo '[' . date('Y-m-d H:i:s') . "] 📤 Published to Redis channel '{$this->messageChannel}' - Subscribers: $subscribers\n";
            return true;
        } catch (PredisException | \Throwable $e) {
            echo '[' . date('Y-m-d H:i:s') . "] Redis publish error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Check if a user is connected in a room
     */
    public function isUserConnected(string|int $userId, string $room): bool
    {
        if (!$this->enabled || $this->redis === null) {
            return false;
        }

        try {
            $key = $this->connectionsPrefix . $room;
            return $this->redis->hexists($key, (string) $userId);
        } catch (PredisException | \Throwable $e) {
            echo '[' . date('Y-m-d H:i:s') . "] Redis check connection error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Get user connection info
     */
    public function getUserConnection(string|int $userId, string $room): ?array
    {
        if (!$this->enabled || $this->redis === null) {
            return null;
        }

        try {
            $key = $this->connectionsPrefix . $room;
            $data = $this->redis->hget($key, (string) $userId);

            if ($data) {
                return json_decode($data, true);
            }

            return null;
        } catch (PredisException | \Throwable $e) {
            echo '[' . date('Y-m-d H:i:s') . "] Redis get user error: " . $e->getMessage() . "\n";
            return null;
        }
    }

    /**
     * Check if broker is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->redis !== null;
    }

    /**
     * Get message channel name
     */
    public function getMessageChannel(): string
    {
        return $this->messageChannel;
    }

    /**
     * Close Redis connection
     */
    public function close(): void
    {
        if ($this->redis !== null) {
            try {
                $this->redis->disconnect();
            } catch (\Throwable $e) {
                // Already closed or error
            }
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
