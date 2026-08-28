<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-900">إنشاء عقد</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            @include('workspace.partials.nav')
            @include('partials.flash')
            @include('workspace.contracts._form')
        </div>
    </div>
</x-app-layout>
