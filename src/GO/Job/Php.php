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

    if (count($this->args)) {
      foreach ($this->args as $key => $value) {
        $command .= ' ' . $key . ' ' . $value;
      }
    }

    return $this->compiled = trim($command);
  }

  public function useBin($bin)
  {
    $this->phpbin = file_exists($bin) ? $bin : $this->phpbin;

    return $this;
  }
}
