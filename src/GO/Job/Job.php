<?php namespace GO\Job;

use GO\Services\Filesystem;
use GO\Services\Interval;

use Cron\CronExpression;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Swift_Attachment;
use Swift_Mailer;
use Swift_MailTransport;
use Swift_Message;

abstract class Job implements LoggerAwareInterface
{
  /**
   * The command
   *
   * @var string
   */
  protected $command;

  /**
   * The arguments to be passed to the command
   *
   * @var array
   */
  protected $args;

  /**
   * The compiled command
   *
   * @var string
   */
  protected $compiled;

  /**
   * The job start time
   *
   * @var int
   */
  protected $time;

  /**
   * The overlap flag. It could contain a callback.
   *
   * @var true|Closure
   */
  protected $overlap = false;

  /**
   * The files where the output has to be sent
   *
   * @var array
   */
  protected $outputs = [];

  /**
   * The emails where the output has to be sent
   *
   * @var array
   */
  private $emails = [];

  /**
   * The email address used to send the email
   *
   * @var array
   */
  private $emailFrom = ['cronjob@server.my' => 'My Email Server'];

  /**
   * Instance of
   *
   * @var Cron\CronExpression
   */
  public $execution;

  /**
   * Bool that defines if the command has to run in backgroud
   *
   * @var bool
   */
  public $runInBackground = true;

  /**
   * Bool that defines if the command passed the truth test
   *
   * @var bool
   */
  public $truthTest = true;

  /**
   * PSR-3 compliant logger
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * Label for the job, to make it easier to identify in the logs
   * @var string
   */
  private $jobLabel;

  /**
   * Optional message that will be added to the
   * @var string
   */
  private $jobDoneMessage;

  /**
   * Path to lock file.
   * @var string
   */
  private $lockFile;

  /**
   * Create a new instance of Job
   *
   * @param mixed $job
   * @param array $args
   * @return void
   */
  public function __construct($job, array $args = [])
  {
    $this->time = time();

    $this->command = $job;

    $this->args = $args;

    if (method_exists($this, 'init')) {
      $this->init();
    }
  }

  /**
   * Get command
   *
   * @return array
   */
  public function getCommand()
  {
    return $this->command;
  }

  /**
   * Get compiled
   *
   * @return array
   */
  public function getCompiled()
  {
    return $this->compiled;
  }

  /**
   * Get args
   *
   * @return array
   */
  public function getArgs()
  {
    return $this->args;
  }

  /**
   * Assign an alias to the job
   * This can be useful when having several cron jobs
   * to check what is being executed on each run
   *
   * @return array
   */
  public function alias($alias)
  {
    return $this->commandAlias = $alias;
  }

  /**
   * Define when to run the job
   *
   * @param string expression
   * @return $this
   */
  public function at($expression)
  {
    $this->execution = CronExpression::factory($expression);

    return $this;
  }

  /**
   * Define the execution interval of the job
   *
   * @param int $interval - fallback to string '*'
   * @return GO\Services\Interval
   */
  public function every($interval = '*')
  {
    return new Interval($this, $interval);
  }

  /**
   * Define the file/s where to send the output of the job
   *
   * @param string/array $ouput
   * @param bool $mode
   * @return $this
   */
  public function output($output, $mode = false)
  {
    $this->outputs = is_array($output) ? $output : [$output];

    $this->mode = $mode === true ? 'a' : 'w';

    return $this;
  }

  /**
   * Get files output
   *
   * @return array
   */
  public function getFilesOutput()
  {
    return $this->outputs;
  }

  /**
   * Define the email address/es where to send the output of the job
   *
   * @param string/array $email
   * @return $this
   */
  public function email($email)
  {
    $this->emails = is_array($email) ? $email : [$email];

    $this->runInBackground = false;

    return $this;
  }

  /**
   * Get emails output
   *
   * @return array
   */
  public function getEmailsOutput()
  {
    return $this->emails;
  }

  /**
   * Check if the job is due to run
   *
   * @return bool
   */
  public function isDue()
  {
    return $this->execution->isDue() && $this->truthTest === true;
  }

  /**
   * Abstract function build and compile the command
   *
   */
  abstract public function build();

