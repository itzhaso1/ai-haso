<?php

namespace App\Http\Controllers\Api\Cashier\V1;

use App\Http\Controllers\Api\Cashier\CashierController;
use App\Http\Controllers\Api\Cashier\Concerns\AuthorizesCashier;
use App\Http\Controllers\Api\Cashier\Concerns\ResolvesCashierWorkspace;
use App\Services\Pos\PosSyncPullService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends CashierController
{
    use AuthorizesCashier;
    use ResolvesCashierWorkspace;

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly PosSyncPullService $pull,
    ) {}

    public function changes(Request $request): JsonResponse
    {
        $workspace = $this->requireWorkspace($this->workspaceContext);
        $this->authorizeCashier($request, $workspace);

        $since = (int) $request->query('since', 0);
        $limit = (int) $request->query('limit', 200);
        if ($since < 0) {
            return $this->fail('قيمة since غير صالحة.', 422);
        }

        // Workspace comes from auth middleware / WorkspaceContext — never trust a client workspace body.
        $payload = $this->pull->changes($workspace, $since, $limit);

        return $this->ok($payload);
    }
}
