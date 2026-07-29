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

    'jwt' => [
        'secret' => env('JWT_SECRET', 'local-dev-secret-for-lazymanager'),
        'issuer' => env('JWT_ISSUER', 'lazymanager-people-service'),
        'audience' => env('JWT_AUDIENCE', 'lazymanager'),
        'ttl_minutes' => (int) env('JWT_TTL_MINUTES', 15),
    ],

    'auth_cookies' => [
        'access_cookie' => env('AUTH_ACCESS_COOKIE', 'lm_access_token'),
        'refresh_cookie' => env('AUTH_REFRESH_COOKIE', 'lm_refresh_token'),
        'csrf_cookie' => env('AUTH_CSRF_COOKIE', 'lm_csrf_token'),
        'access_ttl_minutes' => (int) env('AUTH_ACCESS_TTL_MINUTES', env('JWT_TTL_MINUTES', 15)),
        'refresh_ttl_minutes' => (int) env('AUTH_REFRESH_TTL_MINUTES', 10080),
        'secure' => (bool) env('AUTH_COOKIE_SECURE', false),
        'same_site' => env('AUTH_COOKIE_SAME_SITE', 'lax'),
    ],

];
