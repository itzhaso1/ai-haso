<footer class="mt-12 border-t border-slate-200 bg-white py-6">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 text-xs text-slate-500">
        <p>{{ $settings['footer_text'] ?? ($settings['business_name'] ?? $website->name) }}</p>
        <p>{{ now()->year }} ©</p>
    </div>
</footer>
