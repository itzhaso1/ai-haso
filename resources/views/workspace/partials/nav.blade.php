<div class="mb-6 rounded-xl border border-gray-200 bg-white p-4">
    <div class="flex flex-wrap gap-2 text-sm">
        <a href="{{ route('workspace.dashboard') }}" class="px-3 py-2 rounded-lg {{ request()->routeIs('workspace.dashboard') ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">الرئيسية</a>
        <a href="{{ route('workspace.categories.index') }}" class="px-3 py-2 rounded-lg {{ request()->routeIs('workspace.categories.*') ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">التصنيفات</a>
        <a href="{{ route('workspace.products.index') }}" class="px-3 py-2 rounded-lg {{ request()->routeIs('workspace.products.*') ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">المنتجات</a>
        <a href="{{ route('workspace.inventory.index') }}" class="px-3 py-2 rounded-lg {{ request()->routeIs('workspace.inventory.*') ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">المخزون</a>
        <a href="{{ route('workspace.customers.index') }}" class="px-3 py-2 rounded-lg {{ request()->routeIs('workspace.customers.*') ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">العملاء</a>
        <a href="{{ route('workspace.orders.index') }}" class="px-3 py-2 rounded-lg {{ request()->routeIs('workspace.orders.*') ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">الطلبات</a>
        <a href="{{ route('workspace.conversations.index') }}" class="px-3 py-2 rounded-lg {{ request()->routeIs('workspace.conversations.*') ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">المحادثات</a>
        <a href="{{ route('workspace.payments.index') }}" class="px-3 py-2 rounded-lg {{ request()->routeIs('workspace.payments.*') ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">المدفوعات</a>
        <a href="{{ route('workspace.payment-gateways.index') }}" class="px-3 py-2 rounded-lg {{ request()->routeIs('workspace.payment-gateways.*') ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">بوابات الدفع</a>
        <a href="{{ route('workspace.subscriptions.index') }}" class="px-3 py-2 rounded-lg {{ request()->routeIs('workspace.subscriptions.*') ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">الاشتراك</a>
        <a href="{{ route('workspace.whatsapp-accounts.index') }}" class="px-3 py-2 rounded-lg {{ request()->routeIs('workspace.whatsapp-accounts.*') ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">واتساب</a>
        <a href="{{ route('workspace.ai-settings.edit') }}" class="px-3 py-2 rounded-lg {{ request()->routeIs('workspace.ai-settings.*') ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">إعدادات AI</a>
        <a href="{{ route('workspace.employees.index') }}" class="px-3 py-2 rounded-lg {{ request()->routeIs('workspace.employees.*') ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">الموظفون</a>
    </div>
</div>
