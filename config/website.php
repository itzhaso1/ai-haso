<?php

return [
    'platform_domain' => env('WEBSITE_PLATFORM_DOMAIN'),
    'dns_target' => env('WEBSITE_DNS_TARGET'),
    'dns_target_type' => env('WEBSITE_DNS_TARGET_TYPE', 'A'),
    'dns_ttl' => (int) env('WEBSITE_DNS_TTL', 300),
    'dns_www_target' => env('WEBSITE_DNS_WWW_TARGET', '@'),
    'preview_url_ttl_minutes' => (int) env('WEBSITE_PREVIEW_URL_TTL_MINUTES', 120),
    'resolver_cache_ttl_seconds' => (int) env('WEBSITE_RESOLVER_CACHE_TTL_SECONDS', 300),
    'public_rate_limit' => env('WEBSITE_PUBLIC_RATE_LIMIT', '60,1'),
    'domain_search_tlds' => array_filter(array_map('trim', explode(',', (string) env('WEBSITE_DOMAIN_SEARCH_TLDS', 'com,net,org,clinic,care,health,law,pro')))),
    'domain_markup_percent' => (float) env('WEBSITE_DOMAIN_MARKUP_PERCENT', 0),
];
