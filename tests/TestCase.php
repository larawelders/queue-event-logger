<?php

declare(strict_types=1);

namespace Larawelders\QueueEventLogger\Tests;

use Larawelders\QueueEventLogger\QueueEventLoggerServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            QueueEventLoggerServiceProvider::class,
        ];
    }
}
