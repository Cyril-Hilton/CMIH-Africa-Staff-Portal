<?php

return [
    'non_working_dates' => array_values(array_filter(array_map('trim', explode(',', env('CMIH_NON_WORKING_DATES', ''))))),
    'clock_in_cutoff' => env('CMIH_CLOCK_IN_CUTOFF', '09:00:00'),
];
