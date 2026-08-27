<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlatformAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FoundationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'workspace.view',
            'workspace.manage',
            'products.manage',
            'inventory.manage',
            'customers.manage',
            'orders.manage',
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
            'appointments.view',
            'appointments.manage',
            'appointments.requests.view',
            'appointments.requests.manage',
            'appointments.calendar.view',
            'appointments.billing.manage',
            'appointments.settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (['owner', 'admin', 'manager', 'agent', 'receptionist', 'staff_doctor', 'accountant'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
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
            'appointments.view',
            'appointments.manage',
            'appointments.requests.view',
            'appointments.requests.manage',
            'appointments.calendar.view',
            'appointments.billing.manage',
            'appointments.settings.manage',
        ]);
        $agentRole->syncPermissions([
            'workspace.view',
            'customers.manage',
            'orders.manage',
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
            'appointments.view',
            'appointments.requests.view',
            'appointments.calendar.view',
        ]);
        $receptionistRole->syncPermissions([
            'workspace.view',
            'customers.manage',
            'conversations.manage',
            'appointments.view',
            'appointments.manage',
            'appointments.requests.view',
            'appointments.requests.manage',
            'appointments.calendar.view',
            'appointments.billing.manage',
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
            'appointments.view',
            'appointments.billing.manage',
        ]);

        $plans = [
            [
                'code' => 'individual_free',
                'name' => 'Individual Free',
                'workspace_type' => 'individual',
                'billing_period' => 'monthly',
                'currency' => 'USD',
                'price' => 0,
                'is_active' => true,
                'features' => ['conversations', 'smart_replies', 'ai', 'subscription', 'usage'],
                'permissions' => ['workspace.view', 'conversations.manage', 'ai.manage', 'subscriptions.manage'],
                'limits' => ['ai_usage' => 500, 'conversations' => 300, 'whatsapp_numbers' => 0],
            ],
            [
                'code' => 'individual_pro',
                'name' => 'Individual Pro',
                'workspace_type' => 'individual',
                'billing_period' => 'monthly',
                'currency' => 'USD',
                'price' => 19,
                'is_active' => true,
                'features' => ['conversations', 'smart_replies', 'ai', 'subscription', 'usage', 'whatsapp'],
                'permissions' => ['workspace.view', 'conversations.manage', 'ai.manage', 'whatsapp.manage', 'subscriptions.manage'],
                'limits' => ['ai_usage' => 5000, 'conversations' => 5000, 'whatsapp_numbers' => 1],
            ],
            [
                'code' => 'company_basic',
                'name' => 'Company Basic',
                'workspace_type' => 'company',
                'billing_period' => 'monthly',
                'currency' => 'USD',
                'price' => 99,
                'is_active' => true,
                'features' => ['dashboard', 'products', 'categories', 'inventory', 'customers', 'orders', 'conversations', 'messages', 'smart_replies', 'ai', 'subscription', 'whatsapp', 'finance', 'appointments'],
                'permissions' => ['workspace.view', 'products.manage', 'inventory.manage', 'customers.manage', 'orders.manage', 'conversations.manage', 'ai.manage', 'whatsapp.manage', 'subscriptions.manage', 'finance.view', 'invoices.view', 'expenses.view', 'accounting.view', 'reports.view', 'appointments.view'],
                'limits' => ['products' => 1000, 'users' => 10, 'orders' => 2000, 'ai_usage' => 20000],
            ],
            [
                'code' => 'store_basic',
                'name' => 'Store Basic',
                'workspace_type' => 'store',
                'billing_period' => 'monthly',
                'currency' => 'USD',
                'price' => 99,
                'is_active' => true,
                'features' => ['dashboard', 'products', 'categories', 'inventory', 'customers', 'orders', 'conversations', 'messages', 'smart_replies', 'ai', 'subscription', 'whatsapp', 'finance', 'appointments'],
                'permissions' => ['workspace.view', 'products.manage', 'inventory.manage', 'customers.manage', 'orders.manage', 'conversations.manage', 'ai.manage', 'whatsapp.manage', 'subscriptions.manage', 'finance.view', 'invoices.view', 'expenses.view', 'accounting.view', 'reports.view', 'appointments.view'],
                'limits' => ['products' => 1000, 'users' => 10, 'orders' => 2000, 'ai_usage' => 20000],
            ],
            [
                'code' => 'company_pro',
                'name' => 'Company Pro',
                'workspace_type' => 'company',
                'billing_period' => 'monthly',
                'currency' => 'USD',
                'price' => 199,
                'is_active' => true,
                'features' => ['dashboard', 'products', 'categories', 'inventory', 'customers', 'orders', 'conversations', 'messages', 'smart_replies', 'ai', 'subscription', 'whatsapp', 'payments', 'employees', 'analytics', 'finance', 'appointments'],
                'permissions' => ['workspace.view', 'workspace.manage', 'products.manage', 'inventory.manage', 'customers.manage', 'orders.manage', 'conversations.manage', 'ai.manage', 'whatsapp.manage', 'payments.manage', 'employees.manage', 'subscriptions.manage', 'finance.view', 'finance.manage', 'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.cancel', 'expenses.view', 'expenses.create', 'expenses.edit', 'accounting.view', 'accounting.manage', 'payroll.view', 'reports.view', 'finance.settings', 'appointments.view', 'appointments.manage'],
                'limits' => ['products' => 5000, 'users' => 30, 'orders' => 15000, 'ai_usage' => 120000],
            ],
            [
                'code' => 'store_pro',
                'name' => 'Store Pro',
                'workspace_type' => 'store',
                'billing_period' => 'monthly',
                'currency' => 'USD',
                'price' => 199,
                'is_active' => true,
                'features' => ['dashboard', 'products', 'categories', 'inventory', 'customers', 'orders', 'conversations', 'messages', 'smart_replies', 'ai', 'subscription', 'whatsapp', 'payments', 'employees', 'analytics', 'finance', 'appointments'],
                'permissions' => ['workspace.view', 'workspace.manage', 'products.manage', 'inventory.manage', 'customers.manage', 'orders.manage', 'conversations.manage', 'ai.manage', 'whatsapp.manage', 'payments.manage', 'employees.manage', 'subscriptions.manage', 'finance.view', 'finance.manage', 'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.cancel', 'expenses.view', 'expenses.create', 'expenses.edit', 'accounting.view', 'accounting.manage', 'payroll.view', 'reports.view', 'finance.settings', 'appointments.view', 'appointments.manage'],
                'limits' => ['products' => 5000, 'users' => 30, 'orders' => 15000, 'ai_usage' => 120000],
            ],
            [
                'code' => 'company_enterprise',
                'name' => 'Company Enterprise',
                'workspace_type' => 'company',
                'billing_period' => 'monthly',
                'currency' => 'USD',
                'price' => 499,
                'is_active' => true,
                'features' => ['dashboard', 'products', 'categories', 'inventory', 'customers', 'orders', 'conversations', 'messages', 'smart_replies', 'ai', 'subscription', 'whatsapp', 'payments', 'employees', 'analytics', 'finance', 'appointments'],
                'permissions' => ['workspace.view', 'workspace.manage', 'products.manage', 'inventory.manage', 'customers.manage', 'orders.manage', 'conversations.manage', 'ai.manage', 'whatsapp.manage', 'payments.manage', 'employees.manage', 'subscriptions.manage', 'finance.view', 'finance.manage', 'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.cancel', 'expenses.view', 'expenses.create', 'expenses.edit', 'accounting.view', 'accounting.manage', 'payroll.view', 'reports.view', 'finance.settings', 'appointments.view', 'appointments.manage'],
                'limits' => ['products' => 50000, 'users' => 200, 'orders' => 200000, 'ai_usage' => 1000000],
            ],
            [
                'code' => 'store_enterprise',
                'name' => 'Store Enterprise',
                'workspace_type' => 'store',
                'billing_period' => 'monthly',
                'currency' => 'USD',
                'price' => 499,
                'is_active' => true,
                'features' => ['dashboard', 'products', 'categories', 'inventory', 'customers', 'orders', 'conversations', 'messages', 'smart_replies', 'ai', 'subscription', 'whatsapp', 'payments', 'employees', 'analytics', 'finance', 'appointments'],
                'permissions' => ['workspace.view', 'workspace.manage', 'products.manage', 'inventory.manage', 'customers.manage', 'orders.manage', 'conversations.manage', 'ai.manage', 'whatsapp.manage', 'payments.manage', 'employees.manage', 'subscriptions.manage', 'finance.view', 'finance.manage', 'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.cancel', 'expenses.view', 'expenses.create', 'expenses.edit', 'accounting.view', 'accounting.manage', 'payroll.view', 'reports.view', 'finance.settings', 'appointments.view', 'appointments.manage'],
                'limits' => ['products' => 50000, 'users' => 200, 'orders' => 200000, 'ai_usage' => 1000000],
            ],
        ];

        foreach ($plans as $plan) {
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
    }
}
