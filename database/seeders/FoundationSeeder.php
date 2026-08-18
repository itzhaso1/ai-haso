<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

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
                'limits' => ['products' => 1000, 'users' => 10, 'orders' => 2000, 'ai_usage' => 20000],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
