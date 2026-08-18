<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $stats = [
            'users' => User::query()->count(),
            'workspaces' => Workspace::query()->count(),
            'plans' => Plan::query()->count(),
            'subscriptions' => Subscription::withoutGlobalScopes()->count(),
            'orders' => Order::withoutGlobalScopes()->count(),
            'payments_paid' => Payment::withoutGlobalScopes()->where('status', 'paid')->count(),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'admin' => auth('platform_admin')->user(),
                    'stats' => $stats,
                ],
            ]);
        }

        return view('platform.dashboard', ['stats' => $stats]);
    }
}
