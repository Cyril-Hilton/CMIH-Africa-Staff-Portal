<?php
return [
    'upload_scanner' => env('CREATIVE_UPLOAD_SCANNER'),
    'upload_scan_required' => env('CREATIVE_UPLOAD_SCAN_REQUIRED', env('APP_ENV') === 'production'),
    'email_enabled' => env('CREATIVE_EMAIL_ENABLED', true),
    'review_business_days' => 2,
    'review_kpi_weight' => 10,
    'timezone' => 'Africa/Accra',
    'stages' => ['backlog'=>'Backlog','brief_received'=>'Brief Received','needs_info'=>'Needs Information','scheduled'=>'Scheduled','in_progress'=>'In Progress','in_review'=>'Awaiting Review','changes_requested'=>'Changes Requested','delivered'=>'Approved for Delivery','completed'=>'Verified & Completed','archived'=>'Archived'],
    'types' => ['2D Mockup','3D Mockup','4D Mockup','Flyer / Poster','Animation','Videography','Photography','Website Design','Brand Guide','Print File','Brand Media Asset','Presentation','Key Visual / Campaign Artwork','POSM / Retail Adaptation','Event Branding / Signage','Other'],
];
