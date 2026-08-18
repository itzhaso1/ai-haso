<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold">المحادثات</h2></x-slot>
    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4">
            @include('workspace.partials.nav')
            @include('partials.flash')
            <div class="mb-4 flex items-center justify-between">
                <form method="GET" class="flex gap-2">
                    <input name="search" value="{{ request('search') }}" class="rounded-lg border-gray-300 text-sm" placeholder="بحث External ID" />
                    <button class="rounded-lg bg-gray-800 px-3 py-2 text-sm text-white">بحث</button>
                </form>
                <a href="{{ route('workspace.conversations.create') }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm text-white">محادثة جديدة</a>
            </div>
            <div class="overflow-x-auto rounded-xl border bg-white">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right">القناة</th>
                            <th class="px-4 py-3 text-right">العميل</th>
                            <th class="px-4 py-3 text-right">الحالة</th>
                            <th class="px-4 py-3 text-right">AI</th>
                            <th class="px-4 py-3 text-right">عدد الرسائل</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($conversations as $conversation)
                            <tr>
                                <td class="px-4 py-3">{{ $conversation->channel }}</td>
                                <td class="px-4 py-3">{{ $conversation->customer?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $conversation->status }}</td>
                                <td class="px-4 py-3">{{ $conversation->ai_enabled ? 'ON' : 'OFF' }}</td>
                                <td class="px-4 py-3">{{ $conversation->messages_count }}</td>
                                <td class="px-4 py-3 text-left">
                                    <a href="{{ route('workspace.conversations.edit', $conversation) }}" class="text-blue-600">فتح</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">لا توجد محادثات.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $conversations->links() }}</div>
        </div>
    </div>
</x-app-layout>
