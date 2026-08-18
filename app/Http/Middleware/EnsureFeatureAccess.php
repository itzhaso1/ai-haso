<?php

namespace App\Http\Middleware;

use App\Services\Feature\FeatureAccessService;
use App\Support\Tenancy\WorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureAccess
{
    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly FeatureAccessService $featureAccessService,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        $workspace = $this->workspaceContext->workspace();

        if (! $user || ! $workspace) {
            abort(Response::HTTP_FORBIDDEN, 'Workspace context is required.');
        }

        if (! $this->featureAccessService->hasFeature($user, $workspace, $feature)) {
            abort(Response::HTTP_FORBIDDEN, 'Feature is not available in this workspace.');
        }

        return $next($request);
    }
}
