<?php

declare(strict_types=1);

namespace Larawelders\QueueEventLogger;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
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
use Illuminate\Support\Facades\Log;

class QueueEventLoggerSubscriber
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(WorkerStarting::class, [static::class, 'handleWorkerStarting']);
        $events->listen(JobProcessing::class, [static::class, 'handleJobProcessing']);
        $events->listen(JobProcessed::class, [static::class, 'handleJobProcessed']);
        $events->listen(JobExceptionOccurred::class, [static::class, 'handleJobExceptionOccurred']);
        $events->listen(Looping::class, [static::class, 'handleLooping']);
        $events->listen(JobFailed::class, [static::class, 'handleJobFailed']);
        $events->listen(WorkerStopping::class, [static::class, 'handleWorkerStopping']);
        $events->listen(JobQueued::class, [static::class, 'handleJobQueued']);
        $events->listen(JobReleasedAfterException::class, [static::class, 'handleJobReleasedAfterException']);
        $events->listen(JobTimedOut::class, [static::class, 'handleJobTimedOut']);
        $events->listen(QueueBusy::class, [static::class, 'handleQueueBusy']);
        $events->listen(QueueFailedOver::class, [static::class, 'handleQueueFailedOver']);
        $events->listen(QueuePaused::class, [static::class, 'handleQueuePaused']);
        $events->listen(QueueResumed::class, [static::class, 'handleQueueResumed']);
    }

    public function handleWorkerStarting(WorkerStarting $event): void
    {
        $this->logInfo(sprintf(
            '[worker] Starting connection %s on queue %s',
            $event->connectionName,
            $event->queue
        ));
    }

    public function handleJobProcessing(JobProcessing $event): void
    {
        $this->logInfo(static::formatJobMessage('Processing job', $event->job));
    }

    public function handleJobProcessed(JobProcessed $event): void
    {
        $this->logInfo(static::formatJobMessage('Processed job', $event->job));
    }

    public function handleJobExceptionOccurred(JobExceptionOccurred $event): void
    {
        $this->logError(static::formatExceptionMessage('Uncaught exception', $event->job, $event->exception));
    }

    public function handleLooping(?Looping $event = null): void
    {
        $this->logDebug('[worker] Looping');
    }

    public function handleJobFailed(JobFailed $event): void
    {
        $this->logError(static::formatExceptionMessage('Job failed', $event->job, $event->exception));
    }

    public function handleWorkerStopping(?WorkerStopping $event = null): void
    {
        $this->logInfo(sprintf(
            '[worker] Stopping%s',
            $event instanceof WorkerStopping ? sprintf(' with status %d', $event->status) : ''
        ));
    }

    public function handleJobQueued(JobQueued $event): void
    {
        $this->logInfo(sprintf(
            '[%s] Queued job %s on queue %s%s',
            static::formatQueuedJobId($event->id),
            static::formatQueuedJobName($event->job),
            is_string($event->queue) ? $event->queue : 'default',
            is_int($event->delay) ? sprintf(' with delay %d', $event->delay) : ''
        ));
    }

    public function handleJobReleasedAfterException(JobReleasedAfterException $event): void
    {
        $this->logWarning(sprintf(
            '%s%s',
            static::formatJobMessage('Released job after exception', $event->job),
            is_int($event->backoff) ? sprintf(' with backoff %d', $event->backoff) : ''
        ));
    }

    public function handleJobTimedOut(JobTimedOut $event): void
    {
        $this->logError(static::formatJobMessage('Timed out job', $event->job));
    }

    public function handleQueueBusy(QueueBusy $event): void
    {
        $this->logWarning(sprintf(
            '[worker] Queue %s on connection %s is busy with %d pending jobs',
            $event->queue,
            static::formatQueueConnection($event, 'connectionName', 'connection'),
            $event->size
        ));
    }

    public function handleQueueFailedOver(QueueFailedOver $event): void
    {
        $this->logError(sprintf(
            '[worker] Queue failover from connection %s for job %s after %s: %s',
            is_string($event->connectionName) ? $event->connectionName : 'unknown',
            static::formatQueuedJobName($event->command),
            get_class($event->exception),
            $event->exception->getMessage()
        ));
    }

    public function handleQueuePaused(QueuePaused $event): void
    {
        $this->logInfo(sprintf(
            '[worker] Paused queue %s on connection %s%s',
            $event->queue,
            $event->connection,
            static::formatOptionalPauseTtl($event->ttl)
        ));
    }

    public function handleQueueResumed(QueueResumed $event): void
    {
        $this->logInfo(sprintf(
            '[worker] Resumed queue %s on connection %s',
            $event->queue,
            $event->connection
        ));
    }

    private function logInfo(string $message): void
    {
        Log::channel($this->channelName())->info($message);
    }

    private function logError(string $message): void
    {
        Log::channel($this->channelName())->error($message);
    }

    private function logDebug(string $message): void
    {
        Log::channel($this->channelName())->debug($message);
    }

    private function logWarning(string $message): void
    {
        Log::channel($this->channelName())->warning($message);
    }

    private function channelName(): string
    {
        return (string) config('queue-event-logger.channel', 'queue');
    }

    protected static function formatJobMessage(string $action, Job $job): string
    {
        return sprintf(
            '[%s] %s %s',
            $job->getJobId(),
            $action,
            $job->resolveName()
        );
    }

    protected static function formatExceptionMessage(string $action, Job $job, \Throwable $exception): string
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

    protected static function formatQueuedJobName(mixed $job): string
    {
        if ($job instanceof Closure) {
            return Closure::class;
        }

        if (is_object($job)) {
            return get_class($job);
        }

        return (string) $job;
    }

    protected static function formatQueuedJobId(mixed $id): string
    {
        return is_string($id) || is_int($id) ? (string) $id : 'pending';
    }

    protected static function formatOptionalPauseTtl(mixed $ttl): string
    {
        if (! is_int($ttl) && ! $ttl instanceof \DateInterval && ! $ttl instanceof \DateTimeInterface) {
            return '';
        }

        return sprintf(' for %s', static::formatPauseTtl($ttl));
    }

    protected static function formatPauseTtl(mixed $ttl): string
    {
        if ($ttl instanceof \DateInterval) {
            return $ttl->format('P%yY%mM%dDT%hH%iM%sS');
        }

        if ($ttl instanceof \DateTimeInterface) {
            return $ttl->format(DATE_ATOM);
        }

        return (string) $ttl;
    }

    protected static function formatQueueConnection(object $event, string ...$propertyNames): string
    {
        foreach ($propertyNames as $propertyName) {
            if (property_exists($event, $propertyName) && is_string($event->{$propertyName})) {
                return $event->{$propertyName};
            }
        }

        return 'unknown';
    }
}
