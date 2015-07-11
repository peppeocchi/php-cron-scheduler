<?php namespace GO\Job;

use GO\Job\Job;

class Closure extends Job
{
  protected function build()
  {
    return $this->compiled = $this->command;
  }
}
