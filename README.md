PHP Cron Scheduler
==

[![Latest Stable Version](https://poser.pugx.org/peppeocchi/php-cron-scheduler/v/stable)](https://packagist.org/packages/peppeocchi/php-cron-scheduler) [![Total Downloads](https://poser.pugx.org/peppeocchi/php-cron-scheduler/downloads)](https://packagist.org/packages/peppeocchi/php-cron-scheduler) [![Latest Unstable Version](https://poser.pugx.org/peppeocchi/php-cron-scheduler/v/unstable)](https://packagist.org/packages/peppeocchi/php-cron-scheduler) [![License](https://poser.pugx.org/peppeocchi/php-cron-scheduler/license)](https://packagist.org/packages/peppeocchi/php-cron-scheduler)

This is a simple cron jobs scheduler inspired by the [Laravel Task Scheduling](http://laravel.com/docs/5.1/scheduling).

## Installing via Composer
The raccomended way is to install the php-cron-scheduler is through [Composer](https://getcomposer.org/).

Please refer to [Getting Started](https://getcomposer.org/doc/00-intro.md) on how to download and install Composer.

After you have downloaded/installed Composer, run

`php composer.phar require peppeocchi/php-cron-scheduler`

or add the package to your `composer.json`
```json
{
    "require": {
        "peppeocchi/php-cron-scheduler": "dev-master"
    }
}
```

## How it works
Instead of adding a new entry in the crontab for each cronjob you have to run, you can add only one cron job to your crontab and define the commands in your .php file.

By default when you schedule a command it will run in background, you can overwrite that behavior by calling `->runInForeground()` method.
```php
$scheduler->call('myFunction')->runInForeground()->every()->minute();
```

**Jobs that should send the output to email/s are always set to run in foreground**

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
 * Pass `true` as a second parameter to append the output to that file
 *
 */
$scheduler->raw('echo "I am a raw command!"')
  ->at('* * * * *')
  ->output(__DIR__.'/raw.log', true);

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

### Config
You can pass to the Scheduler constructor an array with your global config for the jobs

The only supported configuration until now is the sender email address when sending the result of a job execution

```php
...
$config = [
  'emailFrom' => 'myEmail@address.com'
];

$scheduler = new Scheduler($config);
...
```

You can also switch configuration on a per job basis or for a group of jobs

```php
...
$config1 = [...];
$config2 = [...];
$scheduler = new Scheduler();

$scheduler->useConfig($config1)->php(...)....

$scheduler->useConfig($config2);

$scheduler->raw(...)....
$scheduler->call(...)....

$scheduler->useConfig($config1);
...
```

### Jobs execution order
The jobs that are due to run are being ordered by their execution: jobs that can run in **background** will be executed **first**


### Job types
After creating a new `Scheduler` instance, you can add few type of jobs
- `->php('myCommand')`, execute a `PHP` job. If you need you can set your own `PHP_BINARY`
```php
$scheduler->php('myCommand')->useBin('myBin')
```
- `->raw('myCommand')`, execute a raw command in the shell, you can use this type if you want to pipe several commands like `ps aux | grep memcached`
- `->call('myFunction')`, execute your own function
- you can optionally write your own interpreter (if you want you can do a PR to add the interpreter to this repo), just extend `GO\Job\Job` and define the `build()` method, and an optional `init()` if it requires to be initiated before running the command - eg. to define a bin path

### Schedule time
`Scheduler` uses `Cron\CronExpression` as an expression parser.

So you can schedule the job using the `->at('myCronExpression')` method and passing to that your cron expression (eg. `* * * * *`) or one of the expression supported by [mtdowling/cron-expression](https://github.com/mtdowling/cron-expression)

Optionally you can use the "pretty scheduling" that lets you define times in an eloquent way. To do that you should call the `->every()` followed by
- `->minute()`, the job will be scheduled to run every minute
- `->hour('02')` the job will be scheduled to run every hour. Default `minute` is `00` but you can override that with your own `minute` (in the example it will run every hour at minute `02`)
- `->day('10:23')` the job will be scheduled to run every day. Default `hour:minute` is `00:00` but you can override that with your own `hour:minute`
- `->month('25 10:30')` the job will be scheduled to run every month. Default `day hour:minute` is `01 00:00` but you can override that with your own `day hour:minute`
