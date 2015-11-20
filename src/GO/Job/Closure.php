<?php namespace GO\Job;

use GO\Job\Job;

class Closure extends Job
{
  public function build()
  {
    return $this->compile($this->command);
  }

  protected function compile($command)
  {
    $this->runInForeground();
    return $command;
  }
}
