<?php

use Neuralpin\Notifyli\Services\RoomManager;

describe('RoomManager', function () {
    beforeEach(function () {
        $this->manager = new RoomManager();
    });

    describe('addClientToRoom', function () {
        it('adds a client to a room', function () {
            $this->manager->addClientToRoom('room1', 1);

            expect($this->manager->isClientInRoom('room1', 1))->toBeTrue();
            expect($this->manager->getRoomClientCount('room1'))->toBe(1);
        });

        it('adds multiple clients to the same room', function () {
            $this->manager->addClientToRoom('room1', 1);
            $this->manager->addClientToRoom('room1', 2);
            $this->manager->addClientToRoom('room1', 3);

            expect($this->manager->getRoomClientCount('room1'))->toBe(3);
        });

        it('adds a client to multiple rooms', function () {
            $this->manager->addClientToRoom('room1', 1);
            $this->manager->addClientToRoom('room2', 1);
            $this->manager->addClientToRoom('room3', 1);

            $clientRooms = $this->manager->getClientRooms(1);
            expect($clientRooms)->toHaveCount(3);
            expect($clientRooms)->toContain('room1');
            expect($clientRooms)->toContain('room2');
            expect($clientRooms)->toContain('room3');
        });

        it('does not add duplicate client to same room', function () {
            $this->manager->addClientToRoom('room1', 1);
            $this->manager->addClientToRoom('room1', 1);

            expect($this->manager->getRoomClientCount('room1'))->toBe(1);
        });
    });

    describe('removeClient', function () {
        it('removes a client from all rooms', function () {
            $this->manager->addClientToRoom('room1', 1);
            $this->manager->addClientToRoom('room2', 1);

            $this->manager->removeClient(1);

            expect($this->manager->isClientInRoom('room1', 1))->toBeFalse();
            expect($this->manager->isClientInRoom('room2', 1))->toBeFalse();
            expect($this->manager->getClientRooms(1))->toBe([]);
        });

        it('does not affect other clients when removing one', function () {
            $this->manager->addClientToRoom('room1', 1);
            $this->manager->addClientToRoom('room1', 2);

            $this->manager->removeClient(1);

            expect($this->manager->getRoomClientCount('room1'))->toBe(1);
            expect($this->manager->isClientInRoom('room1', 2))->toBeTrue();
        });

        it('handles removing non-existent client gracefully', function () {
            $this->manager->removeClient(999);

            expect($this->manager->getClientRooms(999))->toBe([]);
        });
    });

    describe('getRoomClients', function () {
        it('returns all clients in a room', function () {
            $this->manager->addClientToRoom('room1', 1);
            $this->manager->addClientToRoom('room1', 2);
            $this->manager->addClientToRoom('room1', 3);

            $clients = $this->manager->getRoomClients('room1');

            expect($clients)->toHaveCount(3);
            expect($clients)->toContain(1);
            expect($clients)->toContain(2);
            expect($clients)->toContain(3);
        });

        it('returns empty array for non-existent room', function () {
            $clients = $this->manager->getRoomClients('nonexistent');

            expect($clients)->toBe([]);
        });

        it('returns empty array after all clients removed', function () {
            $this->manager->addClientToRoom('room1', 1);
            $this->manager->removeClient(1);

            $clients = $this->manager->getRoomClients('room1');

            expect($clients)->toBe([]);
        });
    });

    describe('roomExists', function () {
        it('returns true when room has clients', function () {
            $this->manager->addClientToRoom('room1', 1);

            expect($this->manager->roomExists('room1'))->toBeTrue();
        });

        it('returns false for non-existent room', function () {
            expect($this->manager->roomExists('nonexistent'))->toBeFalse();
        });

        it('returns false after last client removed', function () {
            $this->manager->addClientToRoom('room1', 1);
            $this->manager->removeClient(1);

            expect($this->manager->roomExists('room1'))->toBeFalse();
        });
    });

    describe('getRoomClientCount', function () {
        it('returns correct count', function () {
            $this->manager->addClientToRoom('room1', 1);
            $this->manager->addClientToRoom('room1', 2);

            expect($this->manager->getRoomClientCount('room1'))->toBe(2);
        });

        it('returns 0 for empty room', function () {
            expect($this->manager->getRoomClientCount('nonexistent'))->toBe(0);
        });

        it('updates count when clients are removed', function () {
            $this->manager->addClientToRoom('room1', 1);
            $this->manager->addClientToRoom('room1', 2);
            $this->manager->removeClient(1);

            expect($this->manager->getRoomClientCount('room1'))->toBe(1);
        });
    });

    describe('getAllRooms', function () {
        it('returns all rooms with their clients', function () {
            $this->manager->addClientToRoom('room1', 1);
            $this->manager->addClientToRoom('room1', 2);
            $this->manager->addClientToRoom('room2', 3);

            $allRooms = $this->manager->getAllRooms();

            expect($allRooms)->toHaveKey('room1');
            expect($allRooms)->toHaveKey('room2');
            expect($allRooms['room1'])->toHaveCount(2);
            expect($allRooms['room2'])->toHaveCount(1);
        });

        it('returns empty array when no rooms exist', function () {
            expect($this->manager->getAllRooms())->toBe([]);
        });
    });

    describe('getClientRooms', function () {
        it('returns all rooms a client is in', function () {
            $this->manager->addClientToRoom('room1', 1);
            $this->manager->addClientToRoom('room2', 1);

            $rooms = $this->manager->getClientRooms(1);

            expect($rooms)->toHaveCount(2);
            expect($rooms)->toContain('room1');
            expect($rooms)->toContain('room2');
        });

        it('returns empty array for client not in any room', function () {
            expect($this->manager->getClientRooms(999))->toBe([]);
        });
    });

    describe('isClientInRoom', function () {
        it('returns true when client is in room', function () {
            $this->manager->addClientToRoom('room1', 1);

            expect($this->manager->isClientInRoom('room1', 1))->toBeTrue();
        });

        it('returns false when client is not in room', function () {
            $this->manager->addClientToRoom('room1', 1);

            expect($this->manager->isClientInRoom('room1', 2))->toBeFalse();
        });

        it('returns false for non-existent room', function () {
            expect($this->manager->isClientInRoom('nonexistent', 1))->toBeFalse();
        });
    });

    describe('complex scenarios', function () {
        it('handles multiple clients in multiple rooms', function () {
            // Client 1 in room1 and room2
            $this->manager->addClientToRoom('room1', 1);
            $this->manager->addClientToRoom('room2', 1);

            // Client 2 in room2 and room3
            $this->manager->addClientToRoom('room2', 2);
            $this->manager->addClientToRoom('room3', 2);

            // Client 3 in all rooms
            $this->manager->addClientToRoom('room1', 3);
            $this->manager->addClientToRoom('room2', 3);
            $this->manager->addClientToRoom('room3', 3);

            expect($this->manager->getRoomClientCount('room1'))->toBe(2);
            expect($this->manager->getRoomClientCount('room2'))->toBe(3);
            expect($this->manager->getRoomClientCount('room3'))->toBe(2);

            // Remove client 3
            $this->manager->removeClient(3);

            expect($this->manager->getRoomClientCount('room1'))->toBe(1);
            expect($this->manager->getRoomClientCount('room2'))->toBe(2);
            expect($this->manager->getRoomClientCount('room3'))->toBe(1);
        });
    });
});
