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

    'departments' => ['brands_marketing' => 'Brands / Marketing', 'client_relations' => 'Client Relations', 'operations_projects' => 'Operations / Projects', 'creatives' => 'Creative', 'finance' => 'Finance', 'hr_admin' => 'HR / Admin'],
    'score_weights' => ['punctuality' => 10, 'completion' => 65, 'kpi' => 25],
    'score_effective_from' => '2026-10-01',
    'dimensions' => ['target_achievement', 'timeliness', 'quality', 'financial', 'stakeholder', 'issue_resolution', 'compliance', 'process_discipline'],
    'root_causes' => ['capacity', 'resources', 'process_delay', 'client_dependency', 'financial_constraint', 'cross_function_dependency', 'quality_rework', 'other'],
    // Values are supplied by management on KPI creation, not assumed business targets.
    'exceptions' => ['repeated_failure_periods' => (int) env('PERFORMANCE_REPEATED_FAILURE_PERIODS', 3), 'budget_tolerance_percent' => (float) env('PERFORMANCE_BUDGET_TOLERANCE_PERCENT', 10), 'executive_after_days' => (int) env('PERFORMANCE_ESCALATION_DAYS', 7)],
    'email_enabled' => env('PERFORMANCE_EMAIL_ENABLED', false),
];
