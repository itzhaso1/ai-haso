<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Communication Center · Templates</h2>
                <p class="mt-1 text-xs text-slate-500">Canned Replies — placeholder فقط في Phase 2.</p>
            </div>
            @include('workspace.communication.partials.subnav')
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        @include('partials.flash')
        <x-empty-state
            title="Templates / Canned Replies"
            text="الردود الجاهزة غير مفعّلة بعد. ستُبنى في مرحلة لاحقة فوق Communication Core دون تغيير مسار الإرسال الحالي."
        />
    </div>
</x-app-layout>
