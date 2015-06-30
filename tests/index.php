<?php require_once __DIR__ . '/../vendor/autoload.php';


use GO\Scheduler;

$scheduler = new Scheduler(__DIR__);
$scheduler->schedule(__DIR__.'/command.php', '* * * * *');

$scheduler->run();
