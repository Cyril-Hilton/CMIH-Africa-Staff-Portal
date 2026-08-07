<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CVO Name
    |--------------------------------------------------------------------------
    | The name of the Chief Visionary Officer used on award certificates and
    | official communications. Set CVO_NAME in .env once the CVO creates their
    | account — this acts as a fallback constant in the interim.
    */
    'cvo_name' => env('CVO_NAME', 'Solomon Nanfa'),

    /*
    |--------------------------------------------------------------------------
    | Split Application Mode
    |--------------------------------------------------------------------------
    |
    | "all" keeps the current monolith behavior. The split workspace can set
    | this to "website", "staff", or "brands" so each deployment only exposes
    | the section it owns while redirecting users to the right subdomain.
    |
    */
    'app_kind' => env('CMIH_APP_KIND', 'all'),

    'urls' => [
        'website' => env('CMIH_WEBSITE_URL', 'https://www.cmih.africa'),
        'staff' => env('CMIH_STAFF_PORTAL_URL', 'https://portal.cmih.africa'),
        'brands' => env('CMIH_BRANDS_PORTAL_URL', 'https://brands.cmih.africa'),
    ],
];
