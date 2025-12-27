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

    'csv' => [
        'signing_key' => env('CSV_SIGNING_KEY'),
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'models' => [
            'primary' => env('OPENROUTER_MODEL_PRIMARY', 'google/gemini-2.0-flash-exp:free'),
            'fallback_1' => env('OPENROUTER_MODEL_FALLBACK_1', 'xiaomi/mimo-v2-flash:free'),
            'fallback_2' => env('OPENROUTER_MODEL_FALLBACK_2', 'meta-llama/llama-3.2-3b-instruct:free'),
        ],
        'timeout_seconds' => env('OPENROUTER_TIMEOUT', 10),
        'max_retries' => env('OPENROUTER_MAX_RETRIES', 2),
    ],

];
