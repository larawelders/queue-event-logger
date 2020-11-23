<?php

namespace Larawelders\QueueEventLogger\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Larawelders\QueueEventLogger\QueueEventLoggerServiceProvider;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../vendor/laravel/laravel/bootstrap/app.php';

        $app->booting(static function () use ($app): void {
            $app->register(QueueEventLoggerServiceProvider::class);
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
