<?php

namespace App\Filament\Workspace\Widgets;

use App\Models\Conversation;
use App\Support\Tenancy\WorkspaceContext;
use Filament\Widgets\ChartWidget;

class ConversationsTrendChart extends ChartWidget
{
    protected ?string $heading = 'المحادثات (آخر 7 أيام)';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return app(WorkspaceContext::class)->workspace() !== null;
    }

    protected function getData(): array
    {
        $dailyConversations = Conversation::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->whereDate('created_at', '>=', now()->subDays(6)->toDateString())
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $data = [];

        for ($day = 6; $day >= 0; $day--) {
            $date = now()->subDays($day);
            $labels[] = $date->format('d/m');
            $data[] = (int) ($dailyConversations[$date->toDateString()] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'عدد المحادثات',
                    'data' => $data,
                    'backgroundColor' => '#38bdf8',
                    'borderRadius' => 8,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
