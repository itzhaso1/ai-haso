<?php

namespace App\Filament\Platform\Widgets;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Users', (string) User::query()->count()),
            Stat::make('Workspaces', (string) Workspace::query()->count()),
            Stat::make('Orders', (string) Order::withoutGlobalScopes()->count()),
            Stat::make('Paid Payments', (string) Payment::withoutGlobalScopes()->where('status', 'paid')->count()),
        ];
    }
}
