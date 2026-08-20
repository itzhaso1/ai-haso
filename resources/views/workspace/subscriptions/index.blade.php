<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-900">الاشتراكات والخطط</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        @include('workspace.partials.nav')
        @include('partials.flash')

        <div class="rounded-2xl border border-[#BDEFE5] bg-[#F3FCFA] p-5">
            <h3 class="text-base font-semibold text-[#067e6b]">تدفق التفعيل (جاهز للربط مع HyperPay)</h3>
            <p class="mt-2 text-sm text-gray-700">
                اختر الباقة → انتقل لصفحة الدفع → تأكيد الدفع → <span class="font-semibold">بعد نجاح الدفع فقط</span> يتم تفعيل الاشتراك وإعطاء المميزات.
            </p>
            <div class="mt-3 grid gap-2 text-xs text-gray-600 sm:grid-cols-3">
                <p>Subscription Status: <span class="font-semibold">pending_activation / activated</span></p>
                <p>Payment Status: <span class="font-semibold">pending / paid / failed</span></p>
                <p>Checkout Status: <span class="font-semibold">awaiting_payment / completed</span></p>
            </div>
        </div>

        <div class="rounded-2xl border bg-white p-6">
            <h3 class="mb-2 font-semibold">الاشتراك الحالي</h3>
            @if($currentSubscription)
                <div class="grid gap-2 text-sm text-gray-700 sm:grid-cols-3">
                    <p><span class="font-semibold">الخطة:</span> {{ $currentSubscription->plan?->name ?? '-' }}</p>
                    <p><span class="font-semibold">الحالة:</span> {{ $currentSubscription->status }}</p>
                    <p><span class="font-semibold">ينتهي:</span> {{ $currentSubscription->current_period_end?->format('Y-m-d H:i') ?? '-' }}</p>
                </div>
            @else
                <p class="text-sm text-gray-500">لا يوجد اشتراك نشط حالياً.</p>
            @endif
        </div>

        <div class="rounded-2xl border bg-white p-6">
            <h3 class="mb-4 font-semibold">اختر الباقة</h3>

            @if($availablePlans->count() === 0)
                <p class="text-sm text-gray-500">لا توجد باقات متاحة حاليًا لنوع مساحة العمل هذه.</p>
            @else
                <div class="grid gap-4 lg:grid-cols-3">
                    @foreach($availablePlans as $plan)
                        <article class="rounded-2xl border border-gray-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <h4 class="text-lg font-bold text-gray-900">{{ $plan->name }}</h4>
                                <span class="rounded-full bg-[#E8FAF6] px-3 py-1 text-xs font-semibold text-[#067e6b]">{{ $plan->workspace_type }}</span>
                            </div>
                            <p class="mt-2 text-2xl font-extrabold text-[#06C2A4]">
                                {{ number_format((float)$plan->price, 2) }} <span class="text-sm text-gray-600">{{ $plan->currency }}</span>
                            </p>
                            <p class="mt-1 text-xs text-gray-500">الفترة: {{ $plan->billing_period }}</p>

                            <div class="mt-4">
                                <p class="mb-1 text-xs font-semibold text-gray-700">المميزات</p>
                                <div class="flex flex-wrap gap-1">
                                    @foreach(array_slice($plan->features ?? [], 0, 6) as $feature)
                                        <span class="rounded-md bg-gray-100 px-2 py-1 text-[11px] text-gray-700">{{ $feature }}</span>
                                    @endforeach
                                </div>
                            </div>

                            <form method="POST" action="{{ route('workspace.subscriptions.store') }}" class="mt-5 space-y-2">
                                @csrf
                                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                <label class="block text-xs font-semibold text-gray-700">بوابة الدفع</label>
                                <select name="payment_provider" class="w-full rounded-lg border-gray-300 text-sm">
                                    <option value="hyperpay">HyperPay (جاهز للربط)</option>
                                    <option value="local">Local Sandbox</option>
                                </select>
                                <button class="w-full rounded-lg bg-[#06C2A4] px-4 py-2 text-sm font-semibold text-white hover:bg-[#04a98e]">
                                    متابعة إلى الدفع
                                </button>
                            </form>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border bg-white p-6">
            <h3 class="mb-3 font-semibold">حالات الدفع للاشتراكات (Checkout Sessions)</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right">الباقة</th>
                            <th class="px-4 py-3 text-right">Checkout</th>
                            <th class="px-4 py-3 text-right">Payment</th>
                            <th class="px-4 py-3 text-right">Subscription</th>
                            <th class="px-4 py-3 text-right">المبلغ</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($checkoutSessions as $session)
                            <tr>
                                <td class="px-4 py-3">{{ $session->plan?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $session->checkout_status }}</td>
                                <td class="px-4 py-3">{{ $session->payment_status }}</td>
                                <td class="px-4 py-3">{{ $session->subscription_status }}</td>
                                <td class="px-4 py-3">{{ number_format((float) $session->amount, 2) }} {{ $session->currency }}</td>
                                <td class="px-4 py-3 text-left">
                                    <a href="{{ route('workspace.subscriptions.checkout.show', $session) }}" class="text-[#06C2A4]">فتح</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">لا توجد عمليات اشتراك بعد.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $checkoutSessions->links() }}</div>
        </div>

        <div class="rounded-2xl border bg-white p-6">
            <h3 class="mb-3 font-semibold">سجل الاشتراكات</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right">الخطة</th>
                            <th class="px-4 py-3 text-right">الحالة</th>
                            <th class="px-4 py-3 text-right">الفترة</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($subscriptions as $subscription)
                            <tr>
                                <td class="px-4 py-3">{{ $subscription->plan?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $subscription->status }}</td>
                                <td class="px-4 py-3">{{ $subscription->current_period_start }} → {{ $subscription->current_period_end }}</td>
                                <td class="px-4 py-3 text-left">
                                    @if(in_array($subscription->status, ['active', 'trialing', 'past_due'], true))
                                        <form method="POST" action="{{ route('workspace.subscriptions.destroy', $subscription) }}" class="inline">
                                            @csrf @method('DELETE')
                                            <button class="text-red-600" onclick="return confirm('إلغاء الاشتراك الحالي؟')">إلغاء</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">لا يوجد سجل.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $subscriptions->links() }}</div>
        </div>
    </div>
</x-app-layout>
