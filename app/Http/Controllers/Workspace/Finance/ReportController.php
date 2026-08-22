<?php

namespace App\Http\Controllers\Workspace\Finance;

use App\Services\Finance\ReportService;
use Illuminate\Support\Carbon;
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

        $from = $this->resolveDate($request->input('from'), now()->startOfMonth())->toDateString();
        $to = $this->resolveDate($request->input('to'), now()->endOfMonth())->toDateString();
        $summary = $this->reportService->summary($from, $to);

        return view('workspace.finance.reports.index', [
            'from' => $from,
            'to' => $to,
            ...$summary,
        ]);
    }

    private function resolveDate(?string $value, Carbon $fallback): Carbon
    {
        if (! $value) {
            return $fallback;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
