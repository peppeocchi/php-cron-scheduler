<?php namespace GO\Job;

use GO\Services\Filesystem;
use GO\Services\Interval;
use GO\Services\TimeParser;

abstract class Job
{
  protected $command;
  protected $args;
  protected $compiled;
  protected $time;

  private $fs;

  private $outputs = [];
  private $emails = [];

  public $due = false;

  public function __construct($job, array $args = [])
  {
    $this->time = time();

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
    $execution = new TimeParser($expression);

    if ($execution->isDue()) {
      $this->due = true;
    }

    return $this;
  }

  /**
   * @param [int] $interval - 
   */
  public function every($interval = '*')
  {
    return new Interval($this, $interval);
  }

  public function output($output, $mode = false)
  {
    $this->outputs = is_array($output) ? $output : [$output];

    $this->mode = $mode === true ? 'a' : 'w';

    return $this;
  }

  public function email($email)
  {
    $this->emails = is_array($email) ? $email : [$email];

    return $this;
  }

  public function isDue()
  {
    return $this->due;
  }

  abstract protected function build();

  public function exec()
  {
    $return = exec($this->compiled);

    foreach ($this->outputs as $output) {
      $this->fs->write($return, $output, $this->mode);
    }

    foreach ($this->emails as $email) {
      mail($email, 'Cronjob', $output);
    }
    return $return;
  }
}
