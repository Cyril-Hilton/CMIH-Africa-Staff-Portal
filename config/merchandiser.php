<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Merchandiser Public Holidays / Blocked Route Dates
    |--------------------------------------------------------------------------
    |
    | Add YYYY-MM-DD dates here, or set MERCHANDISER_PUBLIC_HOLIDAYS as a
    | comma-separated list. The route planner skips these dates when generating
    | automatic outlet assignments.
    |
    */

    'public_holidays' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('MERCHANDISER_PUBLIC_HOLIDAYS', ''))
    ))),
];
