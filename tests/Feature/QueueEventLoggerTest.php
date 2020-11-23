<?php

namespace Larawelders\QueueEventLogger\Tests\Feature;

use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Support\Facades\Log;
use Larawelders\QueueEventLogger\Tests\TestCase;

class QueueEventLoggerTest extends TestCase
{
    /** @test */
    public function it_logs_job_exception_occurred_event()
    {
        $job = new TestJob;
        $exception = new Exception('TestJob failed');

        $this->app['events']->dispatch(
            new JobExceptionOccurred('default', $job, $exception)
        );

        Log::shouldReceive('queue')->with(
            '[test-job] Uncaught exception Exception in job Larawelders\\QueueEventLogger\\Tests\\Feature\\TestJob: TestJob failed'
        );
    }
}

class TestJob implements ShouldQueue
{
    public function resolveName()
    {
        return static::class;
    }

    public function getJobId()
    {
        return 'test-job';
    }
}
