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
                <form method="POST" action="{{ route('workspace.emails.messages.send') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="hidden" name="reply_to_message_id" value="{{ $draft['reply_to_message_id'] ?? '' }}">

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
                        <input type="text" name="recipient" value="{{ $draft['recipient'] ?? '' }}" required placeholder="client@example.com"
                               class="w-full rounded-lg border-slate-300 text-sm">
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
        </aside>
    </div>
@endsection
