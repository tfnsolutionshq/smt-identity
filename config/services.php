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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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
    'memo' => [
        'base_url' => env('MEMO_SERVICE_BASE_URL', 'http://127.0.0.1:8001/api/v1'),
    ],

    'setting' => [
        'base_url' => env('SETTING_SERVICE_BASE_URL', 'http://127.0.0.1:8002/api/v1'),
    ],
    'notification' => [
        'base_url' => env('NOTIFICATION_SERVICE_BASE_URL', 'http://127.0.0.1:8003/api/v1'),
    ],
    'api_key' => env('SERVICE_API_KEY', 'default-secret-key-123'),

    'integration' => [
        'active' => env('ACTIVE_INTEGRATION'),
    ],

];
