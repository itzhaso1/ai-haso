<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold">الاشتراكات والخطط</h2></x-slot>
    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 space-y-6">
            @include('workspace.partials.nav')
            @include('partials.flash')

            <div class="rounded-xl border bg-white p-6">
                <h3 class="mb-2 font-semibold">الاشتراك الحالي</h3>
                @if($currentSubscription)
                    <p class="text-sm text-gray-600">الخطة: {{ $currentSubscription->plan?->name ?? '-' }}</p>
                    <p class="text-sm text-gray-600">الحالة: {{ $currentSubscription->status }}</p>
                    <p class="text-sm text-gray-600">ينتهي: {{ $currentSubscription->current_period_end ?? '-' }}</p>
                @else
                    <p class="text-sm text-gray-500">لا يوجد اشتراك نشط.</p>
                @endif
            </div>

            <div class="rounded-xl border bg-white p-6">
                <h3 class="mb-3 font-semibold">تفعيل خطة</h3>
                <form method="POST" action="{{ route('workspace.subscriptions.store') }}" class="flex gap-2">
                    @csrf
                    <select name="plan_id" class="w-full rounded-lg border-gray-300">
                        @foreach($availablePlans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }} - {{ number_format((float)$plan->price,2) }} {{ $plan->currency }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-lg bg-blue-600 px-4 py-2 text-white">تفعيل</button>
                </form>
            </div>

            <div class="rounded-xl border bg-white p-6">
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
    </div>
</x-app-layout>
