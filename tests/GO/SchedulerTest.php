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

  /**
   * @expectedException InvalidArgumentException
   */
  public function testShouldThrowExceptionIfTempDirNotExists()
  {
    $scheduler = new Scheduler([
      'tempDir' => 'someinvalid/path'
    ]);
  }

  /**
   * @expectedException InvalidArgumentException
   */
  public function testShouldThrowExceptionIfTempDirIsNotWritable()
  {
    $tempDir = __DIR__ . '/../non_writable/';
    chmod($tempDir, 0555);

    $scheduler = new Scheduler([
      'tempDir' => $tempDir
    ]);
  }

  public function testShouldOverlapIfCallbackReturnsFalse()
  {
    $scheduler = new Scheduler([
      'tempDir' => __DIR__ . '/../tmp'
    ]);

    $script = __DIR__.'/../test_overlap.php';

    $job = $scheduler->php($script)->at('* * * * *')->doNotOverlap(function() {
      return false;
    });

    $scheduler->run();

    $this->assertEquals(1, count($scheduler->getExecutedJobs()));
  }

  public function testShouldRemoveLockFileAfterRun()
  {
    $scheduler = new Scheduler([
      'tempDir' => __DIR__ . '/../tmp'
    ]);

    $script = __DIR__.'/../test_overlap.php';

    $job = $scheduler->php($script)->at('* * * * *')->doNotOverlap();

    $scheduler->run();
    $expectedFile = implode('/', [$scheduler->getTempDir(), md5($job->getCommand()) . '.lock']);

    $this->assertTrue(! file_exists($expectedFile));
  }

  public function testShouldNotExecuteJobIfLockFileExists()
  {
    $scheduler = new Scheduler([
      'tempDir' => __DIR__ . '/../tmp'
    ]);

    $script = __DIR__.'/../test_overlap.php';

    $job = $scheduler->php($script)->at('* * * * *')->doNotOverlap();

    $path = implode('/', [$scheduler->getTempDir(), md5($job->getCommand()) . '.lock']);
    touch($path);

    $scheduler->run();

    $this->assertEquals(0, count($scheduler->getExecutedJobs()));

    unlink($path);
  }

  public function testVerboseLockFileShouldWriteCommandToLockFile()
  {
      $scheduler = new Scheduler([
          'tempDir' => __DIR__ . '/../tmp',
          'verboseLockFile' => true
      ]);

      $commandId = 'FooBar';
      $path = implode('/', [$scheduler->getTempDir(), md5($commandId) . '.lock']);

      $scheduler->call(function() use ($commandId, $path){
              $lockFileContent = trim(file_get_contents($path));
              $this->assertEquals($commandId, $lockFileContent);
          }, [], $commandId)
          ->at('* * * * *')
          ->doNotOverlap();

      try
      {
          $scheduler->run();
      }
      catch(\PHPUnit_Framework_AssertionFailedError $e){
          throw $e;
      }
      finally
      {
          unlink($path);
      }
  }
}
