<?php

return [
    'enabled' => env('API_BENCHMARKING_ENABLED', false),

    'log_channel' => env('API_BENCHMARK_LOG_CHANNEL', 'benchmark'),

    'slow_request_threshold_ms' => (float) env('API_BENCHMARK_SLOW_REQUEST_MS', 800),

    'slow_query_threshold_ms' => (float) env('API_BENCHMARK_SLOW_QUERY_MS', 250),
];
