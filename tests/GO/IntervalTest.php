<?php namespace GO\Job\Tests;

use GO\Job;
use PHPUnit\Framework\TestCase;

class IntervalTest extends TestCase
{
    public function testShouldRunEveryMinute()
    {
        $job = new Job('ls');

        $this->assertTrue($job->everyMinute()->isDue(\DateTime::createFromFormat('H:i', '00:00')));
    }

    public function testShouldRunHourly()
    {
        $job = new Job('ls');

        // Default run is at minute 00 every hour
        $this->assertTrue($job->hourly()->isDue(\DateTime::createFromFormat('H:i', '10:00')));
        $this->assertFalse($job->hourly()->isDue(\DateTime::createFromFormat('H:i', '10:01')));
        $this->assertTrue($job->hourly()->isDue(\DateTime::createFromFormat('H:i', '11:00')));
    }

    public function testShouldRunHourlyWithCustomInput()
    {
        $job = new Job('ls');

        $this->assertTrue($job->hourly(19)->isDue(\DateTime::createFromFormat('H:i', '10:19')));
        $this->assertTrue($job->hourly('07')->isDue(\DateTime::createFromFormat('H:i', '10:07')));
        $this->assertFalse($job->hourly(19)->isDue(\DateTime::createFromFormat('H:i', '10:01')));
        $this->assertTrue($job->hourly(19)->isDue(\DateTime::createFromFormat('H:i', '11:19')));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testShouldThrowExceptionWithInvalidHourlyMinuteInput()
    {
        $job = new Job('ls');
        $job->hourly('abc');
    }

    public function testShouldRunDaily()
    {
        $job = new Job('ls');

        // Default run is at 00:00 every day
        $this->assertTrue($job->daily()->isDue(\DateTime::createFromFormat('H:i', '00:00')));
    }

    public function testShouldRunDailyWithCustomInput()
    {
        $job = new Job('ls');

        $this->assertTrue($job->daily(19)->isDue(\DateTime::createFromFormat('H:i', '19:00')));
        $this->assertTrue($job->daily(19, 53)->isDue(\DateTime::createFromFormat('H:i', '19:53')));
        $this->assertFalse($job->daily(19)->isDue(\DateTime::createFromFormat('H:i', '18:00')));
        $this->assertFalse($job->daily(19, 53)->isDue(\DateTime::createFromFormat('H:i', '19:52')));

        // A string is also acceptable
        $this->assertTrue($job->daily('19')->isDue(\DateTime::createFromFormat('H:i', '19:00')));
        $this->assertTrue($job->daily('19:53')->isDue(\DateTime::createFromFormat('H:i', '19:53')));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testShouldThrowExceptionWithInvalidDailyHourInput()
    {
        $job = new Job('ls');
        $job->daily('abc');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testShouldThrowExceptionWithInvalidDailyMinuteInput()
    {
        $job = new Job('ls');
        $job->daily(2, 'abc');
    }

    // public function testShouldRunWeekly()
    // {
    //     $job = new Job('ls');

    //     // Default run is at 00:00 every day
    //     $this->assertTrue($job->everyDay()->isDue(\DateTime::createFromFormat('H:i', '00:00')));
    // }

    // public function testShouldRunMonthly()
    // {
    //     $job = new Job('ls');

    //     // Default run is at 00:00 every day
    //     $this->assertTrue($job->everyDay()->isDue(\DateTime::createFromFormat('H:i', '00:00')));
    // }

    // public function testShouldRunYearly()
    // {
    //     $job = new Job('ls');

    //     // Default run is at 00:00 every day
    //     $this->assertTrue($job->everyDay()->isDue(\DateTime::createFromFormat('H:i', '00:00')));
    // }
}
