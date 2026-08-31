@php
    $title = $sectionConfig['title'] ?? 'آراء العملاء';
    $items = is_array($sectionConfig['items'] ?? null) ? $sectionConfig['items'] : [];
@endphp
<section class="mx-auto max-w-6xl px-4 py-12">
    <h2 class="text-2xl font-bold text-slate-900">{{ $title }}</h2>
    <div class="mt-5 grid gap-4 md:grid-cols-2">
        @forelse($items as $item)
            <blockquote class="rounded-2xl border border-slate-200 bg-white p-5 text-sm leading-7 text-slate-600 shadow-sm">
                <p>"{{ $item['text'] ?? '' }}"</p>
                <footer class="mt-3 text-xs font-semibold text-slate-500">{{ $item['author'] ?? '' }}</footer>
            </blockquote>
        @empty
            <p class="text-sm text-slate-500">يمكنك إضافة شهادات العملاء من إعدادات القسم.</p>
        @endforelse
    </div>
</section>
