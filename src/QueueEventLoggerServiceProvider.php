<?php

declare(strict_types=1);

namespace Larawelders\QueueEventLogger;

use Closure;
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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class QueueEventLoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/queue-event-logger.php', 'queue-event-logger');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/queue-event-logger.php' => config_path('queue-event-logger.php'),
        ], 'queue-event-logger-config');

        $channelName = $this->registerDefaultLogChannel();

        Queue::starting(static function (WorkerStarting $event) use ($channelName): void {
            Log::channel($channelName)->info(
                sprintf(
                    '[worker] Starting connection %s on queue %s',
                    $event->connectionName,
                    $event->queue
                )
            );
        });

        Queue::before(static function (JobProcessing $event) use ($channelName): void {
            Log::channel($channelName)->info(
                self::formatJobMessage('Processing job', $event->job)
            );
        });

        Queue::after(static function (JobProcessed $event) use ($channelName): void {
            Log::channel($channelName)->info(
                self::formatJobMessage('Processed job', $event->job)
            );
        });

        Queue::exceptionOccurred(static function (JobExceptionOccurred $event) use ($channelName): void {
            Log::channel($channelName)->error(
                self::formatExceptionMessage('Uncaught exception', $event->job, $event->exception)
            );
        });

        Queue::looping(static function (?Looping $event = null) use ($channelName): void {
            Log::channel($channelName)->debug('[worker] Looping');
        });

        Queue::failing(static function (JobFailed $event) use ($channelName): void {
            Log::channel($channelName)->error(
                self::formatExceptionMessage('Job failed', $event->job, $event->exception)
            );
        });

        Queue::stopping(static function (?WorkerStopping $event = null) use ($channelName): void {
            Log::channel($channelName)->info(
                sprintf(
                    '[worker] Stopping%s',
                    $event instanceof WorkerStopping ? sprintf(' with status %d', $event->status) : ''
                )
            );
        });

        Event::listen(JobQueued::class, static function (JobQueued $event) use ($channelName): void {
            Log::channel($channelName)->info(
                sprintf(
                    '[%s] Queued job %s on queue %s%s',
                    self::formatQueuedJobId($event->id),
                    self::formatQueuedJobName($event->job),
                    is_string($event->queue) ? $event->queue : 'default',
                    is_int($event->delay) ? sprintf(' with delay %d', $event->delay) : ''
                )
            );
        });

        Event::listen(JobReleasedAfterException::class, static function (JobReleasedAfterException $event) use ($channelName): void {
            Log::channel($channelName)->warning(
                sprintf(
                    '%s%s',
                    self::formatJobMessage('Released job after exception', $event->job),
                    is_int($event->backoff) ? sprintf(' with backoff %d', $event->backoff) : ''
                )
            );
        });

        Event::listen(JobTimedOut::class, static function (JobTimedOut $event) use ($channelName): void {
            Log::channel($channelName)->error(
                self::formatJobMessage('Timed out job', $event->job)
            );
        });

        Event::listen(QueueBusy::class, static function (QueueBusy $event) use ($channelName): void {
            Log::channel($channelName)->warning(
                sprintf(
                    '[worker] Queue %s on connection %s is busy with %d pending jobs',
                    $event->queue,
                    self::formatQueueConnection($event, 'connectionName', 'connection'),
                    $event->size
                )
            );
        });

        Event::listen(QueueFailedOver::class, static function (QueueFailedOver $event) use ($channelName): void {
            Log::channel($channelName)->error(
                sprintf(
                    '[worker] Queue failover from connection %s for job %s after %s: %s',
                    is_string($event->connectionName) ? $event->connectionName : 'unknown',
                    self::formatQueuedJobName($event->command),
                    get_class($event->exception),
                    $event->exception->getMessage()
                )
            );
        });

        Event::listen(QueuePaused::class, static function (QueuePaused $event) use ($channelName): void {
            Log::channel($channelName)->info(
                sprintf(
                    '[worker] Paused queue %s on connection %s%s',
                    $event->queue,
                    $event->connection,
                    self::formatOptionalPauseTtl($event->ttl)
                )
            );
        });

        Event::listen(QueueResumed::class, static function (QueueResumed $event) use ($channelName): void {
            Log::channel($channelName)->info(
                sprintf(
                    '[worker] Resumed queue %s on connection %s',
                    $event->queue,
                    $event->connection
                )
            );
        });
    }

    private function registerDefaultLogChannel(): string
    {
        $channelName = (string) config('queue-event-logger.channel', 'queue');

        if (! config()->has("logging.channels.{$channelName}")) {
            config()->set(
                "logging.channels.{$channelName}",
                config('queue-event-logger.channel_config', [])
            );
        }

        return $channelName;
    }

    private static function formatJobMessage(string $action, Job $job): string
    {
        return sprintf(
            '[%s] %s %s',
            $job->getJobId(),
            $action,
            $job->resolveName()
        );
    }

    private static function formatExceptionMessage(string $action, Job $job, \Throwable $exception): string
    {
        return sprintf(
            '[%s] %s %s in job %s: %s',
            $job->getJobId(),
            $action,
            get_class($exception),
            $job->resolveName(),
            $exception->getMessage()
        );
    }

    private static function formatQueuedJobName(mixed $job): string
    {
        if ($job instanceof Closure) {
            return Closure::class;
        }

        if (is_object($job)) {
            return get_class($job);
        }

        return (string) $job;
    }

    private static function formatQueuedJobId(mixed $id): string
    {
        return is_string($id) || is_int($id) ? (string) $id : 'pending';
    }

    private static function formatOptionalPauseTtl(mixed $ttl): string
    {
        if (! is_int($ttl) && ! $ttl instanceof \DateInterval && ! $ttl instanceof \DateTimeInterface) {
            return '';
        }

        return sprintf(' for %s', self::formatPauseTtl($ttl));
    }

    private static function formatPauseTtl(mixed $ttl): string
    {
        if ($ttl instanceof \DateInterval) {
            return $ttl->format('P%yY%mM%dDT%hH%iM%sS');
        }

        if ($ttl instanceof \DateTimeInterface) {
            return $ttl->format(DATE_ATOM);
        }

        return (string) $ttl;
    }

    private static function formatQueueConnection(object $event, string ...$propertyNames): string
    {
        foreach ($propertyNames as $propertyName) {
            if (property_exists($event, $propertyName) && is_string($event->{$propertyName})) {
                return $event->{$propertyName};
            }
        }

        return 'unknown';
    }
}
