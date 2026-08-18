<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsureWorkspaceSelected;
use App\Filament\Workspace\Widgets\CommercialSalesTrendChart;
use App\Filament\Workspace\Widgets\ConversationsTrendChart;
use App\Filament\Workspace\Widgets\WorkspaceStatsOverview;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class WorkspacePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('workspace')
            ->path('workspace')
            ->profile()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->brandName('AI-HASO Workspace')
            ->discoverResources(in: app_path('Filament/Workspace/Resources'), for: 'App\Filament\Workspace\Resources')
            ->discoverPages(in: app_path('Filament/Workspace/Pages'), for: 'App\Filament\Workspace\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Workspace/Widgets'), for: 'App\Filament\Workspace\Widgets')
            ->widgets([
                WorkspaceStatsOverview::class,
                CommercialSalesTrendChart::class,
                ConversationsTrendChart::class,
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                EnsureWorkspaceSelected::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureEmailIsVerified::class,
            ]);
    }
}
