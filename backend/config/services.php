<?php

return [

    'places' => [
        'url' => env('PLACE_SEARCH_URL', 'https://photon.komoot.io/api/'),
    ],

    'osrm' => [
        'url' => env('OSRM_BASE_URL', 'https://routing.openstreetmap.de/routed-foot/route/v1/foot'),
        // Set only after extracting a self-hosted dataset with OSRM's foot.lua.
        'foot_profile_verified' => (bool) env('OSRM_FOOT_PROFILE_VERIFIED', false),
        'max_snap_m' => 500.0,
        'waypoint_snap_m' => 40.0,
        'avoid_radius_m' => 25.0,
        'search_requests' => 48,
        'public_search_requests' => 16,
        'cache_seconds' => 1800,
        'residential_detours' => true,
        'map_data_url' => 'https://api.openstreetmap.org/api/0.6',
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_SECRET_ID') ?: env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', 'http://localhost:8000/api/v1/auth/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', 'http://localhost:8000/api/v1/auth/facebook/callback'),
    ],

    'cv' => [
        'enabled' => env('CFG_CV_ENABLED', true),
        'url' => env('CV_SERVICE_URL', 'http://cv-service:8000'),
        'threshold' => (float) env('CFG_CV_THRESHOLD', 0.80),
        'timeout' => (int) env('CFG_CV_TIMEOUT_SECONDS', 10),
        'categories' => array_map('trim', explode(',', env('CFG_CV_CATEGORIES', 'Trotoar Rusak,Masalah Drainase,Guiding Block Rusak,Parkir Liar'))),
        'mock' => env('CV_MOCK', false),
    ],

];
