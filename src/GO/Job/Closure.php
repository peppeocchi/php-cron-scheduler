<?php namespace GO\Job;

use GO\Job\Job;

class Closure extends Job
{
  protected function build()
  {
    $res = call_user_func($this->command, $this->args);
    return $this->compiled = $res;
  }
}
