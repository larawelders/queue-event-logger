<?php

declare(strict_types=1);

return [
    'channel' => 'queue',

    'channel_config' => [
        'driver' => 'daily',
        'path' => storage_path('logs/queue.log'),
        'level' => 'debug',
    ],
];
