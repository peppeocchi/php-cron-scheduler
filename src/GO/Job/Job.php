<?php namespace GO\Job;

use GO\Services\Filesystem;

abstract class Job
{
  protected $command;
  protected $args;
  protected $compiled;

  public function __construct($path, array $args = [])
  {
    $fs = new Filesystem($path);
    var_dump($fs); die;
    $this->command = $fs->getCommand();
    var_dump($this->command);
    die;
    $this->args = $args;

    if (method_exists($this, 'init')) {
      $this->init();
    }

    $this->build();
  }

  public function at($expression)
  {
    // Parse expression
    return $this->compiled;
  }

  abstract protected function build();
}
