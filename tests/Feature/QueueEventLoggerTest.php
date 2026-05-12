<?php

declare(strict_types=1);

namespace Larawelders\QueueEventLogger\Tests\Feature;

use Exception;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Queue\Events\QueueFailedOver;
use Illuminate\Queue\Events\QueuePaused;
use Illuminate\Queue\Events\QueueResumed;
use Illuminate\Queue\Events\WorkerStarting;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Log;
use Larawelders\QueueEventLogger\QueueEventLoggerSubscriber;
use Larawelders\QueueEventLogger\Tests\TestCase;
use Throwable;

class QueueEventLoggerTest extends TestCase
{
    public function test_it_registers_the_default_queue_log_channel_configuration(): void
    {
        $this->assertSame('queue', config('queue-event-logger.channel'));
        $this->assertSame(
            [
                'driver' => 'daily',
                'path' => storage_path('logs/queue.log'),
                'level' => 'debug',
            ],
            config('logging.channels.queue')
        );
    }

    public function test_it_does_not_override_an_existing_application_log_channel(): void
    {
        config()->set('logging.channels.queue', [
            'driver' => 'daily',
            'path' => storage_path('logs/custom-queue.log'),
            'level' => 'warning',
        ]);

        $this->app->register(\Larawelders\QueueEventLogger\QueueEventLoggerServiceProvider::class);

        $this->assertSame(
            [
                'driver' => 'daily',
                'path' => storage_path('logs/custom-queue.log'),
                'level' => 'warning',
            ],
            config('logging.channels.queue')
        );
    }

    public function test_it_registers_event_listeners_using_class_callables(): void
    {
        $events = new RecordingDispatcher;

        (new QueueEventLoggerSubscriber)->subscribe($events);

        $this->assertSame([
            [WorkerStarting::class, [QueueEventLoggerSubscriber::class, 'handleWorkerStarting']],
            [JobProcessing::class, [QueueEventLoggerSubscriber::class, 'handleJobProcessing']],
            [JobProcessed::class, [QueueEventLoggerSubscriber::class, 'handleJobProcessed']],
            [JobExceptionOccurred::class, [QueueEventLoggerSubscriber::class, 'handleJobExceptionOccurred']],
            [Looping::class, [QueueEventLoggerSubscriber::class, 'handleLooping']],
            [JobFailed::class, [QueueEventLoggerSubscriber::class, 'handleJobFailed']],
            [WorkerStopping::class, [QueueEventLoggerSubscriber::class, 'handleWorkerStopping']],
            [JobQueued::class, [QueueEventLoggerSubscriber::class, 'handleJobQueued']],
            [JobReleasedAfterException::class, [QueueEventLoggerSubscriber::class, 'handleJobReleasedAfterException']],
            [JobTimedOut::class, [QueueEventLoggerSubscriber::class, 'handleJobTimedOut']],
            [QueueBusy::class, [QueueEventLoggerSubscriber::class, 'handleQueueBusy']],
            [QueueFailedOver::class, [QueueEventLoggerSubscriber::class, 'handleQueueFailedOver']],
            [QueuePaused::class, [QueueEventLoggerSubscriber::class, 'handleQueuePaused']],
            [QueueResumed::class, [QueueEventLoggerSubscriber::class, 'handleQueueResumed']],
        ], $events->listeners);
    }

    public function test_it_logs_job_processing_event(): void
    {
        $this->expectInfo('[test-job] Processing job Tests\\Fixtures\\TestJob');

        $this->app['events']->dispatch(new JobProcessing('default', new FakeJob));
    }

    public function test_it_logs_job_processed_event(): void
    {
        $this->expectInfo('[test-job] Processed job Tests\\Fixtures\\TestJob');

        $this->app['events']->dispatch(new JobProcessed('default', new FakeJob));
    }

    public function test_it_logs_job_exception_occurred_event(): void
    {
        $this->expectError(
            '[test-job] Uncaught exception Exception in job Tests\\Fixtures\\TestJob: TestJob failed'
        );

        $this->app['events']->dispatch(
            new JobExceptionOccurred('default', new FakeJob, new Exception('TestJob failed'))
        );
    }

    public function test_it_logs_job_failed_event(): void
    {
        $this->expectError(
            '[test-job] Job failed Exception in job Tests\\Fixtures\\TestJob: TestJob failed'
        );

        $this->app['events']->dispatch(
            new JobFailed('default', new FakeJob, new Exception('TestJob failed'))
        );
    }

    public function test_it_logs_worker_starting_event(): void
    {
        $this->expectInfo('[worker] Starting connection redis on queue emails');

        $this->app['events']->dispatch(new WorkerStarting('redis', 'emails', new WorkerOptions));
    }

    public function test_it_logs_job_queued_event(): void
    {
        $this->expectInfo('[queued-job] Queued job Larawelders\\QueueEventLogger\\Tests\\Feature\\FakeQueuedCommand on queue emails with delay 15');

        $this->app['events']->dispatch(
            new JobQueued(
                'redis',
                'emails',
                'queued-job',
                new FakeQueuedCommand,
                '{"displayName":"FakeQueuedCommand"}',
                15
            )
        );
    }

