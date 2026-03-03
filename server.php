<?php

namespace Chatapp;

require __DIR__.'/config/app.php';

use Chatapp\Shared\LogHelper;
use Chatapp\Socket\ChatServer;

$ChatServer = new ChatServer(
    host: 'localhost',
    location: $_ENV['APP_LOCATION'],
    port: $_ENV['APP_PORT'],
);

LogHelper::$path = __DIR__.'/logs';
LogHelper::log("{$_ENV['APP_LOCATION']}:{$_ENV['APP_PORT']} - Server started");

//start endless loop, so that our script doesn't stop
while (true) {
	$ChatServer->process();
}