  /**
   * Compile command
   * - add arguments
   * - redirect output to file
   * - remove lock file
   * - send output to logger
   * - run in backgrou/foreground
   *
   * @param string $command
   * @return string
   */
  protected function compile($command)
  {
    if (count($this->args) > 0) {
      foreach ($this->args as $key => $value) {
        $command .= " {$key} \"{$value}\"";
      }
    }

    if (count($this->outputs) > 0) {
      $command .= ' | tee ';
      $command .= $this->mode === 'a' ? '-a ' : '';
      foreach ($this->outputs as $o) {
        $command .= $o.' ';
      }
    }

    /* If overlap is not false, then add the command to remove the lock
       file after the execution */
    if ($this->overlap !== false) {
      $command .= '; rm ' . $this->lockFile;
    }

    // Only hide output if no loggers are used
    if (! $this->logger) {
      $command .= ' > /dev/null 2>&1';
    }

    if ($this->runInBackground === true) {
      $command .= ' &';
    }

    return $this->compiled = trim($command);
  }

  /**
   * Set the lock file path to remove after the execution.
   *
   * @param string $file
   * @return void
   */
  public function removeLockAfterExec($file)
  {
    $this->lockFile = $file;
  }

  /**
   * Execute the job
   *
   * @return string - The output of the executed job
   */
  public function exec()
  {
    $jobOutput = [];
    $this->compiled = $this->build();

    if (is_callable($this->compiled)) {
      $return = call_user_func($this->command, $this->args);
      foreach ($this->outputs as $output) {
        Filesystem::write($return, $output, $this->mode);
      }

      if (is_string($return)) {
        $jobOutput[] = $return;
      }
    } else {
      $return = exec($this->compiled, $jobOutput);
    }

    $this->logJobOutput($jobOutput);

    if ($this->emails) {
      $this->sendEmails();
    }

    return $return;
  }

  /**
   * Send the output to an email address
   *
   * @return void
   */
  private function sendEmails()
  {
    $transport = Swift_MailTransport::newInstance();
    $mailer = Swift_Mailer::newInstance($transport);

    $message = Swift_Message::newInstance()
      ->setSubject('Cronjob execution')
      ->setFrom($this->emailFrom)
      ->setTo($this->emails)
      ->setBody('Cronjob output attached')
      ->addPart('<q>Cronjob output attached</q>', 'text/html');

    foreach ($this->outputs as $file) {
      $message->attach(Swift_Attachment::fromPath($file));
    }

    $mailer->send($message);
  }

  /**
   * Run the command in foreground
   *
   * @return void
   */
  public function runInForeground()
  {
    $this->runInBackground = false;
  }

  /**
   * Injected config from the scheduler
   *
   * @return void
   */
  public function setup(array $config)
  {
    if (isset($config['emailFrom'])) {
      $this->emailFrom = $config['emailFrom'];
    }
  }

  /**
   * Delegate execution to truth test if it's due
   *
   * @return void
   */
  public function when($test)
  {
    if (! is_callable($test)) {
      throw new \Exception('InvalidArgumentException');
    }
    $this->truthTest = $test();

    return $this;
  }

  /**
   * Attach a PSR-3 compliant logger to this job
   *
   * @param \Psr\Log\LoggerInterface $logger
   *
   * @return $this
   */
  public function setLogger(LoggerInterface $logger)
  {
    $this->logger = $logger;

    return $this;
  }

  /**
   * Define a label for this job, which is used by the logger
   *
   * @param string $label
   *
   * @return $this
   */
  public function setLabel($label)
  {
    $this->jobLabel = $label;

    return $this;
  }

  /**
   * Define a message that will be logged after the job has run
   *
   * @param string $message
   *
   * @return $this
   */
  public function setJobDoneMessage($message)
  {
    $this->jobDoneMessage = $message;

    return $this;
  }

  /**
   * Get the log label for this job
   * @return string
   */
  protected function getLogLabel()
  {
    return (! empty($this->jobLabel)) ? $this->jobLabel : '';
  }

  /**
   * Log output, if there is anything to log
   *
   * @param array $jobOutput
   *
   * @return void
   */
  protected function logJobOutput($jobOutput)
  {
    if (! $this->logger) {
      return;
    }

    if (count($jobOutput) > 0) {
      $this->logger->info($this->getLogLabel(), $jobOutput);
    }

    if ($this->jobDoneMessage !== null) {
      $this->logger->info($this->getLogLabel(), [ $this->jobDoneMessage ]);
    }
  }

  /**
   * Prevent the job from overlapping with a previous execution.
   *
   * @param Closure $callback
   * @return $this
   */
  public function doNotOverlap(\Closure $callback = null)
  {
    if ($callback !== null) {
      $this->overlap = $callback;
    } else {
      $this->overlap = true;
    }

    return $this;
  }

  /**
   * Get the overlap value.
   *
   * @return bool|Closure
   */
  public function preventOverlap()
  {
    return $this->overlap;
  }
}
