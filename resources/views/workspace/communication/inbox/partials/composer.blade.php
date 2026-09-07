@php
    $composer = $activeConversation->composer ?? [];
    $canSendOutbound = (bool) ($composer['can_send_outbound'] ?? false);
    $canNote = (bool) ($composer['can_internal_note'] ?? true) && ($canReply ?? false);
    $blockMessage = $composer['block_message'] ?? null;
@endphp

<div class="border-t border-slate-200 bg-white px-4 py-4">
    @unless($canReply ?? false)
        <p class="text-sm text-slate-500">ليس لديك صلاحية الرد على هذه المحادثة.</p>
    @else
        @if(! $canSendOutbound && $blockMessage)
            <div class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                {{ $blockMessage }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('workspace.communication.inbox.messages.store', $activeConversation) }}"
            class="space-y-3"
            @submit="sending = true"
        >
            @csrf
            @foreach($queryState as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <input type="hidden" name="message_type" value="text">

            <input type="hidden" name="direction" :value="composerMode">

            <div class="flex flex-wrap gap-2">
                @if($canSendOutbound)
                    <button type="button" @click="composerMode = 'outbound'" class="rounded-full border px-3 py-1 text-xs font-semibold" :class="composerMode === 'outbound' ? 'border-[#06C2A4] bg-[#E8FAF6] text-[#067e6b]' : 'border-slate-200 text-slate-600'">
                        رسالة للعميل
                    </button>
                @endif
                @if($canNote)
                    <button type="button" @click="composerMode = 'internal_note'" class="rounded-full border px-3 py-1 text-xs font-semibold" :class="composerMode === 'internal_note' ? 'border-amber-400 bg-amber-50 text-amber-900' : 'border-slate-200 text-slate-600'">
                        Internal Note
                    </button>
                @endif
            </div>

            @if($canSendOutbound || $canNote)
                <textarea
                    name="content"
                    rows="3"
                    required
                    class="w-full rounded-xl border-slate-300 text-sm focus:border-[#06C2A4] focus:ring-[#06C2A4]"
                    :placeholder="composerMode === 'internal_note' ? 'ملاحظة داخلية — لن تُرسل للعميل' : 'اكتب الرد...'"
                ></textarea>
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-slate-500" x-show="sending">Sending...</p>
                    <button
                        class="rounded-xl bg-[#06C2A4] px-5 py-2 text-sm font-semibold text-white transition hover:bg-[#04a98e] disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="sending"
                    >
                        <span x-show="!sending" x-text="composerMode === 'internal_note' ? 'حفظ الملاحظة' : 'إرسال'"></span>
                        <span x-show="sending">Sending...</span>
                    </button>
                </div>
            @endif
        </form>
    @endunless
</div>
