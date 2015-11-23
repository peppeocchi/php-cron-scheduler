<?php namespace GO\Job\Tests;

use GO\Job\JobFactory;

class PhpTest extends \PHPUnit_Framework_TestCase
{
  public function testShouldReturnPhpJobInstance()
  {
    $this->assertInstanceOf('Go\Job\Php', JobFactory::factory('GO\Job\Php', 'some command'));
  }

  public function testShouldChangePhpBinOnDemand()
  {
    $bin = '/usr/bin/php';

    $job = JobFactory::factory('GO\Job\Php', 'some command');
    $job->useBin($bin);

    $this->assertEquals($bin, $job->getBin());
  }

  public function testShouldCompileCommand()
  {
    $job = JobFactory::factory('GO\Job\Php', 'somecommand');

    $compiled = $job->build();

    $this->assertEquals(PHP_BINARY . ' somecommand > /dev/null 2>&1 &', $compiled);
  }

  public function testShouldCompileCommandWithCustomBin()
  {
    $bin = '/usr/bin/php';
    $job = JobFactory::factory('GO\Job\Php', 'somecommand');
    $job->useBin($bin);

    $compiled = $job->build();

    $this->assertEquals($bin . ' somecommand > /dev/null 2>&1 &', $compiled);
  }
}
