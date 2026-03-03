<?php

namespace Neuralpin\Notifyli\Services;

use Predis\Client as RedisClient;
use Predis\PredisException;

class RedisStateManager
{
    private ?RedisClient $redis = null;
    private bool $enabled = false;
    private string $prefix;

    public function __construct(
        bool $enabled = false,
        string $host = '127.0.0.1',
        int $port = 6379,
        ?string $password = null,
        string $prefix = 'notifyli:connections:',
    )
    {
        $this->enabled = $enabled;
        $this->prefix = $prefix;

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
            // Redis unavailable, disable it
            $this->redis = null;
            $this->enabled = false;
            echo '[' . date('Y-m-d H:i:s') . "] Redis connection failed: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Register a user connection in Redis
     */
    public function registerConnection(int $clientId, string $room, string $name = ''): bool
    {
        if (!$this->enabled || $this->redis === null) {
            return false;
        }

        try {
            $key = $this->prefix . $room;
            $connectionData = json_encode([
                'client_id' => $clientId,
                'room' => $room,
                'name' => $name,
                'connected_at' => time(),
            ]);

            // Add to room connections set
            $this->redis->hset($key, (string) $clientId, $connectionData);

            return true;
        } catch (PredisException | \Throwable $e) {
            echo '[' . date('Y-m-d H:i:s') . "] Redis register error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Remove a user connection from Redis
     */
    public function removeConnection(int $clientId, string $room): bool
    {
        if (!$this->enabled || $this->redis === null) {
            return false;
        }

        try {
            $key = $this->prefix . $room;
            $this->redis->hdel($key, [(string) $clientId]);

            return true;
        } catch (PredisException | \Throwable $e) {
            echo '[' . date('Y-m-d H:i:s') . "] Redis remove error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Get all active connections in a room
     */
    public function getRoomConnections(string $room): array
    {
        if (!$this->enabled || $this->redis === null) {
            return [];
        }

        try {
            $key = $this->prefix . $room;
            $connections = $this->redis->hgetall($key);

            $result = [];
            foreach ($connections as $clientId => $data) {
                $result[(int) $clientId] = json_decode($data, true);
            }

            return $result;
        } catch (PredisException | \Throwable $e) {
            echo '[' . date('Y-m-d H:i:s') . "] Redis get connections error: " . $e->getMessage() . "\n";
            return [];
        }
    }

    /**
     * Get count of active connections in a room
     */
    public function getRoomConnectionCount(string $room): int
    {
        if (!$this->enabled || $this->redis === null) {
            return 0;
        }

        try {
            $key = $this->prefix . $room;
            return $this->redis->hlen($key);
        } catch (PredisException | \Throwable $e) {
            echo '[' . date('Y-m-d H:i:s') . "] Redis count error: " . $e->getMessage() . "\n";
            return 0;
        }
    }

    /**
     * Clear all connections in a room
     */
    public function clearRoomConnections(string $room): bool
    {
        if (!$this->enabled || $this->redis === null) {
            return false;
        }

        try {
            $key = $this->prefix . $room;
            $this->redis->del($key);

            return true;
        } catch (PredisException | \Throwable $e) {
            echo '[' . date('Y-m-d H:i:s') . "] Redis clear error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Check if Redis is enabled and connected
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->redis !== null;
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
