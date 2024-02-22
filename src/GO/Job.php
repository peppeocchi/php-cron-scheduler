<?php

namespace GO;

use Redis;
use DateTime;
use Exception;
use InvalidArgumentException;

class Job
{
    use Traits\Interval;
    use Traits\Mailer;

    /**
     * Job identifier.
     *
     * @var string
     */
    private $id;

    /**
     * Command to execute.
     *
     * @var mixed
     */
    private $command;

    /**
     * Arguments to be passed to the command.
     *
     * @var array
     */
    private $args = [];

    /**
     * Defines if the job should run in background.
     *
     * @var bool
     */
    private $runInBackground = true;

    /**
     * Creation time.
     *
     * @var DateTime
     */
    private $creationTime;

    /**
     * Job schedule time.
     *
     * @var Cron\CronExpression
     */
    private $executionTime;

    /**
     * Job schedule year.
     *
     * @var string
     */
    private $executionYear = null;

    /**
     * Temporary directory path for
     * lock files to prevent overlapping.
     *
     * @var string
     */
    private $tempDir;

    /**
     * Path to the lock file.
     *
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
     * The output of the executed job.
     *
     * @var mixed
     */
    private $output;

    /**
     * The return code of the executed job.
     *
     * @var int
     */
    private $returnCode = 0;

    /**
     * Files to write the output of the job.
     *
     * @var array
     */
    private $outputTo = [];

    /**
     * Email addresses where the output should be sent to.
     *
     * @var array
     */
    private $emailTo = [];

    /**
     * Configuration for email sending.
     *
     * @var array
     */
    private $emailConfig = [];

    /**
     * A function to execute before the job is executed.
     *
     * @var callable
     */
    private $before;

    /**
     * A function to execute after the job is executed.
     *
     * @var callable
     */
    private $after;

    /**
     * A function to ignore an overlapping job.
     * If true, the job will run also if it's overlapping.
     *
     * @var callable
     */
    private $whenOverlapping;

    /**
     * @var string
     */
    private $outputMode;

    /**
     * Redis client.
     *
     * @var Redis
     */
    private $redisClient = null;

    /**
     * Redis prefix.
     *
     * @var string
     */
    private $redisPrefix = 'cron_lock:';

    /**
     * Indicates whether the lock was successfully acquired.
     *
     * @var bool
     */
    private $lockAcquired = false;

    /**
     * Create a new Job instance.
     *
     * @param  string|callable  $command
     * @param  array            $args
     * @param  string           $id
     */
    public function __construct($command, $args = [], $id = null)
    {
        if (is_string($id)) {
            $this->id = $id;
        } else {
            if (is_string($command)) {
                $this->id = md5($command);
            } elseif (is_array($command)) {
                $this->id = md5(serialize($command));
            } else {
                /* @var object $command */
                $this->id = spl_object_hash($command);
            }
        }

        $this->creationTime = new DateTime('now');

        // initialize the directory path for lock files
        $this->tempDir = sys_get_temp_dir();

        $this->command = $command;
        $this->args = $args;
    }

    /**
     * Get the Job id.
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Check if the Job is due to run.
     * It accepts as input a DateTime used to check if
     * the job is due. Defaults to job creation time.
     * It also defaults the execution time if not previously defined.
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

        if ($this->executionYear && $this->executionYear !== $date->format('Y')) {
            return false;
        }

        return $this->executionTime->isDue($date);
    }

    /**
     * Check if the Job is overlapping.
     *
     * @return bool
     */
    public function isOverlapping()
    {
        if ($this->redisClient) {
            $lockKey = $this->redisPrefix . $this->id;

            return $this->redisClient->exists($lockKey);
        }

        return $this->lockFile && file_exists($this->lockFile);
    }

    /**
     * Force the Job to run in foreground.
     *
     * @return self
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
        }

        return true;
    }

    /**
     * This will prevent the Job from overlapping.
     * It prevents another instance of the same Job of
     * being executed if the previous is still running.
     * The job id is used as a filename for the lock file.
     *
     * @param  string    $tempDir          The directory path for the lock files
     * @param  callable  $whenOverlapping  A callback to ignore job overlapping
     * @return self
     */
    public function onlyOne($tempDir = null, callable $whenOverlapping = null)
    {
        if ($this->redisClient) {
            $lockKey = $this->redisPrefix . $this->id;
            // Atomically set the key if it doesn't exist and set an expiry time (e.g., 3600 seconds)
            $this->lockAcquired = $this->redisClient->set($lockKey, time(), ['nx', 'ex' => 3600]);
        } else {
            // Fallback to file lock mechanism
            if ($tempDir === null || ! is_dir($tempDir)) {
                $tempDir = $this->tempDir;
            }

            $this->lockFile = implode('/', [
                trim($tempDir),
                trim($this->id) . '.lock',
            ]);

            if (! file_exists($this->lockFile)) {
                // Attempt to create the lock file
                touch($this->lockFile);
                $this->lockAcquired = true;
            }
        }

        if (! $this->lockAcquired && $whenOverlapping) {
            call_user_func($whenOverlapping);
            return $this; // Return early if overlap is detected
        }

        return $this;
    }

    /**
     * Releases the lock for the current job.
     * This involves deleting the Redis key used for locking or removing the lock file.
     *
     * @return void
     */
    private function releaseLock()
    {
        if ($this->lockAcquired) {
            if ($this->redisClient) {
                $lockKey = $this->redisPrefix . $this->id;
                $this->redisClient->del([$lockKey]);
            } elseif (file_exists($this->lockFile)) {
                // Remove the lock file
                unlink($this->lockFile);
            }
            $this->lockAcquired = false; // Reset the lock status
        }
    }

