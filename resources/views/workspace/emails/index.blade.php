<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-900">البريد الإلكتروني</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        @include('workspace.partials.nav')
        @include('partials.flash')

        <div class="rounded-2xl border border-[#BDEFE5] bg-[#F3FCFA] p-5">
            <h3 class="text-base font-semibold text-[#067e6b]">Email CRM & Inbox Hub</h3>
            <p class="mt-2 text-sm text-gray-700">
                قسم مستقل لكل مساحة عمل مع عزل كامل للبيانات، مزامنة IMAP، إرسال SMTP، وأرشفة كاملة للرسائل والمرفقات.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="space-y-4 lg:col-span-1">
                <div class="rounded-2xl border bg-white p-4">
                    <h3 class="mb-3 font-semibold">ربط حساب بريد جديد</h3>
                    <form method="POST" action="{{ route('workspace.emails.accounts.store') }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">الاسم التعريفي</label>
                            <input name="name" class="w-full rounded-lg border-gray-300 text-sm" required>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">البريد الإلكتروني</label>
                            <input type="email" name="email" class="w-full rounded-lg border-gray-300 text-sm" required>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">كلمة المرور</label>
                            <input type="password" name="password" class="w-full rounded-lg border-gray-300 text-sm" required>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600">IMAP Host</label>
                                <input name="imap_host" class="w-full rounded-lg border-gray-300 text-sm" required>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600">IMAP Port</label>
                                <input type="number" name="imap_port" value="993" class="w-full rounded-lg border-gray-300 text-sm" required>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600">SMTP Host</label>
                                <input name="smtp_host" class="w-full rounded-lg border-gray-300 text-sm" required>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600">SMTP Port</label>
                                <input type="number" name="smtp_port" value="587" class="w-full rounded-lg border-gray-300 text-sm" required>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600">لون الهوية</label>
                                <input type="color" name="brand_color" value="#06C2A4" class="h-10 w-full rounded-lg border-gray-300">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600">شعار البريد</label>
                                <input type="file" name="logo" class="w-full rounded-lg border-gray-300 text-xs">
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">الأسماء المستعارة (حتى 50)</label>
                            <textarea name="aliases" rows="3" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Support Team&#10;Sales Team"></textarea>
                        </div>
                        <button class="w-full rounded-lg bg-[#06C2A4] px-4 py-2 text-sm font-semibold text-white hover:bg-[#04a98e]">
                            حفظ الحساب
                        </button>
                    </form>
                </div>

                <div class="rounded-2xl border bg-white p-4">
                    <h3 class="mb-3 font-semibold">حسابات البريد المربوطة</h3>
                    <div class="space-y-3">
                        @forelse($accounts as $account)
                            <div class="rounded-xl border border-gray-200 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ $account->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $account->email }}</p>
                                    </div>
                                    <span class="h-3 w-3 rounded-full" style="background-color: {{ $account->brand_color }}"></span>
                                </div>
                                <div class="mt-3 flex items-center gap-2">
                                    <a href="{{ route('workspace.emails.index', ['account_id' => $account->id]) }}" class="rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700">فتح</a>
                                    <form method="POST" action="{{ route('workspace.emails.accounts.sync', $account) }}">
                                        @csrf
                                        <button class="rounded-md bg-[#06C2A4] px-2 py-1 text-xs text-white">مزامنة</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">لا توجد حسابات بريد مضافة.</p>
                        @endforelse
                    </div>
                </div>

                @if($currentAccount)
                    <div class="rounded-2xl border bg-white p-4">
                        <h3 class="mb-3 font-semibold">تعديل الحساب الحالي</h3>
                        <form method="POST" action="{{ route('workspace.emails.accounts.update', $currentAccount) }}" enctype="multipart/form-data" class="space-y-3">
                            @csrf
                            @method('PUT')
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600">الاسم التعريفي</label>
                                <input name="name" value="{{ $currentAccount->name }}" class="w-full rounded-lg border-gray-300 text-sm" required>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600">البريد الإلكتروني</label>
                                <input type="email" name="email" value="{{ $currentAccount->email }}" class="w-full rounded-lg border-gray-300 text-sm" required>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600">كلمة المرور الجديدة (اختياري)</label>
                                <input type="password" name="password" class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-600">IMAP Host</label>
                                    <input name="imap_host" value="{{ $currentAccount->imap_host }}" class="w-full rounded-lg border-gray-300 text-sm" required>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-600">IMAP Port</label>
                                    <input type="number" name="imap_port" value="{{ $currentAccount->imap_port }}" class="w-full rounded-lg border-gray-300 text-sm" required>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-600">SMTP Host</label>
                                    <input name="smtp_host" value="{{ $currentAccount->smtp_host }}" class="w-full rounded-lg border-gray-300 text-sm" required>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-600">SMTP Port</label>
                                    <input type="number" name="smtp_port" value="{{ $currentAccount->smtp_port }}" class="w-full rounded-lg border-gray-300 text-sm" required>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-600">لون الهوية</label>
                                    <input type="color" name="brand_color" value="{{ $currentAccount->brand_color }}" class="h-10 w-full rounded-lg border-gray-300">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-600">تحديث الشعار</label>
                                    <input type="file" name="logo" class="w-full rounded-lg border-gray-300 text-xs">
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600">الأسماء المستعارة</label>
                                <textarea name="aliases" rows="3" class="w-full rounded-lg border-gray-300 text-sm">{{ implode("\n", $currentAccount->aliases ?? []) }}</textarea>
                            </div>
                            <button class="w-full rounded-lg border border-[#06C2A4] px-4 py-2 text-sm font-semibold text-[#06C2A4] hover:bg-[#E8FAF6]">
                                حفظ التحديثات
                            </button>
                        </form>
                    </div>
                @endif
            </section>

            <section class="space-y-4 lg:col-span-2">
                <div class="rounded-2xl border bg-white p-4">
                    <h3 class="mb-3 font-semibold">إرسال رسالة جديدة</h3>
                    @if($currentAccount)
                        <form method="POST" action="{{ route('workspace.emails.messages.send') }}" enctype="multipart/form-data" class="space-y-3">
                            @csrf
                            <input type="hidden" name="email_account_id" value="{{ $currentAccount->id }}">
                            @if($selectedMessage)
                                <input type="hidden" name="reply_to_message_id" value="{{ $selectedMessage->id }}">
                            @endif
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-600">من (Alias)</label>
                                    <select name="sender_alias" class="w-full rounded-lg border-gray-300 text-sm">
                                        <option value="">{{ $currentAccount->name }}</option>
                                        @foreach(($currentAccount->aliases ?? []) as $alias)
                                            <option value="{{ $alias }}">{{ $alias }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-600">إلى</label>
                                    <input name="recipient" class="w-full rounded-lg border-gray-300 text-sm" placeholder="client@example.com" required>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600">الموضوع</label>
                                <input name="subject" value="{{ $selectedMessage ? 'Re: '.$selectedMessage->subject : '' }}" class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600">المحتوى</label>
                                <textarea name="body" rows="5" class="w-full rounded-lg border-gray-300 text-sm" required>{{ $selectedMessage ? "\n\n---\n".$selectedMessage->body : '' }}</textarea>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600">مرفقات</label>
                                <input type="file" name="attachments[]" multiple class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <button class="rounded-lg bg-[#06C2A4] px-4 py-2 text-sm font-semibold text-white hover:bg-[#04a98e]">
                                إرسال (Queue)
                            </button>
                        </form>
                    @else
                        <p class="text-sm text-gray-500">قم بإضافة حساب بريد أولاً لبدء الإرسال.</p>
                    @endif
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded-2xl border bg-white p-4">
                        <h3 class="mb-3 font-semibold">صندوق الوارد والأرشيف</h3>
                        <div class="space-y-2">
                            @forelse($messages as $message)
                                <a href="{{ route('workspace.emails.index', array_filter(['account_id' => $currentAccount?->id, 'message' => $message->id])) }}"
                                   class="block rounded-xl border px-3 py-2 text-sm transition {{ $selectedMessage && $selectedMessage->id === $message->id ? 'border-[#06C2A4] bg-[#E8FAF6]' : 'border-gray-200 hover:bg-gray-50' }}">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-semibold text-gray-900">{{ $message->subject ?: '(بدون عنوان)' }}</span>
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-600">{{ $message->type }}</span>
                                    </div>
                                    <p class="mt-1 truncate text-xs text-gray-500">{{ $message->sender }} → {{ $message->recipient }}</p>
                                    <p class="mt-1 text-[11px] text-gray-400">{{ $message->created_at }}</p>
                                </a>
                            @empty
                                <p class="text-sm text-gray-500">لا توجد رسائل بعد.</p>
                            @endforelse
                        </div>
                        <div class="mt-4">{{ $messages->links() }}</div>
                    </div>

                    <div class="rounded-2xl border bg-white p-4">
                        <h3 class="mb-3 font-semibold">Thread الرسالة</h3>
                        @if($selectedMessage)
                            <div class="space-y-3">
                                @foreach($threadMessages as $threadMessage)
                                    <article class="rounded-xl border border-gray-200 p-3 text-sm">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="font-semibold text-gray-900">{{ $threadMessage->subject ?: '(بدون عنوان)' }}</p>
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-600">{{ $threadMessage->type }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ $threadMessage->sender }} → {{ $threadMessage->recipient }}</p>
                                        <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $threadMessage->body }}</p>
                                        @if($threadMessage->attachments->count() > 0)
                                            <div class="mt-2 border-t pt-2">
                                                <p class="mb-1 text-xs font-semibold text-gray-600">المرفقات</p>
                                                <div class="space-y-1 text-xs">
                                                    @foreach($threadMessage->attachments as $attachment)
                                                        <a class="text-[#06C2A4] hover:underline" target="_blank" href="{{ \Storage::disk('public')->url($attachment->file_path) }}">
                                                            {{ basename($attachment->file_path) }} ({{ $attachment->file_size }} bytes)
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-500">اختر رسالة من القائمة لعرض سلسلة الردود.</p>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
