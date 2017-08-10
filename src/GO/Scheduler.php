<?php namespace GO;

use DateTime;
use Exception;
use InvalidArgumentException;

class Scheduler
{
    /**
     * The queued jobs.
     *
     * @var array
     */
    private $jobs = [];

    /**
     * Successfully executed jobs.
     *
     * @var array
     */
    private $executedJobs = [];

    /**
     * Failed jobs.
     *
     * @var array
     */
    private $failedJobs = [];

    /**
     * The verbose output of the scheduled jobs.
     *
     * @var array
     */
    private $outputSchedule = [];

    /**
     * @var array
     */
    private $config;

    /**
     * Create new instance.
     *
     * @param  array  $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Queue a job for execution in the correct queue.
     *
     * @param  Job  $job
     * @return void
     */
    private function queueJob(Job $job)
    {
        $this->jobs[] = $job;
    }

    /**
     * Prioritise jobs in background.
     *
     * @return array
     */
    private function prioritiseJobs()
    {
        $background = [];
        $foreground = [];

        foreach ($this->jobs as $job) {
            if ($job->canRunInBackground()) {
                $background[] = $job;
            } else {
                $foreground[] = $job;
            }
        }

        return array_merge($background, $foreground);
    }

    /**
     * Get the queued jobs.
     *
     * @return array
     */
    public function getQueuedJobs()
    {
        return $this->prioritiseJobs();
    }

    /**
     * Queues a function execution.
     *
     * @param  callable  $fn  The function to execute
     * @param  array  $args  Optional arguments to pass to the php script
     * @param  string  $id   Optional custom identifier
     * @return Job
     */
    public function call(callable $fn, $args = [], $id = null)
    {
        $job = new Job($fn, $args, $id);

        $this->queueJob($job->configure($this->config));

        return $job;
    }

    /**
     * Queues a php script execution.
     *
     * @param  string  $script  The path to the php script to execute
     * @param  string  $bin     Optional path to the php binary
     * @param  array  $args     Optional arguments to pass to the php script
     * @param  string  $id      Optional custom identifier
     * @return Job
     */
    public function php($script, $bin = null, $args = [], $id = null)
    {
        $bin = $bin !== null && is_string($bin) && file_exists($bin) ?
            $bin : PHP_BINARY === '' ? '/usr/bin/php' : PHP_BINARY;

        $job = new Job($bin . ' ' . $script, $args, $id);

        $this->queueJob($job->configure($this->config));

        return $job;
    }

    /**
     * Queue a raw shell command.
     *
     * @param  string  $command  The command to execute
     * @param  array  $args      Optional arguments to pass to the command
     * @param  string  $id       Optional custom identifier
     * @return Job
     */
    public function raw($command, $args = [], $id = null)
    {
        $job = new Job($command, $args, $id);

        $this->queueJob($job->configure($this->config));

        return $job;
    }

    /**
     * Run the scheduler.
     *
     * @return array  Executed jobs
     */
    public function run()
    {
        // Reset collected data of last run
        $this->executedJobs = [];
        $this->failedJobs = [];
        $this->outputSchedule = [];

        $jobs = $this->getQueuedJobs();

        foreach ($jobs as $job) {
            if ($job->isDue()) {
                try {
                    $job->run();
                    $this->pushExecutedJob($job);
                } catch (\Exception $e) {
                    $this->pushFailedJob($job, $e);
                }
            }
        }

        return $this->getExecutedJobs();
    }

    /**
     * Add an entry to the scheduler verbose output array.
     *
     * @param  string  $string
     * @return void
     */
    private function addSchedulerVerboseOutput($string)
    {
        $now = '[' . (new DateTime('now'))->format('c') . '] ';
        $this->outputSchedule[] = $now . $string;

        // Print to stdoutput in light gray
        // echo "\033[37m{$string}\033[0m\n";
    }

    /**
     * Push a succesfully executed job.
     *
     * @param  Job  $job
     * @return Job
     */
    private function pushExecutedJob(Job $job)
    {
        $this->executedJobs[] = $job;

        $compiled = $job->compile();

        // If callable, log the string Closure
        if (is_callable($compiled)) {
            $compiled = 'Closure';
        }

        $this->addSchedulerVerboseOutput("Executing {$compiled}");

        return $job;
    }

    /**
     * Get the executed jobs.
     *
     * @return array
     */
    public function getExecutedJobs()
    {
        return $this->executedJobs;
    }

    /**
     * Push a failed job.
     *
     * @param  Job  $job
     * @param  Exception  $e
     * @return Job
     */
    private function pushFailedJob(Job $job, Exception $e)
    {
        $this->failedJobs[] = $job;

        $compiled = $job->compile();

        // If callable, log the string Closure
        if (is_callable($compiled)) {
            $compiled = 'Closure';
        }

        $this->addSchedulerVerboseOutput("{$e->getMessage()}: {$compiled}");

        return $job;
    }

    /**
     * Get the failed jobs.
     *
     * @return array
     */
    public function getFailedJobs()
    {
        return $this->failedJobs;
    }

    /**
     * Get the scheduler verbose output.
     *
     * @param  string  $type  Allowed: text, html, array
     * @return mixed  The return depends on the requested $type
     */
    public function getVerboseOutput($type = 'text')
    {
        switch ($type) {
            case 'text':
                return implode("\n", $this->outputSchedule);
            case 'html':
                return implode('<br>', $this->outputSchedule);
            case 'array':
                return $this->outputSchedule;
            default:
                throw new InvalidArgumentException('Invalid output type');
        }
    }

    /**
     * Remove all queued Jobs.
     */
    public function clearJobs()
    {
        $this->jobs = [];
    }
}
