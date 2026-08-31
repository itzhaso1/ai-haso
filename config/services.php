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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

    'google_ai_studio' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GOOGLE_AI_STUDIO_MODEL', env('GEMINI_MODEL', 'gemini-2.5-flash')),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'whatsapp' => [
        'token' => env('WHATSAPP_PERMANENT_TOKEN'),
    ],

    'namecheap' => [
        'env' => env('NAMECHEAP_ENV', 'sandbox'),
        'api_user' => env('NAMECHEAP_API_USER'),
        'api_key' => env('NAMECHEAP_API_KEY'),
        'username' => env('NAMECHEAP_USERNAME'),
        'client_ip' => env('NAMECHEAP_CLIENT_IP'),
        'timeout' => (int) env('NAMECHEAP_TIMEOUT', 20),
        'connect_timeout' => (int) env('NAMECHEAP_CONNECT_TIMEOUT', 8),
        'base_url_sandbox' => 'https://api.sandbox.namecheap.com/xml.response',
        'base_url_production' => 'https://api.namecheap.com/xml.response',
    ],

];
