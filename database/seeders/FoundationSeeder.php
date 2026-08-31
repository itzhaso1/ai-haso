<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlatformAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;

class FoundationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'workspace.view',
            'workspace.manage',
            'products.manage',
            'inventory.manage',
            'customers.manage',
            'orders.manage',
            'pos.manage',
            'tables.manage',
            'menu.manage',
            'conversations.manage',
            'ai.manage',
            'whatsapp.manage',
            'payments.manage',
            'employees.manage',
            'subscriptions.manage',
            'finance.view',
            'finance.manage',
            'finance.sales.view',
            'finance.price_lists.view',
            'finance.price_lists.manage',
            'finance.adjustments.view',
            'finance.adjustments.manage',
            'finance.salary_advances.view',
            'finance.salary_advances.manage',
            'finance.fiscal_years.view',
            'finance.fiscal_years.manage',
            'invoices.view',
            'invoices.create',
            'invoices.edit',
            'invoices.cancel',
            'invoices.delete',
            'expenses.view',
            'expenses.create',
            'expenses.edit',
            'accounting.view',
            'accounting.manage',
            'payroll.view',
            'payroll.manage',
            'reports.view',
            'finance.settings',
            'contracts.view',
            'contracts.manage',
            'appointments.view',
            'appointments.manage',
            'appointments.requests.view',
            'appointments.requests.manage',
            'appointments.calendar.view',
            'appointments.billing.manage',
            'appointments.settings.manage',
            'appointments.website.manage',
            'appointments.domains.manage',
        ];

        foreach (array_values(array_unique($permissions)) as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach (['owner', 'admin', 'manager', 'agent', 'receptionist', 'staff_doctor', 'accountant'] as $roleName) {
            Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }

        $ownerRole = Role::findByName('owner', 'web');
        $adminRole = Role::findByName('admin', 'web');
        $managerRole = Role::findByName('manager', 'web');
        $agentRole = Role::findByName('agent', 'web');
        $receptionistRole = Role::findByName('receptionist', 'web');
        $staffDoctorRole = Role::findByName('staff_doctor', 'web');
        $accountantRole = Role::findByName('accountant', 'web');

        $allPermissions = Permission::query()->pluck('name')->all();
        $ownerRole->syncPermissions($allPermissions);
        $adminRole->syncPermissions($allPermissions);
        $managerRole->syncPermissions([
            'workspace.view',
            'products.manage',
            'inventory.manage',
            'customers.manage',
            'orders.manage',
            'pos.manage',
            'tables.manage',
            'menu.manage',
            'conversations.manage',
            'payments.manage',
            'finance.view',
            'finance.sales.view',
            'finance.price_lists.view',
            'finance.adjustments.view',
            'finance.salary_advances.view',
            'finance.fiscal_years.view',
            'invoices.view',
            'invoices.create',
            'invoices.edit',
            'expenses.view',
            'expenses.create',
            'expenses.edit',
            'accounting.view',
            'reports.view',
            'payroll.view',
            'contracts.view',
            'contracts.manage',
            'appointments.view',
            'appointments.manage',
            'appointments.requests.view',
            'appointments.requests.manage',
            'appointments.calendar.view',
            'appointments.billing.manage',
            'appointments.settings.manage',
            'appointments.website.manage',
            'appointments.domains.manage',
        ]);
        $agentRole->syncPermissions([
            'workspace.view',
            'customers.manage',
            'orders.manage',
            'pos.manage',
            'tables.manage',
            'menu.manage',
            'conversations.manage',
            'finance.view',
            'finance.sales.view',
            'finance.price_lists.view',
            'finance.adjustments.view',
            'finance.salary_advances.view',
            'finance.fiscal_years.view',
            'invoices.view',
            'expenses.view',
            'reports.view',
            'contracts.view',
            'appointments.view',
            'appointments.requests.view',
            'appointments.calendar.view',
        ]);
        $receptionistRole->syncPermissions([
            'workspace.view',
            'customers.manage',
            'pos.manage',
            'tables.manage',
            'menu.manage',
            'conversations.manage',
            'appointments.view',
            'appointments.manage',
            'appointments.requests.view',
            'appointments.requests.manage',
            'appointments.calendar.view',
            'appointments.billing.manage',
            'appointments.website.manage',
            'appointments.domains.manage',
            'invoices.view',
            'payments.manage',
        ]);
        $staffDoctorRole->syncPermissions([
            'workspace.view',
            'appointments.view',
            'appointments.requests.view',
            'appointments.calendar.view',
        ]);
        $accountantRole->syncPermissions([
            'workspace.view',
            'finance.view',
            'invoices.view',
            'payments.manage',
            'reports.view',
            'contracts.view',
            'appointments.view',
            'appointments.billing.manage',
        ]);

        foreach ($this->commercialPlans() as $plan) {
            Plan::query()->updateOrCreate(['code' => $plan['code']], $plan);
        }

        PlatformAdmin::query()->updateOrCreate(
            ['email' => env('PLATFORM_ADMIN_EMAIL', 'admin@hasem.local')],
            [
                'name' => env('PLATFORM_ADMIN_NAME', 'Platform Admin'),
                'password' => Hash::make(env('PLATFORM_ADMIN_PASSWORD', 'password')),
                'email_verified_at' => now(),
            ]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function commercialPlans(): array
    {
        $currency = (string) config('plans.currency', 'SAR');
        $matrix = config('plans.feature_matrix', []);
        $overageRules = $this->overageRulesFromMeters();

        $tierMeta = [
            'starter' => [
                'sort_order' => 10,
                'price' => 99,
                'trial_days' => 14,
                'description' => 'للبدء: حجوزات، موقع إلكتروني، وذكاء اصطناعي أساسي بدون نقطة بيع أو واتساب.',
            ],
            'pro' => [
                'sort_order' => 20,
                'price' => 199,
                'trial_days' => 14,
                'description' => 'للنمو: نقطة بيع، مالية، واتساب، بريد احترافي، وتحليلات.',
            ],
            'business' => [
                'sort_order' => 30,
                'price' => 349,
                'trial_days' => 14,
                'description' => 'للأعمال: صلاحيات متقدمة، واجهة برمجة API، وإدارة عملاء CRM.',
            ],
            'enterprise' => [
                'sort_order' => 40,
                'price' => 499,
                'trial_days' => 30,
                'description' => 'للمؤسسات: تخصيص كامل، تدقيق، وحدود استخدام مرتفعة.',
            ],
        ];

        $plans = [];

        // Individual plans (legacy codes kept).
        $plans[] = $this->planRow(
            code: 'individual_free',
            name: 'Individual Free',
            description: 'باقة فردية مجانية للمحادثات والردود الذكية.',
            tier: 'starter',
            workspaceType: 'individual',
            currency: $currency,
            price: 0,
            sortOrder: 5,
            isPublic: true,
            trialDays: 0,
            features: ['conversations', 'smart_replies', 'ai', 'subscription', 'usage'],
            permissions: ['workspace.view', 'conversations.manage', 'ai.manage', 'subscriptions.manage'],
            limits: array_merge($matrix['starter']['limits'] ?? [], [
                'ai_usage' => 500,
                'conversations' => 300,
                'whatsapp_numbers' => 0,
                'whatsapp_messages' => 0,
            ]),
            overageRules: $overageRules,
        );

        $plans[] = $this->planRow(
            code: 'individual_pro',
            name: 'Individual Pro',
            description: 'باقة فردية احترافية مع واتساب واستخدام أعلى للذكاء الاصطناعي.',
            tier: 'pro',
            workspaceType: 'individual',
            currency: $currency,
            price: 19,
            sortOrder: 15,
            isPublic: true,
            trialDays: 7,
            features: ['conversations', 'smart_replies', 'ai', 'subscription', 'usage', 'whatsapp'],
            permissions: ['workspace.view', 'conversations.manage', 'ai.manage', 'whatsapp.manage', 'subscriptions.manage'],
            limits: array_merge($matrix['pro']['limits'] ?? [], [
                'ai_usage' => 5000,
                'conversations' => 5000,
                'whatsapp_numbers' => 1,
            ]),
            overageRules: $overageRules,
        );

        $typeLabels = [
            'company' => 'Company',
            'store' => 'Store',
        ];

        // New public commercial plans + legacy basic/pro/enterprise aligned to matrix.
        foreach (['company', 'store'] as $workspaceType) {
            $label = $typeLabels[$workspaceType];

            // New public starter (and legacy basic → starter matrix, hidden from catalog).
            foreach ([
                ['code' => "{$workspaceType}_starter", 'name' => "{$label} Starter", 'is_public' => true],
                ['code' => "{$workspaceType}_basic", 'name' => "{$label} Basic", 'is_public' => false],
            ] as $row) {
                $plans[] = $this->planRow(
                    code: $row['code'],
                    name: $row['name'],
                    description: $tierMeta['starter']['description'],
                    tier: 'starter',
                    workspaceType: $workspaceType,
                    currency: $currency,
                    price: $tierMeta['starter']['price'],
                    sortOrder: $tierMeta['starter']['sort_order'],
                    isPublic: $row['is_public'],
                    trialDays: $tierMeta['starter']['trial_days'],
                    features: $matrix['starter']['features'] ?? [],
                    permissions: $this->permissionsForTier('starter'),
                    limits: $matrix['starter']['limits'] ?? [],
                    overageRules: $overageRules,
                );
            }

            $plans[] = $this->planRow(
                code: "{$workspaceType}_pro",
                name: "{$label} Pro",
                description: $tierMeta['pro']['description'],
                tier: 'pro',
                workspaceType: $workspaceType,
                currency: $currency,
                price: $tierMeta['pro']['price'],
                sortOrder: $tierMeta['pro']['sort_order'],
                isPublic: true,
                trialDays: $tierMeta['pro']['trial_days'],
                features: $matrix['pro']['features'] ?? [],
                permissions: $this->permissionsForTier('pro'),
                limits: $matrix['pro']['limits'] ?? [],
                overageRules: $overageRules,
            );

            $plans[] = $this->planRow(
                code: "{$workspaceType}_business",
                name: "{$label} Business",
                description: $tierMeta['business']['description'],
                tier: 'business',
                workspaceType: $workspaceType,
                currency: $currency,
                price: $tierMeta['business']['price'],
                sortOrder: $tierMeta['business']['sort_order'],
                isPublic: true,
                trialDays: $tierMeta['business']['trial_days'],
                features: $matrix['business']['features'] ?? [],
                permissions: $this->permissionsForTier('business'),
                limits: $matrix['business']['limits'] ?? [],
                overageRules: $overageRules,
            );

            $plans[] = $this->planRow(
                code: "{$workspaceType}_enterprise",
                name: "{$label} Enterprise",
                description: $tierMeta['enterprise']['description'],
                tier: 'enterprise',
                workspaceType: $workspaceType,
                currency: $currency,
                price: $tierMeta['enterprise']['price'],
                sortOrder: $tierMeta['enterprise']['sort_order'],
                isPublic: true,
                trialDays: $tierMeta['enterprise']['trial_days'],
                features: $matrix['enterprise']['features'] ?? [],
                permissions: $this->permissionsForTier('enterprise'),
                limits: $matrix['enterprise']['limits'] ?? [],
                overageRules: $overageRules,
            );
        }

        return $plans;
    }

    /**
     * @param  array<int, string>  $features
     * @param  array<int, string>  $permissions
     * @param  array<string, int|float|null>  $limits
     * @param  array<string, string>  $overageRules
     * @return array<string, mixed>
     */
    private function planRow(
        string $code,
        string $name,
        string $description,
        string $tier,
        string $workspaceType,
        string $currency,
        float|int $price,
        int $sortOrder,
        bool $isPublic,
        int $trialDays,
        array $features,
        array $permissions,
        array $limits,
        array $overageRules,
    ): array {
        return [
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'tier' => $tier,
            'workspace_type' => $workspaceType,
            'billing_period' => 'monthly',
            'trial_days' => $trialDays,
            'currency' => $currency,
            'price' => $price,
            'is_active' => true,
            'is_public' => $isPublic,
            'sort_order' => $sortOrder,
            'features' => array_values($features),
            'permissions' => array_values($permissions),
            'limits' => $limits,
            'overage_rules' => $overageRules,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function overageRulesFromMeters(): array
    {
        $rules = [];
        foreach (config('plans.meters', []) as $meter => $meta) {
            $rules[$meter] = (string) ($meta['overage'] ?? 'hard_block');
        }

        return $rules;
    }

    /**
     * @return array<int, string>
     */
    private function permissionsForTier(string $tier): array
    {
        $starter = [
            'workspace.view',
            'customers.manage',
            'conversations.manage',
            'ai.manage',
            'subscriptions.manage',
            'payments.manage',
            'appointments.view',
            'appointments.manage',
            'appointments.requests.view',
            'appointments.requests.manage',
            'appointments.calendar.view',
            'appointments.billing.manage',
            'appointments.settings.manage',
            'appointments.website.manage',
        ];

        $pro = array_values(array_unique(array_merge($starter, [
            'workspace.manage',
            'products.manage',
            'inventory.manage',
            'orders.manage',
            'pos.manage',
            'tables.manage',
            'menu.manage',
            'whatsapp.manage',
            'employees.manage',
            'finance.view',
            'finance.manage',
            'finance.sales.view',
            'finance.price_lists.view',
            'finance.price_lists.manage',
            'finance.adjustments.view',
            'finance.adjustments.manage',
            'finance.salary_advances.view',
            'finance.salary_advances.manage',
            'finance.fiscal_years.view',
            'finance.fiscal_years.manage',
            'invoices.view',
            'invoices.create',
            'invoices.edit',
            'invoices.cancel',
            'expenses.view',
            'expenses.create',
            'expenses.edit',
            'accounting.view',
            'accounting.manage',
            'payroll.view',
            'reports.view',
            'finance.settings',
            'contracts.view',
            'contracts.manage',
            'appointments.domains.manage',
        ])));

        return match ($tier) {
            'starter' => $starter,
            'pro', 'business', 'enterprise' => $pro,
            default => $starter,
        };
    }
}
