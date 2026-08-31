@php
    $title = $sectionConfig['title'] ?? 'المعرض';
    $images = is_array($sectionConfig['images'] ?? null) ? $sectionConfig['images'] : [];
@endphp
<section class="mx-auto max-w-6xl px-4 py-12">
    <h2 class="text-2xl font-bold text-slate-900">{{ $title }}</h2>
    <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4">
        @forelse($images as $image)
            <img src="{{ $image }}" alt="gallery image" loading="lazy" class="h-28 w-full rounded-xl object-cover sm:h-36">
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                أضف صور المعرض من إعدادات القالب.
            </div>
        @endforelse
    </div>
</section>
