<?php namespace GO\Job\Tests;

use GO\Scheduler;

class SchedulerTest extends \PHPUnit_Framework_TestCase
{
  public function testShouldLoadConfigOnConstruct()
  {
    $config = ['test' => 'this is a test'];
    $scheduler = new Scheduler($config);

    $this->assertEquals($config, $scheduler->getConfig());
  }

  public function testShouldSwitchConfig()
  {
    $config = ['test' => 'this is a test'];
    $scheduler = new Scheduler();
    $scheduler->useConfig($config);

    $this->assertEquals($config, $scheduler->getConfig());
  }

  public function testShouldHaveNoJobOnConstruct()
  {
    $scheduler = new Scheduler();

    $this->assertEmpty($scheduler->getJobs());
  }

  public function testShouldPushJobs()
  {
    $scheduler = new Scheduler();
    $scheduler->php('some command');

    $this->assertEquals(1, count($scheduler->getJobs()));
  }

  public function testShouldExecuteJobsInBackgroundFirst()
  {
    $closure = function ($nums) {
      return array_sum($nums);
    };
    $scheduler = new Scheduler();
    $scheduler->call($closure)->at('* * * * *');
    $scheduler->raw('echo "raw command"')->at('* * * * *');

    $scheduler->jobsInBackgroundFirst();

    $jobs = $scheduler->getJobs();

    $this->assertTrue($jobs[0]->runInBackground);
  }

  public function testShouldNotExecuteJobsBeforeRun()
  {
    $scheduler = new Scheduler();
    $scheduler->raw('echo "raw command"')->at('* * * * *');
    $scheduler->raw('echo "raw command 2"')->at('* * * 1970 *');

    $this->assertEmpty($scheduler->getExecutedJobs());
  }

  public function testShouldExecuteOnlyDueJobs()
  {
    $scheduler = new Scheduler();
    $scheduler->raw('echo "raw command"')->at('* * * * *');
    $scheduler->raw('echo "raw command 2"')->at('* * * 1970 *');

    $scheduler->run();

    $this->assertEquals(1, count($scheduler->getExecutedJobs()));
  }
}
