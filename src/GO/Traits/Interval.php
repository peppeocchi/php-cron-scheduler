<?php namespace GO\Traits;

use Cron\CronExpression;
use InvalidArgumentException;

trait Interval
{
    /**
     * Set the Job execution time.
     *
     * @param  string
     * @return this
     */
    public function at($expression)
    {
        $this->executionTime = CronExpression::factory($expression);

        return $this;
    }

    /**
     * Set the execution time to every minute.
     *
     * @return this
     */
    public function everyMinute()
    {
        return $this->at('* * * * *');
    }

    /**
     * Set the execution time to every hour.
     *
     * @param  int\string  $minute
     * @return this
     */
    public function hourly($minute = 0)
    {
        if (! is_numeric($minute) && $minute !== '*') {
            throw new InvalidArgumentException("The minute should be numeric or *");
        }

        return $this->at("{$minute} * * * *");
    }

    /**
     * Set the execution time to every day.
     *
     * @param  int\string  $hour
     * @param  int\string  $minute
     * @return this
     */
    public function daily($hour = 0, $minute = '*')
    {
        if (is_string($hour)) {
            $parts = explode(':', $hour);
            $hour = $parts[0];
            $minute = isset($parts[1]) ? $parts[1] : '*';
        }

        if (! is_numeric($hour) && $hour !== '*') {
            throw new InvalidArgumentException("The hour should be numeric or *");
        }

        if (! is_numeric($minute) && $minute !== '*') {
            throw new InvalidArgumentException("The minute should be numeric or *");
        }

        return $this->at("{$minute} {$hour} * * *");
    }

    // public function weekly()
    // {

    // }

    // public function monthly()
    // {
    //     # code...
    // }

    // public function monthlyAt()
    // {
    //     # code...
    // }

    // public function annually()
    // {
    //     # code...
    // }
}
