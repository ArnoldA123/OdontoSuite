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

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
        'max_tokens' => env('OPENAI_MAX_TOKENS', 1000),
        'timeout' => env('OPENAI_TIMEOUT', 30),
        // #296 — what the service costs and how much of it a patient may use.
        // USD per 1000 tokens, four decimals in the row. The daily limit is a
        // clinic policy: 0 means no cap, and no number is invented here.
        'price_per_1k_tokens' => env('OPENAI_PRICE_PER_1K_TOKENS', 0.01),
        'daily_analysis_limit_per_patient' => env('OPENAI_DAILY_ANALYSIS_LIMIT_PER_PATIENT', 0),
    ],

];
