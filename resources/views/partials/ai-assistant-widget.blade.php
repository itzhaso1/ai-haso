<div
    x-data="{
        open: false,
        loading: false,
        message: '',
        messages: [
            { role: 'assistant', text: 'مرحبًا، أنا مساعد حاسم. كيف يمكنني مساعدتك اليوم؟' }
        ],
        async send() {
            const text = this.message.trim();
            if (!text || this.loading) return;

            this.messages.push({ role: 'user', text });
            this.message = '';
            this.loading = true;

            try {
                const response = await fetch('{{ route('assistant.chat') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.content || '',
                    },
                    body: JSON.stringify({ message: text }),
                });

                const payload = await response.json();
                const reply = payload?.data?.reply || 'تعذر إنشاء الرد الآن. حاول مرة أخرى.';
                this.messages.push({ role: 'assistant', text: reply });
            } catch (e) {
                this.messages.push({ role: 'assistant', text: 'حدث خطأ أثناء الاتصال بالمساعد. حاول مجددًا.' });
            } finally {
                this.loading = false;
                this.$nextTick(() => {
                    this.$refs.log.scrollTop = this.$refs.log.scrollHeight;
                });
            }
        }
    }"
    class="fixed bottom-6 left-6 z-[70]"
>
    <div x-cloak x-show="open" class="mb-3 w-[330px] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-100 bg-[#06C2A4] px-4 py-3 text-white">
            <div>
                <p class="text-sm font-bold">مساعد حاسم</p>
                <p class="text-[11px] text-white/90">دعم فوري للدخول والتنقل داخل المنصة</p>
            </div>
            <button @click="open = false" class="rounded-md p-1 hover:bg-white/20">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div x-ref="log" class="max-h-72 space-y-2 overflow-y-auto bg-[#F7FCFB] p-3">
            <template x-for="(item, idx) in messages" :key="idx">
                <div :class="item.role === 'assistant' ? 'justify-start' : 'justify-end'" class="flex">
                    <div :class="item.role === 'assistant' ? 'bg-white text-gray-800 border border-gray-200' : 'bg-[#06C2A4] text-white'" class="max-w-[85%] rounded-2xl px-3 py-2 text-xs leading-6 shadow-sm">
                        <p x-text="item.text"></p>
                    </div>
                </div>
            </template>
            <p x-show="loading" class="text-xs text-gray-500">جاري كتابة الرد...</p>
        </div>

        <form @submit.prevent="send" class="border-t border-gray-100 bg-white p-3">
            <div class="flex items-center gap-2">
                <input
                    x-model="message"
                    type="text"
                    placeholder="اكتب سؤالك..."
                    class="w-full rounded-xl border-gray-300 text-sm focus:border-[#06C2A4] focus:ring-[#06C2A4]"
                />
                <button type="submit" class="rounded-xl bg-[#06C2A4] px-3 py-2 text-xs font-semibold text-white hover:bg-[#04a98e]">إرسال</button>
            </div>
        </form>
    </div>

    <button
        @click="open = !open"
        type="button"
        class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-[#06C2A4] text-white shadow-xl transition hover:scale-[1.03] hover:bg-[#04a98e]"
        aria-label="فتح مساعد حاسم"
    >
        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M8 10h8M8 14h5m5 7-3.7-2H8a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v14Z" />
        </svg>
    </button>
</div>
