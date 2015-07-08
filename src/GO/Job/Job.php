<?php namespace GO\Job;

use GO\Services\Filesystem;

abstract class Job
{
  protected $command;
  protected $args;
  protected $compiled;

  private $fs;

  private $outputs = [];
  private $emails = [];

  public $due = false;

  public function __construct($job, array $args = [])
  {
    if (is_callable($job)) {
      $this->command = $job;
    } else {
      $this->fs = new Filesystem($job);
      $this->command = $this->fs->getCommand();
    }

    $this->args = $args;

    if (method_exists($this, 'init')) {
      $this->init();
    }

    $this->build();
  }

  public function at($expression)
  {
    // Parse expression
    if ($this->isDue()) {
      $this->due = true;
    }

    return $this;
  }

  public function output($output)
  {
    $this->outputs = is_array($output) ? $output : [$output];

    return $this;
  }

  public function email($email)
  {
    $this->emails = is_array($email) ? $email : [$email];

    return $this;
  }

  protected function isDue()
  {
    return rand() % 2 == 0;
  }

  abstract protected function build();

  public function exec()
  {
    $return = 'Executing ' . $this->compiled;

    foreach ($this->outputs as $output) {
      $this->fs->write($return, $output);
    }

    foreach ($this->emails as $email) {
      echo 'Sending email to ' . $email;
    }
    return $return;
  }
}
