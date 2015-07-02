<?php require_once __DIR__ . '/../vendor/autoload.php';

use GO\Scheduler;

$scheduler = new Scheduler();

// Schedule command.php to run every minute
$scheduler->schedule(__DIR__.'/command.php', '* * * * *');

// Schedule command.php to run every day at 08:30
$scheduler->schedule(__DIR__.'/command.php', '30 08 * * *');

$scheduler->run();
