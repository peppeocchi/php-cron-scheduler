<?php namespace GO\Job;

use GO\Services\Filesystem;
use GO\Services\Interval;

use Cron\CronExpression;
use Swift_Attachment;
use Swift_Mailer;
use Swift_MailTransport;
use Swift_Message;

abstract class Job
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
   * Filesystem manager
   *
   * @var GO\Services\Filesystem
   */
  private $fs;

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
   * Bool that define if the command is due
   *
   * @var bool
   */
  public $due = false;

  /**
   * Bool that defines if the command has run in backgroud
   *
   * @var bool
   */
  public $runInBackground = true;


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

    // if (is_callable($job)) {
      $this->command = $job;
    // } else {
      // $this->fs = new Filesystem($job);
      // $this->command = $this->fs->getCommand();
    // }

    $this->args = $args;

    if (method_exists($this, 'init')) {
      $this->init();
    }
  }

  /**
   * Define when to run the job
   *
   * @param string expression
   * @return $this
   */
  public function at($expression)
  {
    $execution = CronExpression::factory($expression);

    if ($execution->isDue()) {
      $this->due = true;
    }

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
   * Check if the job is due to run
   *
   * @return bool
   */
  public function isDue()
  {
    return $this->due;
  }

  /**
   * Abstract function build and compile the command
   *
   */
  abstract protected function build();

  /**
   * Compile command - finalize with output redirections
   *
   * @param string $command
   * @return string
   */
  protected function compile($command)
  {
    if (count($this->args) > 0) {
      foreach ($this->args as $key => $value) {
        $command .= ' ' . $key . ' ' . $value;
      }
    }

    if (count($this->outputs) > 0) {
      $command .= ' | tee ';
      $command .= $this->mode === 'a' ? '-a ' : '';
      foreach ($this->outputs as $o) {
        $command .= $o.' ';
      }
    }

    $command .= '> /dev/null 2>&1';
    if ($this->runInBackground === true) {
      $command .= ' &';
    }

    return $this->compiled = trim($command);
  }

  /**
   * Execute the job
   *
   * @return string - The output of the executed job
   */
  public function exec()
  {
    $compiled = $this->build();
    if (is_callable($this->compiled)) {
      $return = call_user_func($this->command, $this->args);
      foreach ($this->outputs as $output) {
        Filesystem::write($return, $output, $this->mode);
      }
    } else {
      $return = exec($this->compiled);
    }

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
}
