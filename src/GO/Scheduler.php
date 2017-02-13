<?php namespace GO;

use InvalidArgumentException;

use GO\Job\JobFactory;
use GO\Services\Filesystem;

class Scheduler
{

  /**
   * Timezone
   *
   * @var string
   */
  private $timezone = 'Europe/Dublin';

  /**
   * The scheduled jobs
   *
   * @var array of GO\Job\Job
   */
  private $jobs = [];

  /**
   * The executed jobs
   *
   * @var array of GO\Job\Job
   */
  private $executed = [];

  /**
   * The scheduler start time
   *
   * @var int
   */
  private $time;

  /**
   * Global config for the jobs
   *
   * @var array
   */
  private $config;


  /**
   * Create a new Scheduler instance and keep optional jobs config.
   *
   * @param array $config
   * @return void
   */
  public function __construct(array $config = [])
  {
    if (isset($config['tempDir']) &&
        (! is_dir($config['tempDir']) || ! is_writable($config['tempDir']))
    ) {
      throw new InvalidArgumentException("{$config['tempDir']} does not exits or is not writable.");
    }

    $this->useConfig($config);

    $this->time = time();
  }

  /**
   * Switch between configurations.
   *
   * @param array $config
   * @return $this
   */
  public function useConfig(array $config)
  {
    $this->config = $config;

    return $this;
  }

  /**
   * Get current configuration.
   *
   * @return array
   */
  public function getConfig()
  {
    return $this->config;
  }

  /**
   * Get jobs.
   *
   * @return array
   */
  public function getJobs()
  {
    return $this->jobs;
  }

  /**
   * Get executed jobs.
   *
   * @return array
   */
  public function getExecutedJobs()
  {
    return $this->executed;
  }

  /**
   * Set the timezone.
   *
   * @param string $timezone
   * @return void
   */
  public function setTimezone($timezone)
  {
    $this->config['timezone'] = $timezone;
  }

  /**
   * Get the directory for temporary files (job locks).
   *
   * @return string
   */
  public function getTempDir()
  {
    return isset($this->config['tempDir']) ? $this->config['tempDir'] : sys_get_temp_dir();
  }

  /**
   * PHP job.
   *
   * @param string $command
   * @param array $args
   * @return instance of GO\Job\Job
   */
  public function php($command, array $args = [])
  {
    return $this->jobs[] = JobFactory::factory(\GO\Job\Php::class, $command, $args);
  }

  /**
   * I'm feeling lucky
   * -----------------
   * Guess the job to run by the file extension
   *
   * @param string $command
   * @param array $args
   * @return instance of GO\Job\Job
   */
  private function command($command, array $args = [])
  {
    $file = basename($command);
  }

  /**
   * Raw job
   *
   * @param string $command
   * @return instance of GO\Job\Job
   */
  public function raw($command)
  {
    return $this->jobs[] = JobFactory::factory(\GO\Job\Raw::class, $command);
  }

  /**
   * Ping url
   *
   * @param string $url
   * @return instance of GO\Job\Job
   */
  public function ping($command)
  {
    return false;
    return $this->jobs[] = JobFactory::factory(\GO\Job\Ping::class, $command);
  }

  /**
   * Closure job
   *
   * @param callable $closure
   * @return instance of GO\Job\Job
   */
  public function call($closure)
  {
    return $this->jobs[] = JobFactory::factory(\GO\Job\Closure::class, $closure);
  }

  /**
   * Move the jobs that can run in background on top of the array
   * This is done to avoid blocking jobs that can slow down the
   * execution of other jobs
   *
   * @return void
   */
  public function jobsInBackgroundFirst()
  {
    usort($this->jobs, function ($a, $b) {
      return $b->runInBackground - $a->runInBackground;
    });
  }

  /**
   * Run the scheduled jobs
   *
   * @return array - The output of the executed jobs
   */
  public function run()
  {
    $output = [];

    // First reorder the cronjobs
    $this->jobsInBackgroundFirst();

    foreach ($this->jobs as $job) {
      $job->setup($this->config);

      // Check if job is due and if it should prevent overlapping
      if ($job->isDue()) {

        if ($job->preventOverlap() !== false) {

          // If overlapping, skip job execution
          if (! $this->jobCanRun($job)) {
            continue;
          }

          // Create lock file for this job to prevent overlap
          $lockFile = implode('/', [$this->getTempDir(), md5($job->getCommand()) . '.lock']);
          $lockFileContent = '';
          if(isset($this->config['verboseLockFile']) && $this->config['verboseLockFile'] == true)
              $lockFileContent = $job->getCommand();
          Filesystem::write($lockFileContent, $lockFile);
          // Sets the file to remove after the execution
          $job->removeLockAfterExec($lockFile);
          // Sets the job to run in foreground
          $job->runInForeground();
        }

        $output[] = $job->exec();
        $this->executed[] = $job;
      }
    }

    return $output;
  }

  /**
   * Check if the job is not overlapping.
   * If overlapping and a callback has been provided,
   * the result of that function will decide if the job can overlap.
   *
   * @param GO\Job\Job $job
   * @return bool
   */
  private function jobCanRun(\GO\Job\Job $job)
  {
    // Check if file exists, if not return true
    $lockFile = implode('/', [$this->getTempDir(), md5($job->getCommand()) . '.lock']);
    if (! file_exists($lockFile)) {
      return true;
    }

    // Return false if no callback was specified
    if ($job->preventOverlap() === true) {
      return false;
    }

    $filetime = filemtime($lockFile);
    $callback = $job->preventOverlap();

    return ! $callback($filetime);
  }

}
