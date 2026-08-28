<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-900">تعديل العقد {{ $contract->contract_number }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            @include('workspace.partials.nav')
            @include('partials.flash')
            @include('workspace.contracts._form')

            @if($contract->attachments->isNotEmpty())
                <article class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-bold text-slate-900">المرفقات الحالية</h3>
                    <div class="space-y-2">
                        @foreach($contract->attachments as $attachment)
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                <a href="{{ route('workspace.contracts.attachments.download', [$contract, $attachment]) }}" class="text-slate-700 hover:text-slate-900">
                                    {{ $attachment->file_name ?: basename((string) $attachment->file_path) }}
                                </a>
                                <form method="POST" action="{{ route('workspace.contracts.attachments.destroy', [$contract, $attachment]) }}" onsubmit="return confirm('حذف المرفق؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-md border border-red-200 px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">حذف</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endif
        </div>
    </div>
</x-app-layout>
