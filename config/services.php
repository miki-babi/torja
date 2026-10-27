<?php

return [

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

    'chapa' => [
        'secret_key' => env('CHAPA_SECRET_KEY'),
        'public_key' => env('CHAPA_PUBLIC_KEY'),
        'webhook_secret' => env('CHAPA_WEBHOOK_SECRET'),
        'base_url' => env('CHAPA_BASE_URL', 'https://api.chapa.co/v1'),
    ],

    'verify_checkout' => [
        'api_key' => env('VERIFY_CHECKOUT_API_KEY'),
        'webhook_secret' => env('VERIFY_CHECKOUT_WEBHOOK_SECRET'),
        'base_url' => env('VERIFY_CHECKOUT_BASE_URL', 'https://checkoutapi.verify.et'),
        'version' => '2026-06-01',
    ],

    'jami' => [
        'token' => env('JAMI_TOKEN'),
        'webhook_secret' => env('JAMI_WEBHOOK_SECRET'),
        'base_url' => env('JAMI_BASE_URL', 'https://jami.bio'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

];
