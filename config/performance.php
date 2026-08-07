<?php

return [
    'background_jobs' => [
        'connection' => env('CMIH_BACKGROUND_QUEUE_CONNECTION', 'deferred'),
    ],

    'slow_requests' => [
        'enabled' => env('SLOW_REQUEST_LOGGING', true),
        'min_duration_ms' => (int) env('SLOW_REQUEST_MIN_MS', 1500),
        'min_query_count' => (int) env('SLOW_REQUEST_MIN_QUERIES', 80),
        'min_query_time_ms' => (int) env('SLOW_REQUEST_MIN_QUERY_MS', 800),
        'paths' => [
            'portal*',
            'dashboard*',
            'merchandisers*',
        ],
    ],
];
