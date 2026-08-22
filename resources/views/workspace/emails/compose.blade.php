@extends('layouts.email', ['pageTitle' => 'كتابة رسالة'])

@section('content')
    <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900">محرر الرسائل</h2>
                <form method="POST" action="{{ route('workspace.emails.compose.clear') }}">
                    @csrf
                    <input type="hidden" name="account_id" value="{{ $draft['email_account_id'] ?? '' }}">
                    <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100">
                        رسالة جديدة (مسح المسودة)
                    </button>
                </form>
            </div>

            @if($accounts->isEmpty())
                <p class="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-500">
                    لا يوجد أي حساب بريد مضاف. أضف شركة من قسم "الشركات / حسابات البريد" ثم ارجع للإرسال.
                </p>
            @else
                <form method="POST"
                      action="{{ route('workspace.emails.messages.send') }}"
                      enctype="multipart/form-data"
                      class="space-y-4"
                      x-data="recipientPicker({
                        searchUrl: '{{ route('workspace.emails.contacts.search') }}',
                        lookupUrl: '{{ route('workspace.emails.contacts.lookup') }}',
                        initialRecipient: @js($draft['recipient'] ?? ''),
                        initialContactId: @js($draft['recipient_contact_id'] ?? $recognizedContact?->id),
                        initialRecognized: @js($recognizedContact ? ['id' => $recognizedContact->id, 'name' => $recognizedContact->name, 'email' => $recognizedContact->email] : null),
                      })">
                    @csrf
                    <input type="hidden" name="reply_to_message_id" value="{{ $draft['reply_to_message_id'] ?? '' }}">
                    <input type="hidden" name="recipient_contact_id" x-model="recipientContactId">

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-700">الإرسال من:</label>
                            <select name="email_account_id" class="w-full rounded-lg border-slate-300 text-sm" required>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" @selected((int) ($draft['email_account_id'] ?? 0) === $account->id)>
                                        {{ $account->name }} — {{ $account->email }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-700">اسم مرسل إضافي (اختياري)</label>
                            <input type="text" name="sender_alias" value="{{ $draft['sender_alias'] ?? '' }}" placeholder="مثل: الدعم الفني"
                                   class="w-full rounded-lg border-slate-300 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">إلى</label>
                        <div class="relative">
                            <input type="text"
                                   name="recipient"
                                   x-model="recipient"
                                   @focus="search"
                                   @input.debounce.250ms="search"
                                   @keydown.escape="closeSuggestions"
                                   @keydown.arrow-down.prevent="focusNext"
                                   @keydown.arrow-up.prevent="focusPrev"
                                   @keydown.enter.prevent="chooseFocused"
                                   required
                                   placeholder="اكتب اسم الشركة أو البريد الإلكتروني"
                                   class="w-full rounded-lg border-slate-300 text-sm">

                            <div x-cloak x-show="open && suggestions.length > 0" class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                                <template x-for="(contact, index) in suggestions" :key="contact.id">
                                    <button type="button"
                                            @mouseenter="focusedIndex = index"
                                            @click="selectContact(contact)"
                                            class="w-full px-3 py-2 text-right text-sm transition"
                                            :class="focusedIndex === index ? 'bg-[#E8FAF6] text-[#0f7668]' : 'hover:bg-slate-50 text-slate-700'">
                                        <div class="font-semibold" x-text="contact.name"></div>
                                        <div class="text-xs text-slate-500">
                                            <span x-text="contact.email"></span>
                                            <span class="font-semibold text-emerald-700"> — ✓ مسجل مسبقًا</span>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <template x-if="recognized">
                            <p class="mt-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                                <span class="font-semibold" x-text="recognized.name"></span>
                                <span> — </span>
                                <span x-text="recognized.email"></span>
                                <span class="font-semibold"> — ✓ مسجل مسبقًا</span>
                            </p>
                        </template>

                        <template x-if="recipient && !recognized">
                            <p class="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                البريد غير محفوظ في جهات الاتصال حاليًا.
                                <a href="{{ route('workspace.emails.contacts.index') }}" class="font-semibold text-[#0f7668] hover:underline">إضافة كجهة اتصال</a>
                            </p>
                        </template>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">العنوان</label>
                        <input type="text" name="subject" value="{{ $draft['subject'] ?? '' }}" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">نص الرسالة</label>
                        <textarea name="body" rows="12" required class="w-full rounded-lg border-slate-300 text-sm leading-7">{{ $draft['body'] ?? '' }}</textarea>
                        <p class="mt-1 text-xs text-slate-500">ملاحظة: النص يبقى محفوظًا بعد الإرسال حتى تضغط "رسالة جديدة".</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">المرفقات</label>
                        <input type="file" name="attachments[]" multiple class="w-full rounded-lg border-slate-300 text-sm">
                        <p class="mt-1 text-xs text-slate-500">حد أقصى 10 ملفات، 10MB لكل ملف.</p>
                    </div>

                    <button type="submit" class="rounded-lg bg-[#06C2A4] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#05ab91]">
                        إرسال الرسالة
                    </button>
                </form>
            @endif
        </section>

        <aside class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900">إرشادات سريعة</h3>
                <ul class="mt-2 space-y-1 text-xs leading-6 text-slate-600">
                    <li>• اختر الشركة من قائمة "الإرسال من".</li>
                    <li>• النظام يستخدم SMTP الخاص بالحساب المختار تلقائيًا.</li>
                    <li>• عند النجاح ستظهر رسالة: "تم إرسال الرسالة بنجاح".</li>
                    <li>• عند الفشل ستظهر رسالة خطأ توضح السبب.</li>
                </ul>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900">إدارة الحسابات</h3>
                <p class="mt-2 text-xs leading-6 text-slate-600">لإضافة أو تعديل بيانات شركات البريد، انتقل إلى صفحة الحسابات.</p>
                <a href="{{ route('workspace.emails.accounts.index') }}" class="mt-3 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                    فتح الشركات / الحسابات
                </a>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900">دفتر العناوين</h3>
                <p class="mt-2 text-xs leading-6 text-slate-600">يمكنك اختيار شركة مباشرة من جهات الاتصال بدل كتابة البريد يدويًا.</p>
                <a href="{{ route('workspace.emails.contacts.index') }}" class="mt-3 inline-flex rounded-lg border border-[#06C2A4] px-3 py-2 text-xs font-semibold text-[#0f7668] hover:bg-[#E8FAF6]">
                    فتح جهات الاتصال
                </a>

                @if($contacts->isNotEmpty())
                    <div class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                        @foreach($contacts->take(5) as $contact)
                            <a href="{{ route('workspace.emails.compose', ['recipient' => $contact->email, 'recipient_contact_id' => $contact->id]) }}"
                               class="block rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">
                                <span class="font-semibold">{{ $contact->name }}</span>
                                <span class="text-slate-500"> — {{ $contact->email }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </aside>
    </div>

    <script>
        function recipientPicker({ searchUrl, lookupUrl, initialRecipient, initialContactId, initialRecognized }) {
            return {
                recipient: initialRecipient || '',
                recipientContactId: initialContactId || '',
                recognized: initialRecognized,
                suggestions: [],
                open: false,
                focusedIndex: -1,
                searchRequestToken: 0,
                lookupRequestToken: 0,
                async search() {
                    const query = (this.recipient || '').trim();
                    if (query.length < 1) {
                        this.suggestions = [];
                        this.open = false;
                        this.recipientContactId = '';
                        this.recognized = null;
                        return;
                    }

                    this.searchRequestToken += 1;
                    const token = this.searchRequestToken;
                    try {
                        const url = new URL(searchUrl, window.location.origin);
                        url.searchParams.set('q', query);
                        const response = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                        const data = await response.json();
                        if (token !== this.searchRequestToken) return;
                        this.suggestions = Array.isArray(data.contacts) ? data.contacts : [];
                        this.open = this.suggestions.length > 0;
                        this.focusedIndex = this.suggestions.length > 0 ? 0 : -1;
                    } catch (error) {
                        this.suggestions = [];
                        this.open = false;
                    }

                    await this.lookupExact();
                },
                async lookupExact() {
                    const query = (this.recipient || '').trim();
                    if (!query || query.indexOf('@') === -1) {
                        this.recognized = null;
                        this.recipientContactId = '';
                        return;
                    }

                    this.lookupRequestToken += 1;
                    const token = this.lookupRequestToken;
                    try {
                        const url = new URL(lookupUrl, window.location.origin);
                        url.searchParams.set('email', query);
                        const response = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                        const data = await response.json();
                        if (token !== this.lookupRequestToken) return;

                        if (data.found && data.contact) {
                            this.recognized = data.contact;
                            this.recipientContactId = data.contact.id;
                        } else {
                            this.recognized = null;
                            this.recipientContactId = '';
                        }
                    } catch (error) {
                        this.recognized = null;
                        this.recipientContactId = '';
                    }
                },
                selectContact(contact) {
                    this.recipient = contact.email;
                    this.recognized = contact;
                    this.recipientContactId = contact.id;
                    this.closeSuggestions();
                },
                closeSuggestions() {
                    this.open = false;
                    this.focusedIndex = -1;
                },
                focusNext() {
                    if (!this.open || this.suggestions.length === 0) return;
                    this.focusedIndex = (this.focusedIndex + 1) % this.suggestions.length;
                },
                focusPrev() {
                    if (!this.open || this.suggestions.length === 0) return;
                    this.focusedIndex = (this.focusedIndex - 1 + this.suggestions.length) % this.suggestions.length;
                },
                chooseFocused() {
                    if (!this.open || this.focusedIndex < 0 || !this.suggestions[this.focusedIndex]) return;
                    this.selectContact(this.suggestions[this.focusedIndex]);
                },
            };
        }
    </script>
@endsection
