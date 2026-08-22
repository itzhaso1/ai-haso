<?php

namespace App\Http\Controllers\Workspace\Finance;

use App\Services\Finance\DashboardService;
use App\Services\Finance\FinanceBootstrapService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends FinanceBaseController
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly FinanceBootstrapService $financeBootstrapService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeFinance($request, 'finance.view');
        $workspace = $this->currentWorkspace();
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($workspace);

        $data = $this->dashboardService->metrics();

        return view('workspace.finance.dashboard', [
            'workspace' => $workspace,
            ...$data,
        ]);
    }
}
