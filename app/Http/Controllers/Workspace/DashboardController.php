<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Models\AiLog;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Subscription;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use InteractsWithWorkspace;

    public function index(): View
    {
        $workspace = $this->currentWorkspace();
        $isCommercial = in_array($workspace->type, ['company', 'store'], true);

        $stats = [
            'conversations' => Conversation::query()->count(),
            'subscription_status' => Subscription::query()->latest('id')->value('status') ?? 'inactive',
            'ai_tokens_30d' => (int) AiLog::query()
                ->whereDate('created_at', '>=', now()->subDays(30)->toDateString())
                ->sum('tokens_used'),
        ];

        if ($isCommercial) {
            $stats = array_merge($stats, [
                'orders_today' => Order::query()->whereDate('created_at', today())->count(),
                'paid_orders' => Order::query()->where('payment_status', 'paid')->count(),
                'sales_total' => (float) Order::query()
                    ->whereIn('status', ['confirmed', 'completed'])
                    ->sum('total_amount'),
                'customers' => Customer::query()->count(),
                'products' => Product::query()->count(),
                'paid_payments' => Payment::query()->where('status', 'paid')->count(),
            ]);
        }

        return view('workspace.dashboard', [
            'workspace' => $workspace,
            'isCommercial' => $isCommercial,
            'stats' => $stats,
        ]);
    }
}
