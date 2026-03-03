<?php

use Neuralpin\Notifyli\MessagingServer;

require __DIR__.'/config/app.php';

$ChatServer = new MessagingServer(
    host: 'localhost',
    location: $_ENV['APP_LOCATION'],
    port: $_ENV['APP_PORT'],
);

//start endless loop, so that our script doesn't stop
while (true) {
	$ChatServer->process();
}

