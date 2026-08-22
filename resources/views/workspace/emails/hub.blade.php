@extends('layouts.email', ['pageTitle' => 'مركز البريد الإلكتروني'])

@section('content')
    <div class="mx-auto w-full max-w-[1800px] px-2 sm:px-4">
        <div class="rounded-3xl border border-slate-200 bg-gradient-to-b from-slate-50 to-white p-4 shadow-sm sm:p-6">
            <div class="mb-4 flex flex-wrap items-center gap-2 border-b border-slate-200 pb-4">
                <a href="{{ route('workspace.emails.index', array_filter(['account_id' => $currentAccount?->id, 'folder' => 'inbound', 'search' => $search ?: null])) }}"
                   class="{{ $folder === 'inbound' ? 'bg-[#06C2A4] text-white' : 'bg-white text-slate-600 hover:bg-slate-100' }} rounded-lg px-4 py-2 text-xs font-semibold transition">
                    الوارد
                </a>
                <a href="{{ route('workspace.emails.index', array_filter(['account_id' => $currentAccount?->id, 'folder' => 'outbound', 'search' => $search ?: null])) }}"
                   class="{{ $folder === 'outbound' ? 'bg-[#06C2A4] text-white' : 'bg-white text-slate-600 hover:bg-slate-100' }} rounded-lg px-4 py-2 text-xs font-semibold transition">
                    الصادر
                </a>
                <a href="{{ route('workspace.emails.index', array_filter(['account_id' => $currentAccount?->id, 'folder' => 'all', 'search' => $search ?: null])) }}"
                   class="{{ $folder === 'all' ? 'bg-[#06C2A4] text-white' : 'bg-white text-slate-600 hover:bg-slate-100' }} rounded-lg px-4 py-2 text-xs font-semibold transition">
                    الكل
                </a>

                <form method="GET" action="{{ route('workspace.emails.index') }}" class="mr-auto flex w-full max-w-xl items-center gap-2 sm:w-auto">
                    <input type="hidden" name="account_id" value="{{ $currentAccount?->id }}">
                    <input type="hidden" name="folder" value="{{ $folder }}">
                    <input type="text" name="search" value="{{ $search }}" placeholder="ابحث في البريد بالمرسل/المستلم/العنوان..."
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-[#06C2A4] focus:ring-[#06C2A4]">
                    <button class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-700">
                        بحث
                    </button>
                </form>
            </div>

            <div class="grid gap-4 xl:grid-cols-[320px_420px_1fr]">
                <aside class="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-900">الحسابات المربوطة</h3>
                        <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500">{{ $accounts->count() }}</span>
                    </div>

                    <div class="space-y-2">
                        @forelse($accounts as $account)
                            <div class="rounded-xl border {{ $currentAccount && $currentAccount->id === $account->id ? 'border-[#06C2A4] bg-[#F0FBF9]' : 'border-slate-200 bg-white' }} p-3">
                                <div class="mb-2 flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">{{ $account->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $account->email }}</p>
                                    </div>
                                    <span class="h-3 w-3 rounded-full border border-white shadow" style="background-color: {{ $account->brand_color }}"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('workspace.emails.index', ['account_id' => $account->id, 'folder' => $folder, 'search' => $search ?: null]) }}"
                                       class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">
                                        فتح
                                    </a>
                                    <form method="POST" action="{{ route('workspace.emails.accounts.sync', $account) }}">
                                        @csrf
                                        <button class="rounded-md bg-[#06C2A4] px-2 py-1 text-xs font-semibold text-white hover:bg-[#05ab91]">
                                            مزامنة
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <p class="rounded-lg border border-dashed border-slate-300 p-4 text-xs text-slate-500">لا توجد حسابات بريد بعد.</p>
                        @endforelse
                    </div>

                    <details class="group rounded-xl border border-slate-200">
                        <summary class="cursor-pointer list-none rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 transition group-open:border-b group-open:border-slate-200">
                            إضافة حساب بريد جديد
                        </summary>
                        <form method="POST" action="{{ route('workspace.emails.accounts.store') }}" enctype="multipart/form-data" class="space-y-2 p-3">
                            @csrf
                            <p class="rounded-lg bg-[#F0FBF9] px-3 py-2 text-[11px] leading-5 text-[#0f7668]">
                                أدخل فقط: اسم الشركة + البريد + كلمة المرور + IMAP/SMTP (هوست وبورت).
                            </p>
                            <input name="name" placeholder="اسم الشركة (يظهر للمستلم)" class="w-full rounded-lg border-slate-300 text-sm" required>
                            <input type="email" name="email" placeholder="company@example.com" class="w-full rounded-lg border-slate-300 text-sm" required>
                            <input type="password" name="password" placeholder="كلمة المرور / App Password" class="w-full rounded-lg border-slate-300 text-sm" required>

                            <div class="grid grid-cols-2 gap-2">
                                <input name="imap_host" placeholder="IMAP Host" class="w-full rounded-lg border-slate-300 text-sm" required>
                                <input type="number" name="imap_port" value="993" class="w-full rounded-lg border-slate-300 text-sm" required>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <input name="smtp_host" placeholder="SMTP Host" class="w-full rounded-lg border-slate-300 text-sm" required>
                                <input type="number" name="smtp_port" value="587" class="w-full rounded-lg border-slate-300 text-sm" required>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="color" name="brand_color" value="#06C2A4" class="h-10 w-full rounded-lg border-slate-300">
                                <input type="file" name="logo" class="w-full rounded-lg border-slate-300 text-xs">
                            </div>
                            <textarea name="aliases" rows="2" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Support Team&#10;Sales Team"></textarea>
                            <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700">حفظ الحساب</button>
                        </form>
                    </details>

                    @if($currentAccount)
                        <details class="group rounded-xl border border-slate-200" open>
                            <summary class="cursor-pointer list-none rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 transition group-open:border-b group-open:border-slate-200">
                                تحديث الحساب الحالي
                            </summary>
                            <form method="POST" action="{{ route('workspace.emails.accounts.update', $currentAccount) }}" enctype="multipart/form-data" class="space-y-2 p-3">
                                @csrf
                                @method('PUT')
                                <input name="name" value="{{ $currentAccount->name }}" placeholder="اسم الشركة (يظهر للمستلم)" class="w-full rounded-lg border-slate-300 text-sm" required>
                                <input type="email" name="email" value="{{ $currentAccount->email }}" class="w-full rounded-lg border-slate-300 text-sm" required>
                                <input type="password" name="password" placeholder="تحديث كلمة المرور (اختياري)" class="w-full rounded-lg border-slate-300 text-sm">
                                <div class="grid grid-cols-2 gap-2">
                                    <input name="imap_host" value="{{ $currentAccount->imap_host }}" class="w-full rounded-lg border-slate-300 text-sm" required>
                                    <input type="number" name="imap_port" value="{{ $currentAccount->imap_port }}" class="w-full rounded-lg border-slate-300 text-sm" required>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <input name="smtp_host" value="{{ $currentAccount->smtp_host }}" class="w-full rounded-lg border-slate-300 text-sm" required>
                                    <input type="number" name="smtp_port" value="{{ $currentAccount->smtp_port }}" class="w-full rounded-lg border-slate-300 text-sm" required>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="color" name="brand_color" value="{{ $currentAccount->brand_color }}" class="h-10 w-full rounded-lg border-slate-300">
                                    <input type="file" name="logo" class="w-full rounded-lg border-slate-300 text-xs">
                                </div>
                                <label class="flex items-center gap-2 text-xs text-slate-600">
                                    <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-red-600">
                                    حذف الشعار الحالي
                                </label>
                                <textarea name="aliases" rows="2" class="w-full rounded-lg border-slate-300 text-sm">{{ implode("\n", $currentAccount->aliases ?? []) }}</textarea>
                                <button class="w-full rounded-lg border border-[#06C2A4] px-3 py-2 text-xs font-semibold text-[#06C2A4] hover:bg-[#E8FAF6]">
                                    تحديث الإعدادات
                                </button>
                            </form>
                        </details>
                    @endif
                </aside>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-semibold text-slate-900">
                        {{ $folder === 'inbound' ? 'صندوق الوارد' : ($folder === 'outbound' ? 'البريد الصادر' : 'جميع الرسائل') }}
                    </h3>

                    <div class="space-y-2">
                        @forelse($messages as $message)
                            <div class="rounded-xl border {{ $selectedMessage && $selectedMessage->id === $message->id ? 'border-[#06C2A4] bg-[#F1FCFA]' : 'border-slate-200 hover:bg-slate-50' }} p-3 transition">
                                <div class="flex items-start justify-between gap-2">
                                    <a href="{{ route('workspace.emails.index', array_filter(['account_id' => $currentAccount?->id, 'folder' => $folder, 'search' => $search ?: null, 'message' => $message->id])) }}" class="block min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $message->subject ?: '(بدون عنوان)' }}</p>
                                        <p class="mt-1 truncate text-xs text-slate-500">{{ $message->sender }} → {{ $message->recipient }}</p>
                                        <p class="mt-1 text-[11px] text-slate-400">{{ $message->created_at }}</p>
                                    </a>
                                    <form method="POST" action="{{ route('workspace.emails.messages.destroy', $message) }}" onsubmit="return confirm('هل تريد حذف هذه الرسالة مع مرفقاتها؟');">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="account_id" value="{{ $currentAccount?->id }}">
                                        <input type="hidden" name="folder" value="{{ $folder }}">
                                        <input type="hidden" name="search" value="{{ $search }}">
                                        <button class="rounded-md border border-red-200 px-2 py-1 text-[11px] font-semibold text-red-600 hover:bg-red-50">حذف</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 p-5 text-center text-sm text-slate-500">
                                لا توجد رسائل مطابقة للفلترة الحالية.
                            </div>
                        @endforelse
                    </div>

                    <div class="mt-4">{{ $messages->links() }}</div>
                </section>

                <section class="space-y-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="mb-3 text-sm font-semibold text-slate-900">عرض المحادثة (Thread)</h3>

                        @if($selectedMessage)
                            <div class="max-h-[440px] space-y-3 overflow-auto pr-1">
                                @foreach($threadMessages as $threadMessage)
                                    <article class="rounded-xl border border-slate-200 bg-white p-3">
                                        <div class="mb-2 flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">{{ $threadMessage->subject ?: '(بدون عنوان)' }}</p>
                                                <p class="mt-1 text-[11px] text-slate-500">{{ $threadMessage->sender }} → {{ $threadMessage->recipient }}</p>
                                            </div>
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">{{ $threadMessage->type }}</span>
                                        </div>
                                        <p class="whitespace-pre-line text-sm leading-7 text-slate-700">{{ $threadMessage->body }}</p>

                                        @if($threadMessage->attachments->count() > 0)
                                            <div class="mt-3 border-t border-slate-200 pt-2">
                                                <p class="mb-2 text-xs font-semibold text-slate-600">المرفقات</p>
                                                <div class="space-y-1 text-xs">
                                                    @foreach($threadMessage->attachments as $attachment)
                                                        <a class="block text-[#06C2A4] hover:underline" target="_blank" href="{{ \Storage::disk('public')->url($attachment->file_path) }}">
                                                            {{ basename($attachment->file_path) }} — {{ $attachment->file_size }} bytes
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <div class="rounded-xl border border-dashed border-slate-300 p-5 text-center text-sm text-slate-500">
                                اختر رسالة لعرض سلسلة المحادثة.
                            </div>
                        @endif
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="mb-3 text-sm font-semibold text-slate-900">محرر الرسائل (Composer)</h3>
                        @if($currentAccount)
                            <form method="POST" action="{{ route('workspace.emails.messages.send') }}" enctype="multipart/form-data" class="space-y-3">
                                @csrf
                                <input type="hidden" name="email_account_id" value="{{ $currentAccount->id }}">
                                @if($selectedMessage)
                                    <input type="hidden" name="reply_to_message_id" value="{{ $selectedMessage->id }}">
                                @endif
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-slate-600">المرسل (Alias)</label>
                                        <select name="sender_alias" class="w-full rounded-lg border-slate-300 text-sm">
                                            <option value="">{{ $currentAccount->name }}</option>
                                            @foreach(($currentAccount->aliases ?? []) as $alias)
                                                <option value="{{ $alias }}">{{ $alias }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-slate-600">إلى</label>
                                        <input name="recipient" class="w-full rounded-lg border-slate-300 text-sm" placeholder="client@example.com" required>
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-600">الموضوع</label>
                                    <input name="subject" value="{{ $selectedMessage ? 'Re: '.$selectedMessage->subject : '' }}" class="w-full rounded-lg border-slate-300 text-sm">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-600">نص الرسالة</label>
                                    <textarea name="body" rows="6" class="w-full rounded-lg border-slate-300 text-sm leading-7" required>{{ $selectedMessage ? "\n\n---\n".$selectedMessage->body : '' }}</textarea>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-600">المرفقات (حد أقصى 10 ملفات)</label>
                                    <input type="file" name="attachments[]" multiple class="w-full rounded-lg border-slate-300 text-sm">
                                </div>
                                <button class="rounded-lg bg-[#06C2A4] px-4 py-2 text-sm font-semibold text-white hover:bg-[#05ab91]">
                                    إرسال عبر Queue
                                </button>
                            </form>
                        @else
                            <p class="text-sm text-slate-500">أضف حساب بريد أولاً لتفعيل محرر الرسائل.</p>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
