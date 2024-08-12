<?php namespace GO;

use Throwable;

class FailedJob
{
    /**
     * @var Job
     */
    private $job;

    /**
     * @var Throwable
     */
    private $exception;

    public function __construct(Job $job, Throwable $exception)
    {
        $this->job = $job;
        $this->exception = $exception;
    }

    public function getJob(): Job
    {
        return $this->job;
    }

    public function getException(): Throwable
    {
        return $this->exception;
    }
}
