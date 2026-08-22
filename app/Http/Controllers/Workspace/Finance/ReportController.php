<?php

namespace App\Http\Controllers\Workspace\Finance;

use App\Services\Finance\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends FinanceBaseController
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeFinance($request, 'reports.view');

        $from = $request->date('from', now()->startOfMonth())->toDateString();
        $to = $request->date('to', now()->endOfMonth())->toDateString();
        $summary = $this->reportService->summary($from, $to);

        return view('workspace.finance.reports.index', [
            'from' => $from,
            'to' => $to,
            ...$summary,
        ]);
    }
}
