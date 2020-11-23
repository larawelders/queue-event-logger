<?php

namespace Larawelders\QueueEventLogger;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class QueueEventLoggerServiceProvider extends ServiceProvider
{
    /**
     * Boot any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        Event::listen(JobExceptionOccurred::class, static function (JobExceptionOccurred $event) {
            Log::channel('queue')->error(
                sprintf(
                    '[%s] Uncaught exception %s in job %s: %s%s',
                    $event->job->getJobId(),
                    get_class($event->exception),
                    $event->job->resolveName(),
                    $event->exception->getMessage(),
                    property_exists($event->job, 'contextString') ? ', '.$event->job->contextString : ''
                )
            );
        });
    }
}
