PHP Cron Scheduler
==

[![Latest Stable Version](https://poser.pugx.org/peppeocchi/php-cron-scheduler/v/stable)](https://packagist.org/packages/peppeocchi/php-cron-scheduler) [![License](https://poser.pugx.org/peppeocchi/php-cron-scheduler/license)](https://packagist.org/packages/peppeocchi/php-cron-scheduler) [![Build Status](https://travis-ci.org/peppeocchi/php-cron-scheduler.svg)](https://travis-ci.org/peppeocchi/php-cron-scheduler) [![Coverage Status](https://coveralls.io/repos/github/peppeocchi/php-cron-scheduler/badge.svg?branch=v2.x)](https://coveralls.io/github/peppeocchi/php-cron-scheduler?branch=v2.x) [![StyleCI](https://styleci.io/repos/38302733/shield)](https://styleci.io/repos/38302733) [![Total Downloads](https://poser.pugx.org/peppeocchi/php-cron-scheduler/downloads)](https://packagist.org/packages/peppeocchi/php-cron-scheduler)

This is a framework agnostic cron jobs scheduler that can be easily integrated with your project or run as a standalone command scheduler.
The idea was originally inspired by the [Laravel Task Scheduling](http://laravel.com/docs/5.1/scheduling).

## Installing via Composer
The recommended way is to install the php-cron-scheduler is through [Composer](https://getcomposer.org/).
Please refer to [Getting Started](https://getcomposer.org/doc/00-intro.md) on how to download and install Composer.

After you have downloaded/installed Composer, run

`php composer.phar require peppeocchi/php-cron-scheduler`

or add the package to your `composer.json`
```json
{
    "require": {
        "peppeocchi/php-cron-scheduler": "2.*"
    }
}
```

## How it works

Create a `scheduler.php` file in the root your project with the following content.
```php
<?php require_once __DIR__.'/vendor/autoload.php';

use GO\Scheduler;

// Create a new scheduler
$scheduler = new Scheduler();

// ... configure the scheduled jobs (see below) ...

// Let the scheduler execute jobs which are due.
$scheduler->run();
```

Then add a new entry to your crontab to run `scheduler.php` every minute.

````
* * * * * path/to/phpbin path/to/scheduler.php 1>> /dev/null 2>&1
````

That's it! Your scheduler is up and running, now you can add your jobs without worring anymore about the crontab.

## Scheduling jobs

By default all your jobs will try to run in background.
PHP scripts and raw commands will run in background by default, while functions will always run in foreground.
You can force a command to run in foreground by calling the `inForeground()` method.
**Jobs that have to send the output to email, will run foreground**.

### Schedule a php script

```php
$scheduler->php('path/to/my/script.php');
```
The `php` method accepts 4 arguments:
- The path to your php script
- The PHP binary to use
- Arguments to be passed to the script
- Identifier
```php
$scheduler->php(
    'path/to/my/script.php', // The script to execute
    'path/to/my/custom/bin/php', // The PHP bin
    [
        '-c' => 'ignore',
        '--merge' => null,
    ],
    'myCustomIdentifier'
);
```

### Schedule a raw command

```php
$scheduler->raw('ps aux | grep httpd');
```
The `raw` method accepts 3 arguments:
- Your command
- Arguments to be passed to the command
- Identifier
```php
$scheduler->raw(
    'mycommand | myOtherCommand',
    [
        '-v' => '6',
        '--silent' => null,
    ],
    'myCustomIdentifier'
);
```

### Schedule a function

```php
$scheduler->call(function () {
    return true;
});
```
The `call` method accepts 3 arguments:
- Your function
- Arguments to be passed to the function
- Identifier
```php
$scheduler->call(
    function ($args) {
        return $args['user'];
    },
    [
        'user' => $user,
    ],
    'myCustomIdentifier'
);
```

### Schedules execution time

There are a few methods to help you set the execution time of your schedules.
If you don't call any of this method, the job will run every minute (* * * * *).

- `at` - This method accepts any expression supported by [mtdowling/cron-expression](https://github.com/mtdowling/cron-expression)
    ```php
    $scheduler->php('script.php')->at('* * * * *');
    ```
- `everyMinute` - Run every minute
    ```php
    $scheduler->php('script.php')->everyMinute();
    ```
- `hourly` - Run once per hour. You can optionally pass the `$minute` you want to run, bu default it will run every hour at minute '00'.
    ```php
    $scheduler->php('script.php')->hourly();
    $scheduler->php('script.php')->hourly(53);
    ```
- `daily` - Run once per day. You can optionally pass `$hour` and `$minute` to have more granular control (or a string `hour:minute`)
    ```php
    $scheduler->php('script.php')->daily();
    $scheduler->php('script.php')->daily(22, 03);
    $scheduler->php('script.php')->daily('22:03');
    ```

### Send output to file/s

You can define one or multiple files where you want the output of your script/command/function execution to be sent to.

```php
$scheduler->php('script.php')->output([
    'my_file1.log', 'my_file2.log'
]);

// The scheduler catches both stdout and function return and send
// those values to the output file
$scheduler->call(function () {
    echo "Hello";

    return " world!";
})->output('my_file.log');
```

### Send output to email/s

You can define one or multiple email addresses where you want the output of your script/command/function execution to be sent to.
In order for the email to be sent, the output of the job needs to be sent first to a file.
In fact, the files will be attached to your email address.
In order for this to work, you need to install [swiftmailer/swiftmailer](https://github.com/swiftmailer/swiftmailer)

```php
$scheduler->php('script.php')->output([
    // If you specify multiple files, both will be attached to the email
    'my_file1.log', 'my_file2.log'
])->email([
    [
        'someemail@mail.com' => 'My custom name'
    ],
    'someotheremail@mail.com'
]);
```

You can optionally customize the `Swift_Mailer` instance with a custom `Swift_Transport`.
You can configure:
- `subject` - The subject of the email sent
- `from` - The email address set as sender
- `body` - The body of the email
- `transport` - The transport to use. For example if you want to use your gmail account or any other SMTP account. The value should be an instance of `Swift_Tranport`

The configuration can be set "globally" for all the scheduler commands, when creating the scheduler.

```php
$scheduler = new Scheduler([
    'email' => [
        'subject' => 'Visitors count',
        'from' => 'cron@email.com',
        'body' => 'This is the daily visitors count',
        'transport' => Swift_SmtpTransport::newInstance('smtp.gmail.com', 465, 'ssl')
                            ->setUsername('username')
                            ->setPassword('password');
    ]
]);
```

Or can be set on a job per job basis.

```php
$scheduler = new Scheduler();

$scheduler->php('myscript.php')->configure([
    'email' => [
        'subject' => 'Visitors count',
    ]
]);

$scheduler->php('my_other_script.php')->configure([
    'email' => [
        'subject' => 'Page views count',
    ]
]);
```

### Schedule conditional execution

Sometimes you might want to execute a schedule not only when the execution is due, but also depending on some other condition.

You can delegate the execution of a cronjob to a truthful test with the method `when`.

```php
$scheduler->php('script.php')->when(function () {
    // The job will run (if due) only when
    // this function returns true
    return true;
});
```

### Schedules execution order

The jobs that are due to run are being ordered by their execution: jobs that can run in **background** will be executed **first**.

### Schedules overlapping

To prevent the execution of a schedule while the previous execution is still in progress, use the method `onlyOne`. To avoid overlapping, the Scheduler needs to create **lock files**.
By default it will be used the directory path used for temporary files.

You can specify a custom directory path globally, when creating a new Scheduler instance.

```php
$scheduler = new Scheduler([
    'tempDir' => 'path/to/my/tmp/dir'
]);

$scheduler->php('script.php')->onlyOne();
```

Or you can define the directory path on a job per job basis.

```php
$scheduler = new Scheduler();

// This will use the default directory path
$scheduler->php('script.php')->onlyOne();

$scheduler->php('script.php')->onlyOne('path/to/my/tmp/dir');
$scheduler->php('other_script.php')->onlyOne('path/to/my/other/tmp/dir');
```

In some cases you might want to run the job also if it's overlapping.
For example if the last execution was more that 5 minutes ago.
You can pass a function as a second parameter, the last execution time will be injected.
The job will not run until this function returns `false`. If it returns `true`, the job will run if overlapping.

```php
$scheduler->php('script.php')->onlyOne(null, function ($lastExecutionTime) {
    return (time() - $lastExecutionTime) > (60 * 5);
});
```

### After job execution

Sometime you might wish to do something after a job runs. The `then` methods provides you the flexibility to do anything you want after the job execution. The output of the job will be injected to this function.
For example you might want to add an entry to you logs, ping a url etc...
By default, the job will be forced to run in foreground (because the output is injected to the function), if you don't need the output, you can pass `true` as a second parameter to allow the execution in background (in this case `$output` will be empty).

```php
$scheduler->php('script.php')->then(function ($output) use ($logger, $messenger) {
    $logger->info($output);

    $messenger->ping('myurl.com', $output);
});

$scheduler->php('script.php')->then(function ($output) {
    log('Job executed!');
}, true);
```
