<?php namespace GO\Job\Tests;

use GO\Job\JobFactory;

class JobTest extends \PHPUnit_Framework_TestCase
{
  public function testShouldReturnIntervalInstance()
  {
    $job = JobFactory::factory('GO\Job\Php', 'some command')->every();
    $this->assertInstanceOf('Go\Services\Interval', $job);
  }

  public function testShouldSetFilesOutput()
  {
    $files = ['file1', 'file2'];

    $job = JobFactory::factory('GO\Job\Php', 'some command')->output($files);

    $this->assertEquals($files, $job->getFilesOutput());
  }

  public function testShouldSetEmailsOutput()
  {
    $emails = ['email1', 'email2'];

    $job = JobFactory::factory('GO\Job\Php', 'some command')->email($emails);

    $this->assertEquals($emails, $job->getEmailsOutput());
  }

  public function testShouldRecogniseDueJobs()
  {
    $job = JobFactory::factory('GO\Job\Php', 'some command')->at('* * * * *');

    $this->assertTrue($job->isDue());
  }

  public function testShouldRecogniseNonDueJobs()
  {
    $job = JobFactory::factory('GO\Job\Php', 'some command')->at('* * * 1970 *');

    $this->assertFalse($job->isDue());
  }

  public function testShouldRunInBackground()
  {
    $job = JobFactory::factory('GO\Job\Php', 'some command')->output(['file1']);

    $this->assertTrue($job->runInBackground);
  }

  public function testShouldRunInForeground()
  {
    $job = JobFactory::factory('GO\Job\Php', 'some command')->email(['email1']);

    $this->assertFalse($job->runInBackground);
  }

  public function testShouldAppendArgs()
  {
    $args = [
      '--arg1' => 'value_arg 1',
      '--arg2' => 'value_arg 2',
    ];
    $job = JobFactory::factory('GO\Job\Raw', 'somecommand', $args);

    $this->assertEquals('somecommand --arg1 "value_arg 1" --arg2 "value_arg 2" > /dev/null 2>&1 &', $job->build());
  }

  public function testShouldExecuteOnlyOnTruthTestTrue()
  {
    $job = JobFactory::factory('GO\Job\Raw', 'somecommand')->at('* * * * *')->when(function () {
      return true;
    });

    $this->assertTrue($job->isDue());
  }

  public function testShouldNotExecuteIfTruthTestFalse()
  {
    $job = JobFactory::factory('GO\Job\Raw', 'somecommand')->at('* * * * *')->when(function () {
      return false;
    });

    $this->assertFalse($job->isDue());
  }

  /**
   * @expectedException TypeError
   */
  public function testShouldAcceptOnlyCallbackWithDoNotOverlap()
  {
    $job = JobFactory::factory('GO\Job\Php', 'some command')->at('* * * * *')->doNotOverlap([1,2]);
  }

  public function testShouldSetCallbackWithDoNotOverlap()
  {
    $job = JobFactory::factory('GO\Job\Php', 'some command')->at('* * * * *')
      ->doNotOverlap(function ($lastExec) {
        return $lastExec < 1000;
      });

    $this->assertTrue($job->preventOverlap() !== false && $job->preventOverlap() instanceof \Closure);
  }

  public function testShouldSetTrueIfNoCallbackProvidedWithDoNotOverlap()
  {
    $job = JobFactory::factory('GO\Job\Php', 'some command')->at('* * * * *')
      ->doNotOverlap();

    $this->assertTrue($job->preventOverlap());
  }
}
