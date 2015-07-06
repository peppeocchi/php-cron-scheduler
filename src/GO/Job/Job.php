<?php namespace GO\Job;

abstract class Job
{
  protected $command;
  protected $args;
  protected $compiled;

  public function __construct($command, array $args = [])
  {
    $this->command = $command;
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
