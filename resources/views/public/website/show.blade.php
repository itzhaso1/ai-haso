@php
    $direction = $theme['direction'] ?? 'rtl';
    $isRtl = $direction === 'rtl';
    $primaryColor = $theme['primary_color'] ?? '#0f766e';
    $secondaryColor = $theme['secondary_color'] ?? '#14b8a6';
    $fontFamily = $theme['font'] ?? 'Cairo';
    $pageSlug = $current_page?->slug ?? 'home';
    $isHostedMode = request()->attributes->has('website');
    $homeUrl = $isHostedMode ? url('/') : route('public.website.show', $website->slug);
    $bookingUrl = $isHostedMode ? url('/booking') : route('public.website.page', [$website->slug, 'booking']);
    $contactUrl = $isHostedMode ? url('/contact') : route('public.website.page', [$website->slug, 'contact']);
@endphp
<!DOCTYPE html>
<html lang="{{ $isRtl ? 'ar' : 'en' }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seo['title'] ?? $website->name }}</title>
    @if(!empty($seo['description']))
        <meta name="description" content="{{ $seo['description'] }}">
    @endif
    @if(!empty($seo['canonical']))
        <link rel="canonical" href="{{ $seo['canonical'] }}">
    @endif
    @if(!empty($seo['robots']))
        <meta name="robots" content="{{ $seo['robots'] }}">
    @endif
    @if(!empty($seo['og_title']))
        <meta property="og:title" content="{{ $seo['og_title'] }}">
    @endif
    @if(!empty($seo['og_description']))
        <meta property="og:description" content="{{ $seo['og_description'] }}">
    @endif
    @if(!empty($seo['favicon']))
        <link rel="icon" href="{{ $seo['favicon'] }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --ws-primary: {{ $primaryColor }};
            --ws-secondary: {{ $secondaryColor }};
            --ws-font: "{{ $fontFamily }}", system-ui, -apple-system, sans-serif;
        }
        body { font-family: var(--ws-font); }
        .ws-btn { background-color: var(--ws-primary); color: #fff; }
        .ws-btn:hover { background-color: color-mix(in srgb, var(--ws-primary), #000 10%); }
        .ws-accent { color: var(--ws-primary); }
        .ws-bg-soft { background-color: color-mix(in srgb, var(--ws-primary), #fff 92%); }
        .ws-border { border-color: color-mix(in srgb, var(--ws-primary), #fff 75%); }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <a href="{{ $homeUrl }}" class="text-base font-bold ws-accent">
                {{ $settings['business_name'] ?? $website->name }}
            </a>
            <nav class="flex items-center gap-2 text-sm">
                <a href="{{ $homeUrl }}" class="rounded-lg px-3 py-1.5 {{ $pageSlug === 'home' ? 'ws-bg-soft ws-accent font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">Home</a>
                <a href="{{ $bookingUrl }}" class="rounded-lg px-3 py-1.5 {{ $pageSlug === 'booking' ? 'ws-bg-soft ws-accent font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">Booking</a>
                <a href="{{ $contactUrl }}" class="rounded-lg px-3 py-1.5 {{ $pageSlug === 'contact' ? 'ws-bg-soft ws-accent font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">Contact</a>
            </nav>
        </div>
    </header>

    <main>
        @if(!empty($isPreview))
            <div class="border-b border-amber-200 bg-amber-50 py-2 text-center text-xs font-semibold text-amber-700">
                Preview Mode — Draft content is visible only through this secure preview link.
            </div>
        @endif

        @if($pageSlug === 'booking')
            @include('public.website.sections.booking_funnel')
        @elseif($pageSlug === 'contact')
            @include('public.website.sections.contact_page')
        @else
            @foreach($sections as $section)
                @php($sectionConfig = is_array($section->config) ? $section->config : [])
                @includeIf('public.website.sections.'.$section->component_key, ['sectionConfig' => $sectionConfig])
            @endforeach
        @endif
    </main>

    <script>
        window.PublicBookingConfig = {
            websiteSlug: @json($website->slug),
            routes: {
                services: @json(route('public.api.services', $website->slug)),
                serviceStaff: @json(route('public.api.services.staff', [$website->slug, '__SERVICE__'])),
                availability: @json(route('public.api.availability', $website->slug)),
                validate: @json(route('public.api.booking.validate', $website->slug)),
                store: @json(route('public.api.booking.store', $website->slug)),
            },
            timezone: @json($timezone ?? config('app.timezone')),
            requiresPaymentLabel: @json(__('Requires payment')),
        };
    </script>
</body>
</html>