    public function test_it_logs_job_released_after_exception_event(): void
    {
        $this->expectWarning('[test-job] Released job after exception Tests\\Fixtures\\TestJob with backoff 30');

        $this->app['events']->dispatch(new JobReleasedAfterException('default', new FakeJob, 30));
    }

    public function test_it_logs_job_timed_out_event(): void
    {
        $this->expectError('[test-job] Timed out job Tests\\Fixtures\\TestJob');

        $this->app['events']->dispatch(new JobTimedOut('default', new FakeJob));
    }

    public function test_it_logs_looping_event(): void
    {
        $this->expectDebug('[worker] Looping');

        $this->app['events']->dispatch(new Looping('redis', 'emails'));
    }

    public function test_it_logs_worker_stopping_event(): void
    {
        $this->expectInfo('[worker] Stopping with status 12');

        $this->app['events']->dispatch(new WorkerStopping(12));
    }

    public function test_it_logs_queue_busy_event(): void
    {
        $this->expectWarning('[worker] Queue emails on connection redis is busy with 42 pending jobs');

        $this->app['events']->dispatch(new QueueBusy('redis', 'emails', 42));
    }

    public function test_it_logs_queue_failed_over_event(): void
    {
        $this->expectError(
            '[worker] Queue failover from connection redis for job Larawelders\\QueueEventLogger\\Tests\\Feature\\FakeQueuedCommand after Exception: failover'
        );

        $this->app['events']->dispatch(
            new QueueFailedOver('redis', new FakeQueuedCommand, new Exception('failover'))
        );
    }

    public function test_it_logs_queue_paused_event(): void
    {
        $this->expectInfo('[worker] Paused queue emails on connection redis for 60');

        $this->app['events']->dispatch(new QueuePaused('redis', 'emails', 60));
    }

    public function test_it_logs_queue_resumed_event(): void
    {
        $this->expectInfo('[worker] Resumed queue emails on connection redis');

        $this->app['events']->dispatch(new QueueResumed('redis', 'emails'));
    }

    private function expectInfo(string $message): void
    {
        Log::shouldReceive('channel')->once()->with('queue')->andReturnSelf();
        Log::shouldReceive('info')->once()->with($message);
    }

    private function expectError(string $message): void
    {
        Log::shouldReceive('channel')->once()->with('queue')->andReturnSelf();
        Log::shouldReceive('error')->once()->with($message);
    }

    private function expectDebug(string $message): void
    {
        Log::shouldReceive('channel')->once()->with('queue')->andReturnSelf();
        Log::shouldReceive('debug')->once()->with($message);
    }

    private function expectWarning(string $message): void
    {
        Log::shouldReceive('channel')->once()->with('queue')->andReturnSelf();
        Log::shouldReceive('warning')->once()->with($message);
    }
}

class FakeJob implements Job
{
    public function uuid(): ?string
    {
        return null;
    }

    public function getJobId(): string
    {
        return 'test-job';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [];
    }

    public function fire(): void
    {
    }

    /**
     * @param int $delay
     */
    public function release(mixed $delay = 0): void
    {
    }

    public function isReleased(): bool
    {
        return false;
    }

    public function delete(): void
    {
    }

    public function isDeleted(): bool
    {
        return false;
    }

    public function isDeletedOrReleased(): bool
    {
        return false;
    }

    public function attempts(): int
    {
        return 1;
    }

    public function hasFailed(): bool
    {
        return false;
    }

    public function markAsFailed(): void
    {
    }

    /**
     * @param Throwable|null $e
     */
    public function fail(mixed $e = null): void
    {
    }

    public function maxTries(): ?int
    {
        return null;
    }

    public function maxExceptions(): ?int
    {
        return null;
    }

    public function timeout(): ?int
    {
        return null;
    }

    public function retryUntil(): ?int
    {
        return null;
    }

    public function getName(): string
    {
        return 'Tests\\Fixtures\\TestJob';
    }

    public function resolveName(): string
    {
        return $this->getName();
    }

    public function resolveQueuedJobClass(): string
    {
        return $this->getName();
    }

    public function getConnectionName(): string
    {
        return 'default';
    }

    public function getQueue(): string
    {
        return 'default';
    }

    public function getRawBody(): string
    {
        return '{}';
    }
}

final class RecordingDispatcher implements \Illuminate\Contracts\Events\Dispatcher
{
    /**
     * @var list<array{0: array<mixed>|string|\Closure, 1: array<mixed>|string|\Closure|null}>
     */
    public array $listeners = [];

    /**
     * @param \Closure|string|array<mixed> $events
     * @param \Closure|string|array<mixed>|null $listener
     */
    public function listen($events, $listener = null)
    {
        $this->listeners[] = [$events, $listener];
    }

    public function hasListeners($eventName)
    {
        return false;
    }

    public function subscribe($subscriber)
    {
    }

    public function until($event, $payload = [])
    {
        return null;
    }

    /**
     * @param mixed $payload
     * @return array<mixed>|null
     */
    public function dispatch($event, $payload = [], $halt = false)
    {
        return null;
    }

    /**
     * @param array<mixed> $payload
     */
    public function push($event, $payload = [])
    {
    }

    public function flush($event)
    {
    }

    public function forget($event)
    {
    }

    public function forgetPushed()
    {
    }
}

final class FakeQueuedCommand
{
}
