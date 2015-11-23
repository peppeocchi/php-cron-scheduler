<?php namespace GO\Job\Tests;

use GO\Job\JobFactory;

class RawTest extends \PHPUnit_Framework_TestCase
{
  public function testShouldReturnRawJobInstance()
  {
    $this->assertInstanceOf('Go\Job\Raw', JobFactory::factory('GO\Job\Raw', 'some command'));
  }
}
