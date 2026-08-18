<?php

namespace App\Filament\Workspace\Widgets;

use App\Models\Order;
use App\Support\Tenancy\WorkspaceContext;
use Filament\Widgets\ChartWidget;

class CommercialSalesTrendChart extends ChartWidget
{
    protected static ?string $heading = 'اتجاه المبيعات (آخر 7 أيام)';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        $workspace = app(WorkspaceContext::class)->workspace();

        return $workspace !== null && in_array($workspace->type, ['company', 'store'], true);
    }

    protected function getData(): array
    {
        $dailySales = Order::query()
            ->selectRaw('DATE(created_at) as day, COALESCE(SUM(total_amount), 0) as total')
            ->whereDate('created_at', '>=', now()->subDays(6)->toDateString())
            ->whereIn('status', ['confirmed', 'completed'])
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $data = [];

        for ($day = 6; $day >= 0; $day--) {
            $date = now()->subDays($day);
            $labels[] = $date->format('d/m');
            $data[] = (float) ($dailySales[$date->toDateString()] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'المبيعات',
                    'data' => $data,
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.08)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
