<?php namespace GO;

use Closure;
use DateTime;
use Exception;
use InvalidArgumentException;

class Job
{
    use Traits\Interval,
        Traits\Mailer;

    /**
     * @var string
     */
    private $id;

    /**
     * @var mixed
     */
    private $command;

    /**
     * @var array
     */
    private $args = [];

    /**
     * @var bool
     */
    private $runInBackground = true;

    /**
     * @var DateTime
     */
    private $creationTime;

    /**
     * @var CronExpression
     */
    private $executionTime;

    /**
     * @var string
     */
    private $tempDir;

    /**
     * @var string
     */
    private $lockFile;

    /**
     * This could prevent the job to run.
     * If true, the job will run (if due).
     *
     * @var bool
     */
    private $truthTest = true;

    /**
     * @var array
     */
    private $outputTo = [];

    /**
     * @var array
     */
    private $emailTo = [];

    /**
     * @var array
     */
    private $emailConfig = [];

    /**
     * @var Swift_Mailer
     */
    private $mailer;

    /**
     * @var Messenger
     */
    private $messenger;

    /**
     * Create a new Job instance.
     *
     * @param  string\Closure  $command
     * @param  array  $args
     * @param  string  $id
     * @return void
     */
    public function __construct($command, $args = [], $id = null)
    {
        if (is_string($id)) {
            $this->id = $id;
        } else {
            if (is_string($command)) {
                $this->id = md5($command);
            } else {
                $this->id = spl_object_hash($command);
            }
        }

        $this->creationTime = new DateTime('now');
        $this->tempDir = sys_get_temp_dir();

        $this->command = $command;
        $this->args = $args;
    }

    /**
     * Get the Job id
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Check if the Job is due to run.
     *
     * @param  DateTime  $date
     * @return bool
     */
    public function isDue(DateTime $date = null)
    {
        // The execution time is being defaulted if not defined
        if (! $this->executionTime) {
            $this->at('* * * * *');
        }

        $date = $date !== null ? $date : $this->creationTime;

        return $this->executionTime->isDue($date);
    }

    /**
     * Check if the Job is overlapping.
     *
     * @param  string  $tempDir
     * @return bool
     */
    public function isOverlapping()
    {
        return $this->lockFile && file_exists($this->lockFile);
    }

    /**
     * Force the Job to run in foreground.
     *
     * @return this
     */
    public function inForeground()
    {
        $this->runInBackground = false;

        return $this;
    }

    /**
     * Check if the Job can run in background.
     *
     * @return bool
     */
    public function canRunInBackground()
    {
        if (is_callable($this->command) || $this->runInBackground === false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * This will prevent the Job from overlapping.
     * It prevents another instance of the same Job of
     * being executed if the previous is still running.
     *
     * @return this
     */
    public function onlyOne($tempDir = null)
    {
        if ($tempDir === null || ! is_dir($tempDir)) {
            $tempDir = $this->tempDir;
        }

        $this->lockFile = implode('/', [
            trim($tempDir),
            trim($this->id) . '.lock',
        ]);

        return $this;
    }

    /**
     * Compile the Job command.
     *
     * @return mixed
     */
    public function compile()
    {
        $compiled = $this->command;

        // If Closure, return the Closure itself
        if (is_callable($compiled)) {
            return $compiled;
        }

        // Augment with any supplied arguments
        foreach ($this->args as $key => $value) {
            $compiled .= ' ' . escapeshellarg($key);
            if ($value !== null) {
                $compiled .= ' ' . escapeshellarg($value);
            }
        }

        // Add the boilerplate to redirect the output to file/s
        if (count($this->outputTo) > 0) {
            $compiled .= ' | tee ';
            $compiled .= $this->outputMode === 'a' ? '-a ' : '';
            foreach ($this->outputTo as $file) {
                $compiled .= $file . ' ';
            }

            $compiled = trim($compiled);
        }

        // Add boilerplate to remove lockfile after execution
        if ($this->lockFile) {
            $compiled .= '; rm ' . $this->lockFile;
        }

        // Add boilerplate to run in background
        if ($this->canRunInBackground()) {
            // Parentheses are need execute the chain of commands in a subshell
            // that can then run in background
            $compiled = '(' . $compiled . ') > /dev/null 2>&1 &';
        }

        return trim($compiled);
    }

    public function configure(array $config = [])
    {
        if (isset($config['email'])) {
            if (! is_array($config['email'])) {
                throw new InvalidArgumentException("Email configuration should be an array.");
            }
            $this->emailConfig = $config['email'];
        }

        // Check if config has defined a tempDir
        if (isset($config['tempDir']) && is_dir($config['tempDir'])) {
            $this->tempDir = $config['tempDir'];
        }

        return $this;
    }

    /**
     * Truth test
     *
     * @return bool
     */
    public function when(Closure $fn)
    {
        $this->truthTest = $fn();

        return $this;
    }

    /**
     * Run the Job.
     *
     * @return bool
     */
    public function run()
    {
        // If the truthTest failed, don't run
        if ($this->truthTest !== true) {
            return false;
        }

        // If overlapping, don't run
        if ($this->isOverlapping()) {
            return false;
        }

        $compiled = $this->compile();

        // Write lock file if necessary
        $this->createLockFile();

        if (is_callable($compiled)) {
            $this->exec($compiled);
        } else {
            exec($compiled);
        }

        $this->emailOutput();

        return true;
    }

    private function createLockFile($content = null)
    {
        if ($this->lockFile) {
            if ($content === null || ! is_string($content)) {
                $content = $this->getId();
            }

            file_put_contents($this->lockFile, $content);
        }
    }

    private function removeLockFile()
    {
        if ($this->lockFile && file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
    }

    /**
     * Execute a function.
     *
     * @param  Closure  $fn
     * @return void
     */
    private function exec(Closure $fn)
    {
        ob_start();

        try {
            $returnData = call_user_func_array($fn, $this->args);
        } catch (Exception $e) {
            ob_end_clean();
            throw $e;
        }

        $outputBuffer = ob_get_clean();

        foreach ($this->outputTo as $filename) {
            if ($outputBuffer) {
                file_put_contents($filename, $outputBuffer, $this->outputMode === 'a' ? FILE_APPEND : 0);
            }

            if ($returnData) {
                file_put_contents($filename, $returnData, FILE_APPEND);
            }
        }

        $this->removeLockFile();
    }

    /**
     * Write the output of the Job to file/s.
     *
     * @param  string\array  $filename
     * @param  bool  $append
     * @return this
     */
    public function output($filename, $append = false)
    {
        $this->outputTo = is_array($filename) ? $filename : [$filename];
        $this->outputMode = $append === false ? 'w' : 'a';

        return $this;
    }

    /**
     * Sends the output to email/s.
     * The Job should be set to write output to a file
     * for this to work.
     *
     * @param  string\array  $email
     * @return this
     */
    public function email($email)
    {
        if (! is_string($email) && ! is_array($email)) {
            throw new InvalidArgumentException("Email can be only string or array");
        }

        $this->emailTo = is_array($email) ? $email : [$email];

        // Force the job to run in foreground
        $this->inForeground();

        return $this;
    }

    private function emailOutput()
    {
        if (! count($this->outputTo) || ! count($this->emailTo)) {
            return false;
        }

        $this->sendToEmails($this->outputTo);
    }

    // public function ping($url, $method = 'GET', array $config = [])
    // {
    //     $this->messenger = new Messenger($url, $method, $config);

    //     // Set the job to run in foreground
    //     $this->inForeground();
    // }
}
