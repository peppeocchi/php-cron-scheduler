<?php require_once __DIR__ . '/../vendor/autoload.php';

use GO\Scheduler;

$scheduler = new Scheduler();

// Schedule cronjob.php to run every minute
$scheduler->php(__DIR__.'/../tests/cronjob.php')->at('* * * * *');

// Schedule a raw command to tun every minute between 00 and 04 of every hour, send the output to raw.log
$scheduler->raw('echo "I am a raw command!"')
  ->at('00-04 * * * *')
  ->output(__DIR__.'/../tests/raw.log');

// Schedule a command and send output to cronjob.log - append to the existing file
$scheduler->php(__DIR__.'/../tests/cronjob.php')
  ->at('* * * * *')
  ->output(__DIR__.'/../tests/cronjob.log', true);

// Send output to multiple files
$scheduler->php(__DIR__.'/../tests/cronjob.php')
  ->at('* * * * *')
  ->output([
    __DIR__.'/../tests/cronjob.log',
    __DIR__.'/../tests/my_other.log',
  ], true);

// Send output to file and to email
$scheduler->php(__DIR__.'/../tests/cronjob.php')
  ->at('* * * * *')
  ->output(__DIR__.'/../tests/cronjob.log', true)
  ->email('my@cool.email');

// Send output to multiple emails
$scheduler->php(__DIR__.'/../tests/cronjob.php')
  ->at('* * * * *')
  ->email(['my@cool.email', 'my@othercool.email']);

// Pretty scheduling - run every day at 10:30
$scheduler->php(__DIR__.'/../tests/cronjob.php')
  ->every()
  ->day('10:30');

// Pretty scheduling - run every 25th of month at 00:13
$scheduler->php(__DIR__.'/../tests/cronjob.php')
  ->every()
  ->month('25 00:13');

$scheduler->run();
