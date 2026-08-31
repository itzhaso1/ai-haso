<?php

use App\Http\Middleware\EnsureFeatureAccess;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureWorkspaceSelected;
use App\Http\Middleware\EnsureWorkspaceMembership;
use App\Http\Middleware\ResolvePublicWebsite;
use App\Http\Middleware\ResolveWorkspaceContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')
                ->prefix('platform')
                ->group(base_path('routes/platform.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'webhooks/resend',
            'whatsapp-webhook',
        ]);

        $middleware->alias([
            'workspace.resolve' => ResolveWorkspaceContext::class,
            'workspace.member' => EnsureWorkspaceMembership::class,
            'workspace.selected' => EnsureWorkspaceSelected::class,
            'workspace.feature' => EnsureFeatureAccess::class,
            'public.website.resolve' => ResolvePublicWebsite::class,
            'platform.admin' => EnsurePlatformAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
