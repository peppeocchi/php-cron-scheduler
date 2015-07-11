<?php namespace GO\Job;

use GO\Job\Closure;
use GO\Job\Php;
use GO\Job\Raw;
use GO\Job\Exceptions\InvalidFactoryException;

class JobFactory
{
  private function __construct() {}

  /**
   * Factory method
   *
   * @param string $class
   * @param string $command
   * @param array $args
   * @return instance of GO\Job\Job
   */
  public static function factory($class, $command, array $args = [])
  {
    if (!class_exists($class)) {
      throw new InvalidFactoryException("Class $class doesn't exists");
    }

    return new $class($command, $args);
  }
}
