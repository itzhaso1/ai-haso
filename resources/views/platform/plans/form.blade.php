@php
    /** @var \App\Models\Plan|null $planData */
    $planData = isset($plan) ? $plan : null;
    $defaultFeatures = [
        'dashboard' => 'لوحة التحكم',
        'products' => 'المنتجات',
        'categories' => 'التصنيفات',
        'inventory' => 'المخزون',
        'customers' => 'العملاء',
        'orders' => 'الطلبات',
        'pos' => 'نقاط البيع',
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
    $tierOptions = [
        '' => '— بدون مستوى —',
        'starter' => 'مبتدئ',
        'growth' => 'نمو',
        'pro' => 'احترافي',
        'enterprise' => 'مؤسسات',
    ];

    $selectedFeatures = old('features', $planData?->features ?? []);
    $selectedPermissions = old('permissions', $planData?->permissions ?? []);
@endphp
<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm font-semibold text-gray-700">رمز الخطة (Code)</label>
        <input name="code" value="{{ old('code', $planData?->code) }}" class="w-full rounded-lg border-gray-300" />
    </div>
    <div>
        <label class="mb-1 block text-sm font-semibold text-gray-700">اسم الخطة</label>
        <input name="name" required value="{{ old('name', $planData?->name) }}" class="w-full rounded-lg border-gray-300" />
    </div>
    <div>
        <label class="mb-1 block text-sm font-semibold text-gray-700">المستوى التجاري (Tier)</label>
        <select name="tier" class="w-full rounded-lg border-gray-300">
            @foreach($tierOptions as $tierValue => $tierLabel)
                <option value="{{ $tierValue }}" @selected(old('tier', $planData?->tier ?? '') === $tierValue)>{{ $tierLabel }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-sm font-semibold text-gray-700">نوع مساحة العمل</label>
        <select name="workspace_type" class="w-full rounded-lg border-gray-300">
            @foreach(['individual' => 'فردي', 'company' => 'شركة', 'store' => 'متجر'] as $type => $label)
                <option value="{{ $type }}" @selected(old('workspace_type', $planData?->workspace_type ?? 'individual') === $type)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-sm font-semibold text-gray-700">دورة الفوترة</label>
        <select name="billing_period" class="w-full rounded-lg border-gray-300">
            @foreach(['monthly' => 'شهري', 'yearly' => 'سنوي', 'lifetime' => 'مدى الحياة'] as $period => $label)
                <option value="{{ $period }}" @selected(old('billing_period', $planData?->billing_period ?? 'monthly') === $period)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-sm font-semibold text-gray-700">العملة</label>
        <input name="currency" value="{{ old('currency', $planData?->currency ?? 'USD') }}" class="w-full rounded-lg border-gray-300" />
    </div>
    <div>
        <label class="mb-1 block text-sm font-semibold text-gray-700">السعر</label>
        <input type="number" step="0.01" name="price" value="{{ old('price', $planData?->price ?? 0) }}" class="w-full rounded-lg border-gray-300" />
    </div>
    <div class="flex flex-wrap items-center gap-6 mt-7">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $planData?->is_active ?? true))>
            <span class="text-sm font-semibold text-gray-700">نشطة</span>
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $planData?->is_public ?? true))>
            <span class="text-sm font-semibold text-gray-700">عامة (ظاهرة للعملاء)</span>
        </label>
    </div>
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
    <label class="mb-1 block text-sm font-semibold text-gray-700">Features JSON (تخصيص متقدم)</label>
    <textarea name="features_json" rows="4" class="w-full rounded-lg border-gray-300 font-mono text-xs">{{ old('features_json', $featuresJson ?? json_encode($planData?->features ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>
</div>
<div>
    <label class="mb-1 block text-sm font-semibold text-gray-700">Permissions JSON (تخصيص متقدم)</label>
    <textarea name="permissions_json" rows="4" class="w-full rounded-lg border-gray-300 font-mono text-xs">{{ old('permissions_json', $permissionsJson ?? json_encode($planData?->permissions ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>
</div>
<div>
    <label class="mb-1 block text-sm font-semibold text-gray-700">Limits JSON (حدود الاستخدام)</label>
    <textarea name="limits_json" rows="4" class="w-full rounded-lg border-gray-300 font-mono text-xs">{{ old('limits_json', $limitsJson ?? json_encode($planData?->limits ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>
</div>
