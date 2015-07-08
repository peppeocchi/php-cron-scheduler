<?php namespace GO\Job;

use GO\Job\Exceptions\InvalidFactoryException;

use GO\Job\Closure;
use GO\Job\Php;
use GO\Job\Raw;

class JobFactory
{
  private function __construct() {}

  public static function factory($class, $command, $args)
  {
    if (!class_exists($class)) {
      throw new InvalidFactoryException("Class $class doesn't exists");
    }

    return new $class($command, $args);
  }
}
