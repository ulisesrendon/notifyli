<?php

namespace Neuralpin\Notifyli\Adapters;

use Neuralpin\Notifyli\Contracts\SocketInterface;
use Socket;

class SocketAdapter implements SocketInterface
{
    public function create(int $domain, int $type, int $protocol): Socket|false
    {
        return socket_create($domain, $type, $protocol);
    }

    public function setOption(Socket $socket, int $level, int $option, mixed $value): bool
    {
        return socket_set_option($socket, $level, $option, $value);
    }

    public function bind(Socket $socket, string $address, int $port): bool
    {
        return socket_bind($socket, $address, $port);
    }

    public function listen(Socket $socket, int $backlog = 0): bool
    {
        return socket_listen($socket, $backlog);
    }

    public function accept(Socket $socket): Socket|false
    {
        return socket_accept($socket);
    }

    public function select(array &$read, ?array &$write, ?array &$except, ?int $seconds, int $microseconds = 0): int|false
    {
        return socket_select($read, $write, $except, $seconds, $microseconds);
    }

    public function recv(Socket $socket, ?string &$data, int $length, int $flags): int|false
    {
        return socket_recv($socket, $data, $length, $flags);
    }

    public function read(Socket $socket, int $length, int $mode = PHP_BINARY_READ): string|false
    {
        return socket_read($socket, $length, $mode);
    }

    public function write(Socket $socket, string $data, ?int $length = null): int|false
    {
        return socket_write($socket, $data, $length);
    }

    public function close(Socket $socket): void
    {
        socket_close($socket);
    }

    public function getPeerName(Socket $socket, string &$address, ?int &$port = null): bool
    {
        return socket_getpeername($socket, $address, $port);
    }

    public function lastError(?Socket $socket = null): int
    {
        return socket_last_error($socket);
    }

    public function strError(int $errorCode): string
    {
        return socket_strerror($errorCode);
    }
}
