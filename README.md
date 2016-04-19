PHP Cron Scheduler
==

[![Build Status](https://travis-ci.org/peppeocchi/php-cron-scheduler.svg)](https://travis-ci.org/peppeocchi/php-cron-scheduler)
[![Latest Stable Version](https://poser.pugx.org/peppeocchi/php-cron-scheduler/v/stable)](https://packagist.org/packages/peppeocchi/php-cron-scheduler) [![Total Downloads](https://poser.pugx.org/peppeocchi/php-cron-scheduler/downloads)](https://packagist.org/packages/peppeocchi/php-cron-scheduler) [![Latest Unstable Version](https://poser.pugx.org/peppeocchi/php-cron-scheduler/v/unstable)](https://packagist.org/packages/peppeocchi/php-cron-scheduler) [![License](https://poser.pugx.org/peppeocchi/php-cron-scheduler/license)](https://packagist.org/packages/peppeocchi/php-cron-scheduler)

This is a simple cron jobs scheduler inspired by the [Laravel Task Scheduling](http://laravel.com/docs/5.1/scheduling).

## Installing via Composer
The recommended way is to install the php-cron-scheduler is through [Composer](https://getcomposer.org/).

Please refer to [Getting Started](https://getcomposer.org/doc/00-intro.md) on how to download and install Composer.

After you have downloaded/installed Composer, run

`php composer.phar require peppeocchi/php-cron-scheduler`

or add the package to your `composer.json`
```json
{
    "require": {
        "peppeocchi/php-cron-scheduler": "1.*"
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
<?php require_once __DIR__.'/../vendor/autoload.php';

use GO\Scheduler;

function myFunc() {
  return "Hello world from function!";
}

$scheduler = new Scheduler([
  'emailFrom' => 'myemail@address.from'
]);


/**
 * Schedule cronjob.php to run every minute
 *
 */
$scheduler->php(__DIR__.'/cronjob.php')->at('* * * * *')->output(__DIR__.'/cronjob.log');


/**
 * Schedule a php job to run with your bin
 *
 */
$scheduler->php(__DIR__.'/cronjob.php')->useBin('/usr/bin/php')->at('* * * * *')->output(__DIR__.'/cronjob_bin.log', true);


/**
 * Schedule a raw command to tun every minute between 00 and 04 of every hour,
 * send the output to raw.log
 * Pass `true` as a second parameter to append the output to that file
 *
 */
$scheduler->raw('ps aux | grep httpd')->at('* * * * *')->output(__DIR__.'/raw.log', true);


/**
 * Run your own function every day at 10:30
 *
 */
$scheduler->call('myFunc')->every()->day('10:30')->output(__DIR__.'/call.log');

$scheduler->call(function () {
    return "This works the same way";
  })->at('* * * * *')->output(__DIR__.'/call.log');

/**
 * Run only when your func returns true
 *
 */
$scheduler->php(__DIR__.'/cronjob.php')
  ->at('* * * * *')
  ->when(function () {
    return false;
  })->output(__DIR__.'/never_created.log');

/**
 * Send the output to an email address
 *
 */
$scheduler->call(function () {
    return "This will be sent via email";
  })->at('* * * * *')->output(__DIR__.'/call.log')->email('myemail@address.to');

$scheduler->run();
```

Then add to your crontab

````
* * * * * path/to/phpbin path/to/scheduler.php 1>> /dev/null 2>&1
````

And you are ready to go.

### Config
You can pass to the Scheduler constructor an array with your global config for the jobs

- Set the sender email address when sending the result of a job execution

```php
...
$config = [
  'emailFrom' => 'myEmail@address.com'
];

$scheduler = new Scheduler($config);
...
```

- Set the path to a directory for temporary files (job locks, to prevent job overlapping). If the path does not exists or is not writable, an exception will be thrown straight away.

```php
$scheduler = new Scheduler([
  'tempDir' => 'my/custom/temp/dir'
])
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

### Output
You can send the output of the execution of your cron job either to a file and an email address.
- `->output('myfile')` will overwrite that file if exists
- `->output('myfile', true)` will append to that file (if exists)
If you want to send the output to an email address, you need to send first the output to a file. That file will be attached to the email
- `->output('myfile')->email('myemail')`
You can pass an array of files or emails if you want to send the output to multiple files/emails
-> `output(['first_file', 'second_file'])->email(['myemail1' => 'Dev1', 'myemail2' => 'Dev2'])`

### Advanced logging
Additionally to the file or email output, you can use a PSR-3 compatible Logger (e.g. Monolog) to handle the job output.

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GO\Scheduler;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$log = new Logger('logger-name');
$log->pushHandler(new StreamHandler('/tmp/log.log', Logger::INFO));

$scheduler
    ->call(function () {
        return "just output something";
    })
    ->at('* * * * *')
    ->setLogger($log);

$scheduler->run();
```

The Scheduler will use the INFO level for logging the output.

#### More logging options
- `->setLabel('my-log-label')` will log the job output using this label
- `->setJobDoneMessage('job xyz done!')` will add an additional message that will be logged when the job is done. This can be useful if you want to track that a job is executed, even though it does not output anything by itself.  

### Conditional
You can delegate the execution of a cronjob to a truthful test.
```
$scheduler->raw('command')->when(function () {
    .....
    return true;
  });
```

### Job overlapping
The `doNotOverlap()` function prevents job overlapping.
```
$scheduler->raw('command')->at('* * * * *')->doNotOverlap();
```
This will prevent the execution of the job if the same job is already being executed.

The function accepts a callback that lets you decide if a job execution is allowed to overlap.
The callback will receive the unix timestamp of when the current running job started. If your callback returns a negative value, the new job will be executed despite the current running job.
```
$scheduler->raw('command')->at('* * * * *')->doNotOverlap(function ($lastExecutionTime) {
  // Allow overlapping jobs if last execution was 5 minutes ago
  return time() - $filetime < 300;
});
```

This functionality by default will create a temp file on the sys temp directory. You can set your own temp directory path when creating a new scheduler instance, passing the `tempDir` config to the constructor.

### Schedule time
`Scheduler` uses `Cron\CronExpression` as an expression parser.

So you can schedule the job using the `->at('myCronExpression')` method and passing to that your cron expression (eg. `* * * * *`) or one of the expression supported by [mtdowling/cron-expression](https://github.com/mtdowling/cron-expression)

Optionally you can use the "pretty scheduling" that lets you define times in an eloquent way. To do that you should call the `->every()` followed by
- `->minute()`, the job will be scheduled to run every minute
- `->hour('02')` the job will be scheduled to run every hour. Default `minute` is `00` but you can override that with your own `minute` (in the example it will run every hour at minute `02`)
- `->day('10:23')` the job will be scheduled to run every day. Default `hour:minute` is `00:00` but you can override that with your own `hour:minute`
- `->month('25 10:30')` the job will be scheduled to run every month. Default `day hour:minute` is `01 00:00` but you can override that with your own `day hour:minute`
