@php
    $sections = [
        'لوحة التحكم' => [
            ['label' => 'لوحة التحكم', 'route' => 'workspace.finance.dashboard', 'active' => 'workspace.finance.dashboard'],
        ],
        'المبيعات' => [
            ['label' => 'المبيعات', 'route' => 'workspace.finance.sales.index', 'active' => 'workspace.finance.sales.*'],
            ['label' => 'الفواتير', 'route' => 'workspace.finance.invoices.index', 'active' => 'workspace.finance.invoices.*'],
            ['label' => 'عروض الأسعار', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'quotations'], 'module_key' => 'quotations'],
            ['label' => 'العملاء', 'route' => 'workspace.finance.customers.index', 'active' => 'workspace.finance.customers.*'],
            ['label' => 'المدفوعات', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'sales-payments'], 'module_key' => 'sales-payments'],
            ['label' => 'الإشعارات الدائنة', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'credit-notes'], 'module_key' => 'credit-notes'],
            ['label' => 'الإشعارات المدينة', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'debit-notes'], 'module_key' => 'debit-notes'],
        ],
        'المشتريات' => [
            ['label' => 'فواتير الشراء', 'route' => 'workspace.finance.invoices.index', 'params' => ['type' => 'purchase'], 'active' => 'workspace.finance.invoices.*'],
            ['label' => 'الموردون', 'route' => 'workspace.finance.suppliers.index', 'active' => 'workspace.finance.suppliers.*'],
            ['label' => 'مدفوعات الموردين', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'supplier-payments'], 'module_key' => 'supplier-payments'],
        ],
        'المصروفات' => [
            ['label' => 'المصروفات', 'route' => 'workspace.finance.expenses.index', 'active' => 'workspace.finance.expenses.*'],
            ['label' => 'تصنيفات المصروفات', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'expense-categories'], 'module_key' => 'expense-categories'],
            ['label' => 'المصروفات المتكررة', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'recurring-expenses'], 'module_key' => 'recurring-expenses'],
        ],
        'المنتجات والخدمات' => [
            ['label' => 'المنتجات', 'route' => 'workspace.finance.products.index', 'active' => 'workspace.finance.products.*'],
            ['label' => 'الخدمات', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'services'], 'module_key' => 'services'],
            ['label' => 'الأسعار', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'price-lists'], 'module_key' => 'price-lists'],
        ],
        'المخزون' => [
            ['label' => 'المخزون', 'route' => 'workspace.finance.inventory.index', 'active' => 'workspace.finance.inventory.*'],
            ['label' => 'المستودعات', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'warehouses'], 'module_key' => 'warehouses'],
            ['label' => 'حركات المخزون', 'route' => 'workspace.finance.inventory.index', 'active' => 'workspace.finance.inventory.*'],
            ['label' => 'التسويات', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'stock-adjustments'], 'module_key' => 'stock-adjustments'],
            ['label' => 'التحويلات', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'stock-transfers'], 'module_key' => 'stock-transfers'],
        ],
        'المحاسبة' => [
            ['label' => 'لوحة المحاسبة', 'route' => 'workspace.finance.accounting.dashboard', 'active' => 'workspace.finance.accounting.*'],
            ['label' => 'دليل الحسابات', 'route' => 'workspace.finance.accounting.dashboard', 'active' => 'workspace.finance.accounting.*'],
            ['label' => 'القيود اليومية', 'route' => 'workspace.finance.accounting.dashboard', 'active' => 'workspace.finance.accounting.*'],
            ['label' => 'دفتر الأستاذ', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'general-ledger'], 'module_key' => 'general-ledger'],
            ['label' => 'ميزان المراجعة', 'route' => 'workspace.finance.accounting.dashboard', 'active' => 'workspace.finance.accounting.*'],
            ['label' => 'السنوات المالية', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'fiscal-years'], 'module_key' => 'fiscal-years'],
            ['label' => 'الفترات المحاسبية', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'accounting-periods'], 'module_key' => 'accounting-periods'],
        ],
        'الرواتب' => [
            ['label' => 'الموظفون', 'route' => 'workspace.finance.payroll.index', 'active' => 'workspace.finance.payroll.*'],
            ['label' => 'مسيرات الرواتب', 'route' => 'workspace.finance.payroll.index', 'active' => 'workspace.finance.payroll.*'],
            ['label' => 'البدلات', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'allowances'], 'module_key' => 'allowances'],
            ['label' => 'الخصومات', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'deductions'], 'module_key' => 'deductions'],
            ['label' => 'السلف', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'salary-advances'], 'module_key' => 'salary-advances'],
            ['label' => 'المكافآت', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'bonuses'], 'module_key' => 'bonuses'],
        ],
        'الضرائب' => [
            ['label' => 'VAT', 'route' => 'workspace.finance.vat.index', 'active' => 'workspace.finance.vat.*'],
            ['label' => 'إعدادات الضرائب', 'route' => 'workspace.finance.settings.index', 'active' => 'workspace.finance.settings.*'],
            ['label' => 'تقارير VAT', 'route' => 'workspace.finance.reports.index', 'active' => 'workspace.finance.reports.*'],
        ],
        'النقد والبنوك' => [
            ['label' => 'الصندوق', 'route' => 'workspace.finance.cashbox.index', 'active' => 'workspace.finance.cashbox.*'],
            ['label' => 'الحسابات البنكية', 'route' => 'workspace.finance.banks.index', 'active' => 'workspace.finance.banks.*'],
            ['label' => 'الإيداعات', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'deposits'], 'module_key' => 'deposits'],
            ['label' => 'السحوبات', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'withdrawals'], 'module_key' => 'withdrawals'],
            ['label' => 'التحويلات', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'bank-transfers'], 'module_key' => 'bank-transfers'],
            ['label' => 'التسويات البنكية', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'bank-reconciliation'], 'module_key' => 'bank-reconciliation'],
        ],
        'التقارير' => [
            ['label' => 'التقارير', 'route' => 'workspace.finance.reports.index', 'active' => 'workspace.finance.reports.*'],
            ['label' => 'الأرباح والخسائر', 'route' => 'workspace.finance.reports.index', 'active' => 'workspace.finance.reports.*'],
            ['label' => 'الميزانية العمومية', 'route' => 'workspace.finance.modules.show', 'params' => ['key' => 'balance-sheet'], 'module_key' => 'balance-sheet'],
            ['label' => 'التدفق النقدي', 'route' => 'workspace.finance.reports.index', 'active' => 'workspace.finance.reports.*'],
            ['label' => 'المبيعات', 'route' => 'workspace.finance.reports.index', 'active' => 'workspace.finance.reports.*'],
            ['label' => 'المشتريات', 'route' => 'workspace.finance.reports.index', 'active' => 'workspace.finance.reports.*'],
            ['label' => 'المصروفات', 'route' => 'workspace.finance.reports.index', 'active' => 'workspace.finance.reports.*'],
            ['label' => 'العملاء', 'route' => 'workspace.finance.reports.index', 'active' => 'workspace.finance.reports.*'],
            ['label' => 'الموردون', 'route' => 'workspace.finance.reports.index', 'active' => 'workspace.finance.reports.*'],
            ['label' => 'الضرائب', 'route' => 'workspace.finance.reports.index', 'active' => 'workspace.finance.reports.*'],
            ['label' => 'الرواتب', 'route' => 'workspace.finance.reports.index', 'active' => 'workspace.finance.reports.*'],
        ],
        'الإعدادات' => [
            ['label' => 'بيانات الشركة', 'route' => 'workspace.finance.settings.index', 'active' => 'workspace.finance.settings.*'],
            ['label' => 'إعدادات الفواتير', 'route' => 'workspace.finance.settings.index', 'active' => 'workspace.finance.settings.*'],
            ['label' => 'الضرائب', 'route' => 'workspace.finance.settings.index', 'active' => 'workspace.finance.settings.*'],
            ['label' => 'العملات', 'route' => 'workspace.finance.settings.index', 'active' => 'workspace.finance.settings.*'],
            ['label' => 'الحسابات', 'route' => 'workspace.finance.settings.index', 'active' => 'workspace.finance.settings.*'],
            ['label' => 'إعدادات المحاسبة', 'route' => 'workspace.finance.settings.index', 'active' => 'workspace.finance.settings.*'],
        ],
    ];
@endphp

<div class="h-full overflow-y-auto px-4 py-5">
    <div class="mb-5 border-b border-slate-200 pb-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">HASem</p>
        <h2 class="mt-1 text-xl font-extrabold text-[#06C2A4]">Financial</h2>
        <p class="mt-1 text-xs text-slate-500">الفوترة والحسابات المتكاملة</p>
    </div>

    <nav class="space-y-4">
        @foreach($sections as $sectionTitle => $links)
            <div>
                <h3 class="mb-2 px-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $sectionTitle }}</h3>
                <div class="space-y-1">
                    @foreach($links as $link)
                        @php
                            $isModuleLink = isset($link['module_key']);
                            $isActive = $isModuleLink
                                ? request()->routeIs('workspace.finance.modules.show') && request()->route('key') === $link['module_key']
                                : request()->routeIs($link['active'] ?? $link['route']);
                        @endphp
                        <a href="{{ route($link['route'], $link['params'] ?? []) }}"
                           class="{{ $isActive ? 'bg-[#06C2A4] text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' }} flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium transition">
                            <span>{{ $link['label'] }}</span>
                            @if($isActive)
                                <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>
</div>