    /**
     * Compile the Job command.
     *
     * @return mixed
     */
    public function compile()
    {
        $compiled = $this->command;

        // If callable, return the function itself
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

    /**
     * Configure the job.
     *
     * @param  array  $config
     * @return self
     */
    public function configure(array $config = [])
    {
        if (isset($config['email'])) {
            if (! is_array($config['email'])) {
                throw new InvalidArgumentException('Email configuration should be an array.');
            }
            $this->emailConfig = $config['email'];
        }

        // Check if config has defined a tempDir
        if (isset($config['tempDir']) && is_dir($config['tempDir'])) {
            $this->tempDir = $config['tempDir'];
        }

        // Set the Redis client and prefix
        if (isset($config['redisClient']) && $config['redisClient'] instanceof Redis) {
            $this->redisClient = $config['redisClient'];
            $this->redisPrefix = $config['redisPrefix'] ?? $this->redisPrefix;
        }

        return $this;
    }

    /**
     * Truth test to define if the job should run if due.
     *
     * @param  callable  $fn
     * @return self
     */
    public function when(callable $fn)
    {
        $this->truthTest = $fn();

        return $this;
    }

    /**
     * Run the job.
     *
     * @return bool
     */
    public function run()
    {
        // Check if the lock was not acquired, indicating another instance is already running
        if (!$this->lockAcquired) {
            return false; // Exit the method without running the job
        }

        try {
            // This is where you'd execute the compiled command or callable
            if (is_callable($this->before)) {
                call_user_func($this->before);
            }

            $compiled = $this->compile();
            if (is_callable($compiled)) {
                $this->output = $this->exec($compiled);
            } else {
                exec($compiled, $this->output, $this->returnCode);
            }

            // Any finalization logic after successful execution
            $this->finalise();
        } catch (Exception $e) {
            // Handle any exceptions that occurred during job execution
            throw new Exception('An error occurred during job execution: ' . $e->getMessage());
        } finally {
            // Always release the lock, regardless of job success or failure
            $this->releaseLock();
        }

        return true; // Indicate the job was executed (attempted)
    }

    /**
     * Create the job lock file.
     *
     * @param  mixed  $content
     * @return void
     */
    private function createLockFile($content = null)
    {
        if ($this->lockFile) {
            if ($content === null || ! is_string($content)) {
                $content = $this->getId();
            }

            file_put_contents($this->lockFile, $content);
        }
    }

    /**
     * Remove the job lock file.
     *
     * @return void
     */
    private function removeLockFile()
    {
        if ($this->lockFile && file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
    }

    /**
     * Execute a callable job.
     *
     * @param  callable  $fn
     * @throws Exception
     * @return string
     */
    private function exec(callable $fn)
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

        // Release the lock in case of Redis
        if ($this->redisClient) {
            $this->releaseLock();
        }

        return $outputBuffer . (is_string($returnData) ? $returnData : '');
    }

    /**
     * Set the file/s where to write the output of the job.
     *
     * @param  string|array  $filename
     * @param  bool          $append
     * @return self
     */
    public function output($filename, $append = false)
    {
        $this->outputTo = is_array($filename) ? $filename : [$filename];
        $this->outputMode = $append === false ? 'w' : 'a';

        return $this;
    }

    /**
     * Get the job output.
     *
     * @return mixed
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Set the emails where the output should be sent to.
     * The Job should be set to write output to a file
     * for this to work.
     *
     * @param  string|array  $email
     * @return self
     */
    public function email($email)
    {
        if (! is_string($email) && ! is_array($email)) {
            throw new InvalidArgumentException('The email can be only string or array');
        }

        $this->emailTo = is_array($email) ? $email : [$email];

        // Force the job to run in foreground
        $this->inForeground();

        return $this;
    }

    /**
     * Finilise the job after execution.
     *
     * @return void
     */
    private function finalise()
    {
        // Send output to email
        $this->emailOutput();

        // Call any callback defined
        if (is_callable($this->after)) {
            call_user_func($this->after, $this->output, $this->returnCode);
        }
    }

    /**
     * Email the output of the job, if any.
     *
     * @return bool
     */
    private function emailOutput()
    {
        if (! count($this->outputTo) || ! count($this->emailTo)) {
            return false;
        }

        if (isset($this->emailConfig['ignore_empty_output']) &&
            $this->emailConfig['ignore_empty_output'] === true &&
            empty($this->output)
        ) {
            return false;
        }

        $this->sendToEmails($this->outputTo);

        return true;
    }

    /**
     * Set function to be called before job execution
     * Job object is injected as a parameter to callable function.
     *
     * @param callable $fn
     * @return self
     */
    public function before(callable $fn)
    {
        $this->before = $fn;

        return $this;
    }

    /**
     * Set a function to be called after job execution.
     * By default this will force the job to run in foreground
     * because the output is injected as a parameter of this
     * function, but it could be avoided by passing true as a
     * second parameter. The job will run in background if it
     * meets all the other criteria.
     *
     * @param  callable  $fn
     * @param  bool      $runInBackground
     * @return self
     */
    public function then(callable $fn, $runInBackground = false)
    {
        $this->after = $fn;

        // Force the job to run in foreground
        if ($runInBackground === false) {
            $this->inForeground();
        }

        return $this;
    }
}
