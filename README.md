PHP Cron Scheduler
==

This is a simple cron jobs scheduler inspired by the [Laravel Task Scheduling](http://laravel.com/docs/5.1/scheduling).

## Installing via Composer
The raccomended way is to install the php-cron-scheduler is through [Composer](https://getcomposer.org/).

Please refer to [Getting Started](https://getcomposer.org/doc/00-intro.md) on how to download and install Composer.

After you have downloaded/installed Composer, run

`composer.phar require peppeocchi/php-cron-scheduler`

or add the package to your `composer.json`
```json
{
    "require": {
        "peppeocchi/php-cron-scheduler": "dev-master"
    }
}
```

## Config
There are just few things to set in [Scheduler.php](https://github.com/peppeocchi/php-cron-scheduler/blob/master/src/GO/Scheduler.php)
- Timezone - your timezone `$scheduler->setTimezone('Europe/Rome')`, default is `Europe/Dublin`
- For some job you can specify a custom the path to the interpreter
  `$scheduler->php('path/to/my/command')->useBin('path/to/my/php/bin')`, default is `PHP_BINARY`, or `/usr/bin/php` if that constant is empty

## How it works
Instead of adding a new entry in the crontab for each cronjob you have to run, you can add only one cron job to your crontab and define the commands in your .php file.

Create your `scheduler.php` file like this
```php
<?php require_once __DIR__ . '/../vendor/autoload.php';


use GO\Scheduler;


$scheduler = new Scheduler();


/**
 * Schedule cronjob.php to run every minute
 *
 */
$scheduler->php(__DIR__.'/cronjob.php')
  ->at('* * * * *')
  ->output(__DIR__.'/cronjob.log')
  ->email('myemail@server.net');

/**
 * Schedule a raw command to tun every minute between 00 and 04 of every hour,
 * send the output to raw.log
 *
 */
$scheduler->raw('echo "I am a raw command!"')
  ->at('* * * * *')
  ->output(__DIR__.'/raw.log');

/**
 * Run your own function every day at 10:30
 *
 */
$scheduler->call(function () {
    return 'I am a function!';
  })
  ->every()->day('10:30')
  ->output([
    __DIR__.'/callable1.log',
    __DIR__.'/callable2.log',
  ]);

/**
 * Pretty scheduling - run every 25th of month at 00:13
 *
 */
$scheduler->php(__DIR__.'/../tests/cronjob.php')
  ->every()
  ->month('25 00:13')
  ->email(['dev1@server.net' => 'Dev 1', 'dev2@mail.com' => 'Dev 2']);


$scheduler->run();
```

Then add to your crontab

````
* * * * * path/to/phpbin path/to/scheduler.php 1>> /dev/null 2>&1
````

And you are ready to go.
