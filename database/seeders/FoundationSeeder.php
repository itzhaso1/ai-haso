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
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (['owner', 'admin', 'manager', 'agent'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

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
                'features' => ['dashboard', 'products', 'categories', 'inventory', 'customers', 'orders', 'conversations', 'messages', 'smart_replies', 'ai', 'subscription', 'whatsapp'],
                'permissions' => ['workspace.view', 'products.manage', 'inventory.manage', 'customers.manage', 'orders.manage', 'conversations.manage', 'ai.manage', 'whatsapp.manage', 'subscriptions.manage'],
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
                'features' => ['dashboard', 'products', 'categories', 'inventory', 'customers', 'orders', 'conversations', 'messages', 'smart_replies', 'ai', 'subscription', 'whatsapp'],
                'permissions' => ['workspace.view', 'products.manage', 'inventory.manage', 'customers.manage', 'orders.manage', 'conversations.manage', 'ai.manage', 'whatsapp.manage', 'subscriptions.manage'],
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
                'features' => ['dashboard', 'products', 'categories', 'inventory', 'customers', 'orders', 'conversations', 'messages', 'smart_replies', 'ai', 'subscription', 'whatsapp', 'payments', 'employees', 'analytics'],
                'permissions' => ['workspace.view', 'workspace.manage', 'products.manage', 'inventory.manage', 'customers.manage', 'orders.manage', 'conversations.manage', 'ai.manage', 'whatsapp.manage', 'payments.manage', 'employees.manage', 'subscriptions.manage'],
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
                'features' => ['dashboard', 'products', 'categories', 'inventory', 'customers', 'orders', 'conversations', 'messages', 'smart_replies', 'ai', 'subscription', 'whatsapp', 'payments', 'employees', 'analytics'],
                'permissions' => ['workspace.view', 'workspace.manage', 'products.manage', 'inventory.manage', 'customers.manage', 'orders.manage', 'conversations.manage', 'ai.manage', 'whatsapp.manage', 'payments.manage', 'employees.manage', 'subscriptions.manage'],
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
                'features' => ['dashboard', 'products', 'categories', 'inventory', 'customers', 'orders', 'conversations', 'messages', 'smart_replies', 'ai', 'subscription', 'whatsapp', 'payments', 'employees', 'analytics'],
                'permissions' => ['workspace.view', 'workspace.manage', 'products.manage', 'inventory.manage', 'customers.manage', 'orders.manage', 'conversations.manage', 'ai.manage', 'whatsapp.manage', 'payments.manage', 'employees.manage', 'subscriptions.manage'],
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
                'features' => ['dashboard', 'products', 'categories', 'inventory', 'customers', 'orders', 'conversations', 'messages', 'smart_replies', 'ai', 'subscription', 'whatsapp', 'payments', 'employees', 'analytics'],
                'permissions' => ['workspace.view', 'workspace.manage', 'products.manage', 'inventory.manage', 'customers.manage', 'orders.manage', 'conversations.manage', 'ai.manage', 'whatsapp.manage', 'payments.manage', 'employees.manage', 'subscriptions.manage'],
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
