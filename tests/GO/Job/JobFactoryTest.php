<?php namespace GO\Job\Tests;

use GO\Job\JobFactory;

class JobFactoryTest extends \PHPUnit_Framework_TestCase
{
  /**
   * @expectedException GO\Job\Exceptions\InvalidFactoryException
   */
  public function testShouldThrowExceptionIfClassIsInvalid()
  {
    JobFactory::factory('invalid', 'some command');
  }

  public function testFactoryReturnsJobInstance()
  {
    $this->assertInstanceOf('Go\Job\Job', JobFactory::factory('GO\Job\Php', 'some command'));
  }
}
