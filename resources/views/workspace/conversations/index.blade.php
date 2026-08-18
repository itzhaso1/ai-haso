<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-900">المحادثات</h2>
    </x-slot>

    @include('partials.flash')

    <div class="mx-auto max-w-[1400px]">
        <div class="mb-4 rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm sm:px-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <form method="GET" class="flex flex-1 items-center gap-2">
                    <input
                        name="search"
                        value="{{ request('search') }}"
                        class="w-full rounded-xl border-gray-300 text-sm focus:border-[#06C2A4] focus:ring-[#06C2A4]"
                        placeholder="بحث باسم العميل أو External ID" />
                    @if(request()->filled('conversation'))
                        <input type="hidden" name="conversation" value="{{ request('conversation') }}">
                    @endif
                    <button class="rounded-xl bg-[#06C2A4] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#04a98e]">بحث</button>
                </form>
                <a href="{{ route('workspace.conversations.create') }}" class="inline-flex items-center justify-center rounded-xl border border-[#06C2A4] px-4 py-2 text-sm font-semibold text-[#06C2A4] transition hover:bg-[#E8FAF6]">
                    + محادثة جديدة
                </a>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex min-h-[72vh] flex-col lg:flex-row-reverse">
                <aside class="w-full border-b border-gray-200 bg-[#FCFFFE] lg:w-[360px] lg:border-b-0 lg:border-l">
                    <div class="border-b border-gray-200 px-4 py-4">
                        <h3 class="font-semibold text-gray-900">قائمة المحادثات</h3>
                        <p class="mt-1 text-xs text-gray-500">جاهز لربط رسائل Meta WhatsApp API لاحقًا.</p>
                    </div>
                    <div class="max-h-[58vh] overflow-y-auto lg:max-h-[calc(72vh-120px)]">
                        @forelse($conversations as $conversation)
                            @php
                                $isActive = $activeConversation && $activeConversation->id === $conversation->id;
                                $preview = $conversation->messages->first();
                            @endphp
                            <a href="{{ route('workspace.conversations.index', array_filter(['search' => request('search'), 'conversation' => $conversation->id])) }}"
                               class="{{ $isActive ? 'bg-[#E8FAF6]' : 'hover:bg-gray-50' }} block border-b border-gray-100 px-4 py-3 transition">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $conversation->customer?->name ?? 'عميل غير معروف' }}</p>
                                        <p class="mt-1 truncate text-xs text-gray-600">
                                            {{ $preview?->content ?: 'لا توجد رسائل بعد' }}
                                        </p>
                                    </div>
                                    <div class="text-left">
                                        <span class="rounded-full bg-gray-100 px-2 py-1 text-[11px] text-gray-600">{{ $conversation->messages_count }}</span>
                                    </div>
                                </div>
                                <div class="mt-2 flex items-center justify-between text-[11px] text-gray-500">
                                    <span>{{ $conversation->channel }}</span>
                                    <span>{{ $conversation->last_message_at?->diffForHumans() ?? 'بدون نشاط' }}</span>
                                </div>
                            </a>
                        @empty
                            <div class="p-5 text-sm text-gray-500">لا توجد محادثات بعد. ابدأ بإنشاء محادثة جديدة.</div>
                        @endforelse
                    </div>
                    <div class="border-t border-gray-200 px-4 py-3">
                        {{ $conversations->links() }}
                    </div>
                </aside>

                <section class="flex min-h-[56vh] flex-1 flex-col bg-white">
                    @if($activeConversation)
                        <div class="border-b border-gray-200 px-4 py-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900">{{ $activeConversation->customer?->name ?? 'عميل بدون اسم' }}</h3>
                                    <p class="mt-1 text-xs text-gray-500">القناة: {{ $activeConversation->channel }} · الحالة: {{ $activeConversation->status }}</p>
                                </div>
                                <form method="POST" action="{{ route('workspace.conversations.update', $activeConversation) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    <select name="status" class="rounded-lg border-gray-300 text-xs focus:border-[#06C2A4] focus:ring-[#06C2A4]">
                                        @foreach(['open','closed','archived'] as $status)
                                            <option value="{{ $status }}" @selected($activeConversation->status === $status)>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                    <label class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2 py-1 text-xs">
                                        <input type="checkbox" name="ai_enabled" value="1" @checked($activeConversation->ai_enabled)>
                                        <span>AI</span>
                                    </label>
                                    <input type="hidden" name="metadata_json" value="{{ json_encode($activeConversation->metadata ?? [], JSON_UNESCAPED_UNICODE) }}">
                                    <button class="rounded-lg bg-[#06C2A4] px-3 py-2 text-xs font-semibold text-white">تحديث</button>
                                </form>
                            </div>
                        </div>

                        <div class="flex-1 space-y-3 overflow-y-auto bg-[#F9FBFB] px-4 py-5">
                            @forelse($activeConversation->messages as $message)
                                @php
                                    $outbound = in_array($message->direction, ['outbound', 'internal_note'], true);
                                @endphp
                                <div class="flex {{ $outbound ? 'justify-start' : 'justify-end' }}">
                                    <div class="{{ $outbound ? 'bg-[#DDF6F1] text-gray-900' : 'bg-white text-gray-800 border border-gray-200' }} max-w-[80%] rounded-2xl px-4 py-2 shadow-sm">
                                        <p class="text-sm leading-6">{{ $message->content ?: '—' }}</p>
                                        <div class="mt-1 flex items-center gap-2 text-[11px] text-gray-500">
                                            <span>{{ $message->direction }}</span>
                                            <span>•</span>
                                            <span>{{ $message->created_at?->format('Y-m-d H:i') }}</span>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">
                                    لا توجد رسائل في هذه المحادثة حتى الآن.
                                </div>
                            @endforelse
                        </div>

                        <div class="border-t border-gray-200 bg-white px-4 py-4">
                            <form method="POST" action="{{ route('workspace.conversations.messages.store', $activeConversation) }}" class="space-y-3">
                                @csrf
                                <input type="hidden" name="conversation_id" value="{{ $activeConversation->id }}">
                                <input type="hidden" name="customer_id" value="{{ $activeConversation->customer_id }}">
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                    <select name="direction" class="rounded-lg border-gray-300 text-sm focus:border-[#06C2A4] focus:ring-[#06C2A4]">
                                        @foreach(['outbound','inbound','internal_note'] as $direction)
                                            <option value="{{ $direction }}">{{ $direction }}</option>
                                        @endforeach
                                    </select>
                                    <select name="message_type" class="rounded-lg border-gray-300 text-sm focus:border-[#06C2A4] focus:ring-[#06C2A4]">
                                        @foreach(['text','image','file','system'] as $type)
                                            <option value="{{ $type }}">{{ $type }}</option>
                                        @endforeach
                                    </select>
                                    <input name="external_message_id" class="rounded-lg border-gray-300 text-sm focus:border-[#06C2A4] focus:ring-[#06C2A4]" placeholder="external_message_id (اختياري)">
                                </div>
                                <textarea name="content" rows="2" class="w-full rounded-xl border-gray-300 text-sm focus:border-[#06C2A4] focus:ring-[#06C2A4]" placeholder="اكتب الرسالة هنا..."></textarea>
                                <input type="hidden" name="metadata_json" value="{}">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-xs text-gray-500">يمكنك إرسال outbound، أو تسجيل inbound لمحاكاة الاستقبال من مزود خارجي.</p>
                                    <button class="rounded-xl bg-[#06C2A4] px-5 py-2 text-sm font-semibold text-white transition hover:bg-[#04a98e]">
                                        إرسال الرسالة
                                    </button>
                                </div>
                            </form>
                        </div>
                    @else
                        <div class="flex h-full flex-1 items-center justify-center px-6">
                            <div class="max-w-md text-center">
                                <h3 class="text-lg font-semibold text-gray-900">اختر محادثة للبدء</h3>
                                <p class="mt-2 text-sm text-gray-500">واجهة المحادثة جاهزة للإرسال والاستقبال وربط تكاملات WhatsApp API مستقبلًا.</p>
                            </div>
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
