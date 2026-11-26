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
        // Safest mode never walks further than max(shortest × ratio, shortest + extra).
        'safest_max_detour_ratio' => (float) env('CFG_ROUTE_MAX_DETOUR_RATIO', 1.5),
        'safest_max_detour_extra_m' => (float) env('CFG_ROUTE_MAX_DETOUR_EXTRA_M', 500),
        // Safest may pass up to N active reports when avoiding each would cost more than
        // report_penalty_m (WSM-weighted metres). 0 = always avoid reports when possible.
        'report_tolerance' => (int) env('CFG_ROUTE_REPORT_TOLERANCE', 3),
        'report_penalty_m' => (float) env('CFG_ROUTE_REPORT_PENALTY_M', 300),
        // WSM cost: length × (1 + ((100 - score) / 100) × penalty).
        'score_penalty' => (float) env('CFG_ROUTE_SCORE_PENALTY', 3.0),
        'unscored_score' => (float) env('CFG_ROUTE_UNSCORED_SCORE', 70),
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

    // A* routing over the imported OSM pedestrian graph in segmen_jalur (F7).
    'routing_graph' => [
        // auto: graph A* → Valhalla → OSRM; graph | valhalla | osrm: force one engine (valhalla falls back to OSRM on outage).
        'engine' => env('CFG_ROUTE_ENGINE', 'auto'),
        'snap_m' => (float) env('CFG_ROUTE_GRAPH_SNAP_M', 100),
        'min_edges' => (int) env('CFG_ROUTE_GRAPH_MIN_EDGES', 50),
        'repair_penalty' => (float) env('CFG_ROUTE_REPAIR_PENALTY', 1.35),
        // Fallback multiplier for edges inside an active report zone when no clear path fits the cap.
        'report_zone_penalty' => (float) env('CFG_ROUTE_REPORT_ZONE_PENALTY', 10),
    ],

    // Valhalla pedestrian engine (FOSSGIS public server: max 1 request/second).
    'valhalla' => [
        'url' => env('VALHALLA_URL', 'https://valhalla1.openstreetmap.de'),
        'snap_radius_m' => 100,
        'cache_seconds' => 1800,
        'max_exclude_polygons' => 50,
        // Safest: avoid the reports hit so far, re-route, repeat (each round = 1 request).
        'exclusion_rounds' => 5,
        // Balance: extra requests that each re-open one report group (≤ tolerance).
        'balance_probes' => 6,
        'safest' => [
            'walkway_factor' => (float) env('CFG_VALHALLA_WALKWAY_FACTOR', 0.8),
            'sidewalk_factor' => (float) env('CFG_VALHALLA_SIDEWALK_FACTOR', 0.8),
            'alley_factor' => (float) env('CFG_VALHALLA_ALLEY_FACTOR', 1.0),
            'use_lit' => (float) env('CFG_VALHALLA_USE_LIT', 0.5),
            'use_living_streets' => (float) env('CFG_VALHALLA_USE_LIVING_STREETS', 0.8),
        ],
    ],

    'osm' => [
        'overpass_url' => env('OSM_OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
    ],

    'google_maps' => [
        // Browser key for the Maps JavaScript API; restrict it by HTTP referrer in Google Cloud.
        'key' => env('GOOGLE_MAPS_API_KEY'),
        'type' => env('GOOGLE_MAPS_TYPE', 'roadmap'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_SECRET_ID') ?: env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', 'http://localhost:8000/api/v1/auth/google/callback'),
        'mock' => filter_var(env('GOOGLE_OAUTH_MOCK', false), FILTER_VALIDATE_BOOLEAN),
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
