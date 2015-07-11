<?php namespace GO\Job;

use GO\Job\Job;

class Php extends Job
{
  /**
   * PHP binary
   */
  private $phpbin;

  protected function init()
  {
    $this->phpbin = PHP_BINARY === '' ? '/usr/bin/php' : PHP_BINARY;
  }

  protected function build()
  {
    $command = $this->phpbin . ' ' . $this->command;

    return $this->compile($command);
  }

  public function useBin($bin)
  {
    $this->phpbin = file_exists($bin) ? $bin : $this->phpbin;

    return $this;
  }
}
