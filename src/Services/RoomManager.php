<?php

namespace Neuralpin\Notifyli\Services;

class RoomManager
{
    private array $rooms = [];
    private array $roomsPerSID = [];

    /**
     * Add a client to a room
     */
    public function addClientToRoom(string $room, int $sid): void
    {
        if (!isset($this->rooms[$room][$sid])) {
            $this->rooms[$room][$sid] = $sid;
            $this->roomsPerSID[$sid][] = $room;
        }
    }

    /**
     * Remove a client from all rooms
     */
    public function removeClient(int $sid): void
    {
        if (isset($this->roomsPerSID[$sid])) {
            foreach ($this->roomsPerSID[$sid] as $roomId) {
                unset($this->rooms[$roomId][$sid]);
                    // Clean up empty rooms
                    if (empty($this->rooms[$roomId])) {
                        unset($this->rooms[$roomId]);
                    }
            }
            unset($this->roomsPerSID[$sid]);
        }
    }

    /**
     * Get all clients in a room
     */
    public function getRoomClients(string $room): array
    {
        return $this->rooms[$room] ?? [];
    }

    /**
     * Check if a room exists
     */
    public function roomExists(string $room): bool
    {
        return isset($this->rooms[$room]);
    }

    /**
     * Get the number of clients in a room
     */
    public function getRoomClientCount(string $room): int
    {
        return count($this->rooms[$room] ?? []);
    }

    /**
     * Get all rooms
     */
    public function getAllRooms(): array
    {
        return $this->rooms;
    }

    /**
     * Get all rooms for a specific client
     */
    public function getClientRooms(int $sid): array
    {
        return $this->roomsPerSID[$sid] ?? [];
    }

    /**
     * Check if a client is in a room
     */
    public function isClientInRoom(string $room, int $sid): bool
    {
        return isset($this->rooms[$room][$sid]);
    }
}
