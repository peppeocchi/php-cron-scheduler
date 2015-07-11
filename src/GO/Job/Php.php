<?php namespace GO\Job;

use GO\Job\Job;

class Php extends Job
{
  /**
   * PHP binary
   *
   * @var string
   */
  private $phpbin;

  /**
   * Child constructor
   * Sets the PHP binary
   *
   * @return void
   */
  protected function init()
  {
    $this->phpbin = PHP_BINARY === '' ? '/usr/bin/php' : PHP_BINARY;
  }

  /**
   * Build the command
   *
   * @return void
   */
  protected function build()
  {
    $command = $this->phpbin . ' ' . $this->command;

    return $this->compile($command);
  }

  /**
   * Change PHP binary path
   *
   * @param string $bin
   * @return $this
   */
  public function useBin($bin)
  {
    $this->phpbin = file_exists($bin) ? $bin : $this->phpbin;

    return $this;
  }
}
