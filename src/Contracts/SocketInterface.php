<?php

namespace Neuralpin\Notifyli\Contracts;

use Socket;

interface SocketInterface
{
    public function create(int $domain, int $type, int $protocol): Socket|false;

    public function setOption(Socket $socket, int $level, int $option, mixed $value): bool;

    public function bind(Socket $socket, string $address, int $port): bool;

    public function listen(Socket $socket, int $backlog = 0): bool;

    public function accept(Socket $socket): Socket|false;

    public function select(array &$read, ?array &$write, ?array &$except, ?int $seconds, int $microseconds = 0): int|false;

    public function recv(Socket $socket, ?string &$data, int $length, int $flags): int|false;

    public function read(Socket $socket, int $length, int $mode = PHP_BINARY_READ): string|false;

    public function write(Socket $socket, string $data, ?int $length = null): int|false;

    public function close(Socket $socket): void;

    public function getPeerName(Socket $socket, string &$address, ?int &$port = null): bool;

    public function lastError(?Socket $socket = null): int;

    public function strError(int $errorCode): string;
}
