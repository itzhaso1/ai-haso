@php($plan = $plan ?? null)
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
            @foreach(['monthly','quarterly','yearly','lifetime'] as $period)
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
    <label class="mb-1 block text-sm">Features JSON</label>
    <textarea name="features_json" rows="4" class="w-full rounded-lg border-gray-300">{{ old('features_json', $featuresJson ?? json_encode($plan?->features ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>
</div>
<div>
    <label class="mb-1 block text-sm">Limits JSON</label>
    <textarea name="limits_json" rows="4" class="w-full rounded-lg border-gray-300">{{ old('limits_json', $limitsJson ?? json_encode($plan?->limits ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>
</div>
