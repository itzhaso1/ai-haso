<?php

return [
    'types' => [
        'individual',
        'company',
        'store',
    ],

    /*
    |--------------------------------------------------------------------------
    | Base feature matrix
    |--------------------------------------------------------------------------
    |
    | The final access decision is:
    | workspace type defaults + active plan features + per-workspace overrides.
    |
    */
    'features_by_type' => [
        'individual' => [
            'conversations',
            'smart_replies',
            'ai',
            'subscription',
            'usage',
            'whatsapp',
        ],
        'company' => [
            'dashboard',
            'products',
            'categories',
            'inventory',
            'customers',
            'orders',
            'conversations',
            'messages',
            'smart_replies',
            'ai',
            'payments',
            'payment_gateway',
            'finance',
            'employees',
            'roles_permissions',
            'subscription',
            'analytics',
            'whatsapp',
        ],
        'store' => [
            'dashboard',
            'products',
            'categories',
            'inventory',
            'customers',
            'orders',
            'conversations',
            'messages',
            'smart_replies',
            'ai',
            'payments',
            'payment_gateway',
            'finance',
            'employees',
            'roles_permissions',
            'subscription',
            'analytics',
            'whatsapp',
        ],
    ],
];
