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

## How it works
Instead of adding a new entry in the crontab for each cronjob you have to run, you can add only one cron job to your crontab and define the commands in your .php file.

Create your `cronjobs.php` file like this
```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GO\Scheduler;

$scheduler = new Scheduler();

// Schedule command.php to run every minute
$scheduler->schedule(__DIR__.'/command.php', '* * * * *');

// Schedule command.php to run every day at 08:30
$scheduler->schedule(__DIR__.'/command.php', '30 08 * * *');

$scheduler->run();
```

Then add to your crontab

````
* * * * * path/to/phpbin path/to/cronjobs.php > /dev/null 2>&1
````

And you are ready to go