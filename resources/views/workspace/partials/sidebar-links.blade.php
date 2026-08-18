@php
    $links = [
        ['label' => 'الرئيسية', 'route' => 'workspace.dashboard', 'active' => 'workspace.dashboard'],
        ['label' => 'التصنيفات', 'route' => 'workspace.categories.index', 'active' => 'workspace.categories.*'],
        ['label' => 'المنتجات', 'route' => 'workspace.products.index', 'active' => 'workspace.products.*'],
        ['label' => 'المخزون', 'route' => 'workspace.inventory.index', 'active' => 'workspace.inventory.*'],
        ['label' => 'العملاء', 'route' => 'workspace.customers.index', 'active' => 'workspace.customers.*'],
        ['label' => 'الطلبات', 'route' => 'workspace.orders.index', 'active' => 'workspace.orders.*'],
        ['label' => 'المحادثات', 'route' => 'workspace.conversations.index', 'active' => 'workspace.conversations.*'],
        ['label' => 'المدفوعات', 'route' => 'workspace.payments.index', 'active' => 'workspace.payments.*'],
        ['label' => 'بوابات الدفع', 'route' => 'workspace.payment-gateways.index', 'active' => 'workspace.payment-gateways.*'],
        ['label' => 'الاشتراكات', 'route' => 'workspace.subscriptions.index', 'active' => 'workspace.subscriptions.*'],
        ['label' => 'واتساب', 'route' => 'workspace.whatsapp-accounts.index', 'active' => 'workspace.whatsapp-accounts.*'],
        ['label' => 'إعدادات الذكاء الاصطناعي', 'route' => 'workspace.ai-settings.edit', 'active' => 'workspace.ai-settings.*'],
        ['label' => 'الموظفون', 'route' => 'workspace.employees.index', 'active' => 'workspace.employees.*'],
    ];
@endphp

<div class="space-y-1">
    @foreach($links as $link)
        @php
            $isActive = request()->routeIs($link['active']);
        @endphp
        <a href="{{ route($link['route']) }}"
            class="{{ $isActive ? 'bg-[#06C2A4] text-white shadow-sm' : 'text-gray-700 hover:bg-[#E8FAF6] hover:text-[#06C2A4]' }} flex items-center justify-between rounded-xl px-4 py-2.5 text-sm font-medium transition">
            <span>{{ $link['label'] }}</span>
            @if($isActive)
                <span class="h-2 w-2 rounded-full bg-white/95"></span>
            @endif
        </a>
    @endforeach
</div>
