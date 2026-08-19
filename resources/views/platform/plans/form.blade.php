@php($plan = $plan ?? null)
@php
    $defaultFeatures = [
        'dashboard' => 'لوحة التحكم',
        'products' => 'المنتجات',
        'categories' => 'التصنيفات',
        'inventory' => 'المخزون',
        'customers' => 'العملاء',
        'orders' => 'الطلبات',
        'conversations' => 'المحادثات',
        'messages' => 'الرسائل',
        'smart_replies' => 'الردود الذكية',
        'ai' => 'الذكاء الاصطناعي',
        'whatsapp' => 'واتساب',
        'payments' => 'المدفوعات',
        'employees' => 'الموظفون',
        'analytics' => 'التحليلات',
        'subscription' => 'الاشتراكات',
    ];
    $defaultPermissions = [
        'workspace.view' => 'عرض المساحة',
        'workspace.manage' => 'إدارة المساحة',
        'products.manage' => 'إدارة المنتجات',
        'inventory.manage' => 'إدارة المخزون',
        'customers.manage' => 'إدارة العملاء',
        'orders.manage' => 'إدارة الطلبات',
        'conversations.manage' => 'إدارة المحادثات',
        'ai.manage' => 'إدارة الذكاء الاصطناعي',
        'whatsapp.manage' => 'إدارة واتساب',
        'payments.manage' => 'إدارة المدفوعات',
        'employees.manage' => 'إدارة الموظفين',
        'subscriptions.manage' => 'إدارة الاشتراكات',
    ];

    $selectedFeatures = old('features', $plan?->features ?? []);
    $selectedPermissions = old('permissions', $plan?->permissions ?? []);
@endphp
<div class="grid gap-4 md:grid-cols-2">
    <div><label class="mb-1 block text-sm">Code</label><input name="code" value="{{ old('code', $plan?->code) }}" class="w-full rounded-lg border-gray-300" /></div>
    <div><label class="mb-1 block text-sm">Name</label><input name="name" required value="{{ old('name', $plan?->name) }}" class="w-full rounded-lg border-gray-300" /></div>
    <div>
        <label class="mb-1 block text-sm">Workspace Type</label>
        <select name="workspace_type" class="w-full rounded-lg border-gray-300">
            @foreach(['individual','company','store'] as $type)
                <option value="{{ $type }}" @selected(old('workspace_type', $plan?->workspace_type ?? 'individual') === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-sm">Billing Period</label>
        <select name="billing_period" class="w-full rounded-lg border-gray-300">
            @foreach(['monthly','yearly','lifetime'] as $period)
                <option value="{{ $period }}" @selected(old('billing_period', $plan?->billing_period ?? 'monthly') === $period)>{{ $period }}</option>
            @endforeach
        </select>
    </div>
    <div><label class="mb-1 block text-sm">Currency</label><input name="currency" value="{{ old('currency', $plan?->currency ?? 'USD') }}" class="w-full rounded-lg border-gray-300" /></div>
    <div><label class="mb-1 block text-sm">Price</label><input type="number" step="0.01" name="price" value="{{ old('price', $plan?->price ?? 0) }}" class="w-full rounded-lg border-gray-300" /></div>
    <label class="inline-flex items-center gap-2 mt-7">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan?->is_active ?? true))>
        <span>نشطة</span>
    </label>
</div>

<div>
    <label class="mb-2 block text-sm font-semibold text-gray-700">خصائص الخطة (Features)</label>
    <div class="grid gap-2 rounded-xl border border-gray-200 bg-gray-50 p-4 md:grid-cols-3">
        @foreach($defaultFeatures as $featureKey => $featureLabel)
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="features[]" value="{{ $featureKey }}" @checked(in_array($featureKey, $selectedFeatures, true))>
                <span>{{ $featureLabel }}</span>
            </label>
        @endforeach
    </div>
</div>

<div>
    <label class="mb-2 block text-sm font-semibold text-gray-700">صلاحيات الخطة (Permissions)</label>
    <div class="grid gap-2 rounded-xl border border-gray-200 bg-gray-50 p-4 md:grid-cols-2">
        @foreach($defaultPermissions as $permissionKey => $permissionLabel)
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="permissions[]" value="{{ $permissionKey }}" @checked(in_array($permissionKey, $selectedPermissions, true))>
                <span>{{ $permissionLabel }}</span>
            </label>
        @endforeach
    </div>
</div>

<div>
    <label class="mb-1 block text-sm">Features JSON (اختياري للتخصيص المتقدم)</label>
    <textarea name="features_json" rows="4" class="w-full rounded-lg border-gray-300">{{ old('features_json', $featuresJson ?? json_encode($plan?->features ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>
</div>
<div>
    <label class="mb-1 block text-sm">Permissions JSON (اختياري للتخصيص المتقدم)</label>
    <textarea name="permissions_json" rows="4" class="w-full rounded-lg border-gray-300">{{ old('permissions_json', $permissionsJson ?? json_encode($plan?->permissions ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>
</div>
<div>
    <label class="mb-1 block text-sm">Limits JSON</label>
    <textarea name="limits_json" rows="4" class="w-full rounded-lg border-gray-300">{{ old('limits_json', $limitsJson ?? json_encode($plan?->limits ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>
</div>
