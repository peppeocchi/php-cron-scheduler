<?php namespace GO\Job\Tests;

use GO\Job\JobFactory;

class ClosureTest extends \PHPUnit_Framework_TestCase
{
  public function testShouldReturnClosureJobInstance()
  {
    $this->assertInstanceOf('Go\Job\Closure', JobFactory::factory('GO\Job\Closure', 'some command'));
  }

  public function testShouldInjectArgs()
  {
    $closure = function ($nums) {
      return array_sum($nums);
    };
    $args = [2, 3];
    $job = JobFactory::factory('GO\Job\Closure', $closure, $args);

    $this->assertEquals(5, $job->exec());
  }

  public function testShouldRunInForeground()
  {
    $closure = function ($nums) {
      return array_sum($nums);
    };
    $args = [2, 3];
    $job = JobFactory::factory('GO\Job\Closure', $closure, $args);

    $job->build();

    $this->assertFalse($job->runInBackground);
  }
}
