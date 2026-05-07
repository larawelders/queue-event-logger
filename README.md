# Laravel Queue Event Logger

Log Laravel queue worker lifecycle events to a dedicated `queue` log channel.

## Support Matrix

| Package | PHP | Laravel |
| ------- | --- | ------- |
| `2.x` | `8.2+` | `12.x` / `13.x` |

Notes:

- Laravel 12 supports PHP `8.2` through `8.5`.
- Laravel 13 requires PHP `8.3+`, so PHP `8.2` is only valid with Laravel 12.

## What It Logs

- `JobProcessing`
- `JobProcessed`
- `JobQueued`
- `JobExceptionOccurred`
- `JobReleasedAfterException`
- `JobFailed`
- `JobTimedOut`
- `Looping`
- `QueueBusy`
- `QueueFailedOver`
- `QueuePaused`
- `QueueResumed`
- `WorkerStarting`
- `WorkerStopping`

## Installation

```bash
composer require larawelders/queue-event-logger
```

The package uses Laravel package discovery, so no manual provider registration is required.

The package registers a default `queue` log channel at runtime if your application does not already define one. By default, it uses a `daily` channel that writes to date-named files under `storage/logs/queue.log`.

If you want to customize the channel name or logger configuration, publish the package config:

```bash
php artisan vendor:publish --tag=queue-event-logger-config
```

The published config lets you change both the channel name and the underlying channel definition:

```php
'channel' => 'queue',

'channel_config' => [
    'driver' => 'daily',
    'path' => storage_path('logs/queue.log'),
    'level' => 'debug',
],
```

If your application already defines a channel with the configured name in `config/logging.php`, the application definition takes precedence:

```php
'channels' => [
    'queue' => [
        'driver' => 'single',
        'path' => storage_path('logs/app-queue.log'),
        'level' => 'info',
    ],
],
```

## Example Output

```text
[8f4d4f2e] Processing job App\Jobs\ProcessWebhook
[8f4d4f2e] Processed job App\Jobs\ProcessWebhook
[8f4d4f2e] Queued job App\Jobs\ProcessWebhook on queue webhooks with delay 30
[8f4d4f2e] Uncaught exception RuntimeException in job App\Jobs\ProcessWebhook: API timeout
[8f4d4f2e] Released job after exception App\Jobs\ProcessWebhook with backoff 30
[8f4d4f2e] Job failed RuntimeException in job App\Jobs\ProcessWebhook: API timeout
[8f4d4f2e] Timed out job App\Jobs\ProcessWebhook
[worker] Looping
[worker] Queue webhooks on connection redis is busy with 250 pending jobs
[worker] Queue failover from connection redis for job App\Jobs\ProcessWebhook after RuntimeException: Redis down
[worker] Paused queue webhooks on connection redis for 60
[worker] Resumed queue webhooks on connection redis
[worker] Starting connection redis on queue webhooks
[worker] Stopping with status 0
```

## Development

```bash
composer install
composer run test
composer run analyse
```
