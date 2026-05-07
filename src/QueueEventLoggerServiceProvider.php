<?php

declare(strict_types=1);

namespace Larawelders\QueueEventLogger;

use Illuminate\Support\Facades\Event;
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

        $this->registerDefaultLogChannel();

        Event::subscribe(QueueEventLoggerSubscriber::class);
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
}
