<?php namespace GO\Job;

use GO\Job\Job;

class Raw extends Job
{
  protected function build()
  {
    return $this->compile(trim($this->command));
  }
}
