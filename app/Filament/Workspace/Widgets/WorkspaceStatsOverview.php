<?php

namespace App\Filament\Workspace\Widgets;

use App\Models\AiLog;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\Subscription;
use App\Support\Tenancy\WorkspaceContext;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WorkspaceStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $workspace = app(WorkspaceContext::class)->workspace();
        if (! $workspace) {
            return [];
        }

        $stats = [
            Stat::make('المحادثات', (string) Conversation::query()->count())
                ->description('كل القنوات')
                ->color('primary'),
            Stat::make('حالة الاشتراك', Subscription::query()->latest('id')->first()?->status ?? 'غير مشترك')
                ->color('info'),
        ];

        if (in_array($workspace->type, ['company', 'store'], true)) {
            $stats[] = Stat::make('طلبات اليوم', (string) Order::query()->whereDate('created_at', today())->count())
                ->description('إنشاءات جديدة')
                ->color('primary');
            $stats[] = Stat::make('طلبات مدفوعة', (string) Order::query()->where('payment_status', 'paid')->count())
                ->description('تأكيد عبر webhook')
                ->color('success');
            $stats[] = Stat::make('المنتجات', (string) Product::query()->count())
                ->color('warning');
            $stats[] = Stat::make('العملاء', (string) Customer::query()->count())
                ->color('info');
            $stats[] = Stat::make('المدفوعات الناجحة', (string) Payment::query()->where('status', 'paid')->count())
                ->color('success');
            $stats[] = Stat::make('بوابة الدفع', PaymentGateway::query()->where('status', 'connected')->exists() ? 'متصلة' : 'غير متصلة')
                ->color(PaymentGateway::query()->where('status', 'connected')->exists() ? 'success' : 'gray');
        } else {
            $stats[] = Stat::make(
                'استهلاك AI (30 يوم)',
                (string) AiLog::query()
                    ->whereDate('created_at', '>=', now()->subDays(30)->toDateString())
                    ->sum('tokens_used')
            )->color('primary');
        }

        return $stats;
    }
}
