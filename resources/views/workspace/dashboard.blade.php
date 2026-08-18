<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-900">لوحة {{ $workspace->name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @include('workspace.partials.nav')
            @include('partials.flash')

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                    <p class="text-sm text-gray-500">المحادثات</p>
                    <p class="mt-2 text-2xl font-bold">{{ $stats['conversations'] }}</p>
                </div>
                <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                    <p class="text-sm text-gray-500">حالة الاشتراك</p>
                    <p class="mt-2 text-2xl font-bold">{{ $stats['subscription_status'] }}</p>
                </div>
                <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                    <p class="text-sm text-gray-500">استهلاك AI (30 يوم)</p>
                    <p class="mt-2 text-2xl font-bold">{{ number_format($stats['ai_tokens_30d']) }}</p>
                </div>
                @if($isCommercial)
                    <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                        <p class="text-sm text-gray-500">طلبات اليوم</p>
                        <p class="mt-2 text-2xl font-bold">{{ $stats['orders_today'] }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                        <p class="text-sm text-gray-500">طلبات مدفوعة</p>
                        <p class="mt-2 text-2xl font-bold">{{ $stats['paid_orders'] }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                        <p class="text-sm text-gray-500">إجمالي المبيعات</p>
                        <p class="mt-2 text-2xl font-bold">{{ number_format($stats['sales_total'], 2) }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                        <p class="text-sm text-gray-500">العملاء</p>
                        <p class="mt-2 text-2xl font-bold">{{ $stats['customers'] }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                        <p class="text-sm text-gray-500">المنتجات</p>
                        <p class="mt-2 text-2xl font-bold">{{ $stats['products'] }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                        <p class="text-sm text-gray-500">مدفوعات ناجحة</p>
                        <p class="mt-2 text-2xl font-bold">{{ $stats['paid_payments'] }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
