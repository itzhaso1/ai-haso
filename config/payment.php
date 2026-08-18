<?php

return [
    'default_provider' => env('PAYMENT_DEFAULT_PROVIDER', 'local'),

    'providers' => [
        'local' => [
            'enabled' => true,
        ],
        'stripe' => [
            'enabled' => (bool) env('STRIPE_ENABLED', false),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
    ],
];
