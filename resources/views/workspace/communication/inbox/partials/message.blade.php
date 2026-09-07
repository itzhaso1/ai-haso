@php
    $isInternal = $message->direction === 'internal_note';
    $isOutbound = $message->direction === 'outbound';
    $status = $message->delivery_status;
    $canRetry = $isOutbound
        && $status === \App\Models\Message::DELIVERY_FAILED
        && ($canReply ?? false)
        && ($composer['can_send_outbound'] ?? false)
        && (($activeConversation->display_channel ?? '') === 'whatsapp');
    $sender = $isInternal
        ? ($message->user?->name ?? 'فريق العمل')
        : ($isOutbound ? ($message->user?->name ?? 'Agent') : ($activeConversation->customer?->name ?? 'Customer'));
@endphp

@if($isInternal)
    <div class="mx-auto max-w-xl rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
        <p class="text-[11px] font-bold uppercase tracking-wide text-amber-800">Internal Note · لا تُرسل للعميل</p>
        <p class="mt-1 text-sm text-amber-950">{{ $message->content }}</p>
        <p class="mt-2 text-[11px] text-amber-700">{{ $sender }} · {{ $message->created_at?->format('Y-m-d H:i') }}</p>
    </div>
@else
    <div class="flex {{ $isOutbound ? 'justify-end' : 'justify-start' }}">
        <div class="{{ $isOutbound ? 'bg-[#DDF6F1]' : 'border border-slate-200 bg-white' }} max-w-[82%] rounded-2xl px-4 py-2 shadow-sm">
            <p class="text-[11px] font-semibold text-slate-500">{{ $sender }}</p>
            <p class="mt-1 text-sm leading-6 text-slate-900">{{ $message->content ?: '—' }}</p>
            @if($message->attachments->isNotEmpty())
                <ul class="mt-2 space-y-1 text-xs text-slate-600">
                    @foreach($message->attachments as $attachment)
                        <li class="rounded-lg bg-white/70 px-2 py-1">{{ $attachment->original_name ?: $attachment->path }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                <span>{{ $message->created_at?->format('Y-m-d H:i') }}</span>
                @if($isOutbound)
                    <span>•</span>
                    @if($status === \App\Models\Message::DELIVERY_PENDING)
                        <span class="font-semibold text-amber-700">Sending</span>
                    @elseif($status === \App\Models\Message::DELIVERY_SENT || $status === \App\Models\Message::DELIVERY_DELIVERED)
                        <span class="font-semibold text-emerald-700">Sent</span>
                    @elseif($status === \App\Models\Message::DELIVERY_FAILED)
                        <span class="font-semibold text-rose-700">Failed</span>
                    @elseif($status === \App\Models\Message::DELIVERY_NA)
                        <span>Not sent to customer</span>
                    @elseif($status)
                        <span>{{ $status }}</span>
                    @endif
                @endif
            </div>
            @if($status === \App\Models\Message::DELIVERY_FAILED && $message->delivery_error)
                <p class="mt-1 text-[11px] text-rose-700">{{ $message->delivery_error }}</p>
            @endif
            @if($canRetry)
                <form method="POST" action="{{ route('workspace.communication.inbox.messages.retry', [$activeConversation, $message]) }}" class="mt-2">
                    @csrf
                    @foreach($queryState as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <button class="rounded-lg bg-rose-600 px-3 py-1 text-[11px] font-semibold text-white">Retry</button>
                </form>
            @elseif($isOutbound && $status === \App\Models\Message::DELIVERY_FAILED)
                <p class="mt-2 text-[11px] text-slate-500">Retry is disabled because this channel cannot send through Communication Center yet.</p>
            @endif
        </div>
    </div>
@endif
