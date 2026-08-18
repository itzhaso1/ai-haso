<?php

return [
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'openai'),
    'openai' => [
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'temperature' => (float) env('OPENAI_TEMPERATURE', 0.4),
        'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 512),
    ],
];
