<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Communication Center · Connections</h2>
                <p class="mt-1 text-xs text-slate-500">الحالة تعتمد على ChannelConnection الفعلية. لا يوجد مسار مصادقة واتساب جديد هنا.</p>
            </div>
            @include('workspace.communication.partials.subnav')
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        @include('partials.flash')

        @if($connectionCount === 0)
            <x-empty-state title="No connections" text="لا توجد Channel Connections بعد. اربط واتساب من حسابات WhatsApp الحالية، أو أضف حساب بريد من Email Hub." />
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            @foreach($catalog as $channel)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">{{ $channel['name'] }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ $channel['hint'] }}</p>
                        </div>
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold
                            {{ $channel['coming_soon'] ? 'bg-amber-100 text-amber-800' : ($channel['connected'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600') }}">
                            {{ $channel['status_text'] }}
                        </span>
                    </div>

                    @if($channel['coming_soon'])
                        <p class="mt-4 text-sm font-semibold text-amber-800">Coming Soon</p>
                        <p class="mt-1 text-xs text-slate-500">ليست متصلة وليست قابلة للإرسال من Inbox.</p>
                    @elseif($channel['connections'] !== [])
                        <ul class="mt-4 space-y-2">
                            @foreach($channel['connections'] as $connection)
                                <li class="rounded-xl border border-slate-100 px-3 py-2 text-sm">
                                    <p class="font-semibold text-slate-900">{{ $connection['display_name'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $connection['external_account_id'] ?: $connection['status'] }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-4 text-sm text-slate-500">لا يوجد اتصال محفوظ لهذه القناة.</p>
                    @endif

                    @if($channel['manage_url'] && ! $channel['coming_soon'])
                        <a href="{{ $channel['manage_url'] }}" class="mt-4 inline-flex rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            {{ $channel['key'] === 'whatsapp' ? 'إدارة حسابات واتساب' : 'فتح Email Hub' }}
                        </a>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</x-app-layout>
