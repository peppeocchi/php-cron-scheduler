<?php namespace GO\Services;

/**
 * Job TimeParser Decorator
 *
 */
class Interval
{
  /**
   * Instance of GO\Job\Job
   *
   */
  private $job;

  /**
   * Interval
   *
   * @var string
   */
  private $interval;

  /**
   * Set job and interval
   *
   * @param GO\Job\Job $job
   * @param string $interval
   * @return void
   */
  public function __construct(/*GO\Job\Job */$job, $interval = '*')
  {
    $this->job = $job;

    $this->interval = is_int($interval) ? '*/'.$interval : $interval;
  }

  /**
   * Execute job every $interval minute
   *
   * @return instance of GO\Job\Job
   */
  public function minute()
  {
    return $this->job->at("{$this->interval} * * * *");
  }

  /**
   * Execute job every $interval hour
   *
   * @param string $minute
   * @return instance of GO\Job\Job
   */
  public function hour($minute = '00')
  {
    return $this->job->at("{$minute} {$this->interval} * * *");
  }

  /**
   * Execute job every $interval hour
   *
   * @param string $string - hour:minute
   * @return instance of GO\Job\Job
   */
  public function day($string = '00:00')
  {
    $time = explode(':', $string);
    return $this->job->at("{$time[1]} {$time[0]} {$this->interval} * *");
  }

  /**
   * Execute job every $interval hour
   *
   * @param string $string - day hour:minute
   * @return instance of GO\Job\Job
   */
  public function month($string = '01 00:00')
  {
    $date = explode(' ', $string);
    $time = explode(':', $date[1]);

    return $this->job->at("{$time[1]} {$time[0]} * {$this->interval} *");
  }

  // Removed
  private function year()
  {
    return $job;
  }
}
