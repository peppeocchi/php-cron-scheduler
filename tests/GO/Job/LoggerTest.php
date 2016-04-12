<?php namespace GO\Job\Tests;

use GO\Job\JobFactory;

class JobMock extends \GO\Job\Job
{
    public function build()
    {
        return "dude";
    }
}

class LoggerTest extends \PHPUnit_Framework_TestCase
{
    private function isWindows()
    {
        return (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    }

    private function getPsrLoggerMock()
    {
        return $this
            ->getMockBuilder('Psr\Log\LoggerInterface')
            ->setMethods([ 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log' ])
            ->getMock();
    }

    public function testShouldNotSendOutputToDevNullIfLoggerIsAttached()
    {
        $job = JobFactory::factory('GO\Job\Raw', 'somecommand');

        $loggerMock = $this->getMock('Psr\Log\LoggerInterface');

        $job->setLogger($loggerMock);

        $this->assertEquals('somecommand &', $job->build());
    }

    public function testShouldLogShellOutput()
    {
        if ($this->isWindows()) {
            $this->markTestSkipped("Can't execute shell script on Windows");
        }

        $job = JobFactory::factory('GO\Job\Raw', __DIR__ . '/../../test_job.sh');

        $loggerMock = $this->getPsrLoggerMock();
        $loggerMock
            ->expects($this->once())
            ->method('info')
            ->with('', [ 'testoutput1', 'testoutput2' ]);
        
        $job->setLogger($loggerMock);

        $job->exec();
    }

    public function testShouldLogClosureOutput()
    {
        $job = JobFactory::factory('GO\Job\Closure', function () {
            return "closureoutput";
        });

        $loggerMock = $this->getPsrLoggerMock();
        $loggerMock
            ->expects($this->once())
            ->method('info')
            ->with('', [ 'closureoutput' ]);

        $job->setLogger($loggerMock);

        $job->exec();
    }

    public function testShouldLogLabelIfSet()
    {
        $job = JobFactory::factory('GO\Job\Closure', function () {
            return "closureoutput";
        });
        
        $loggerMock = $this->getPsrLoggerMock();
        $loggerMock
            ->expects($this->once())
            ->method('info')
            ->with('mylabel', [ 'closureoutput' ]);

        $job->setLabel('mylabel')->setLogger($loggerMock);

        $job->exec();
    }

    public function testShouldLogJobDoneMessage()
    {
        $job = JobFactory::factory('GO\Job\Closure', function () {
            return "closureoutput";
        });

        $loggerMock = $this->getPsrLoggerMock();
        $loggerMock
            ->expects($this->at(0))
            ->method('info')
            ->with('', [ 'closureoutput' ]);
        $loggerMock
            ->expects($this->at(1))
            ->method('info')
            ->with('', [ 'job done' ]);

        $job->setJobDoneMessage('job done')->setLogger($loggerMock);

        $job->exec();
    }
}
