<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ga4' => [
        'id' => env('GA4_MEASUREMENT_ID'),
    ],

    'sms' => [
        'default' => env('SMS_DEFAULT_PROVIDER', 'mnotify'),
    ],

    'mnotify' => [
        'api_key' => env('MNOTIFY_API_KEY'),
        'sender_id' => env('MNOTIFY_SENDER_ID', 'CMIH'),
        'endpoint' => env('MNOTIFY_ENDPOINT', 'https://api.mnotify.com/api/sms/quick'),
    ],

    'dropbox' => [
        'access_token' => env('DROPBOX_ACCESS_TOKEN'),
        'app_key' => env('DROPBOX_APP_KEY'),
        'app_secret' => env('DROPBOX_APP_SECRET'),
        'refresh_token' => env('DROPBOX_REFRESH_TOKEN'),
    ],

    'google' => [
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'vision_model' => env('OPENAI_VISION_MODEL', env('AI_MODEL', 'gpt-4o-mini')),
        'sku_confidence_threshold' => (float) env('SKU_AI_CONFIDENCE_THRESHOLD', env('AI_REVIEW_THRESHOLD', 0.75)),
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'auto'),
        'model' => env('AI_MODEL', env('OPENAI_VISION_MODEL', 'gpt-4o-mini')),
        'review_threshold' => (float) env('AI_REVIEW_THRESHOLD', env('SKU_AI_CONFIDENCE_THRESHOLD', 0.75)),
        'async' => filter_var(env('AI_ASYNC', false), FILTER_VALIDATE_BOOL),
        'ca_bundle' => env('AI_CA_BUNDLE', env('CURL_CA_BUNDLE', env('SSL_CERT_FILE'))),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_API_BASE_URL', 'https://generativelanguage.googleapis.com'),
        'model' => env('GEMINI_MODEL', env('WARDROBE_AI_GEMINI_MODEL', 'gemini-2.5-flash')),
    ],

    'ip_geolocation' => [
        'api_key' => env('IP_GEOLOCATION_API_KEY'),
    ],

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
    ],

];
