<?php

namespace App\Services\Finance;

use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceInvoice;
use App\Models\Finance\FinanceInvoicePayment;
use App\Models\Finance\FinanceTaxRate;
use App\Models\Finance\FinanceTreasuryAccount;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function metrics(): array
    {
        $salesInvoices = FinanceInvoice::query()->where('type', 'sales');
        $purchaseInvoices = FinanceInvoice::query()->where('type', 'purchase');

        $totalSales = (float) (clone $salesInvoices)->sum('total');
        $totalPurchases = (float) (clone $purchaseInvoices)->sum('total');
        $totalExpenses = (float) FinanceExpense::query()->sum('total');
        $outputVat = (float) (clone $salesInvoices)->sum('tax_amount');
        $inputVat = (float) (
            (clone $purchaseInvoices)->sum('tax_amount')
            + FinanceExpense::query()->sum('tax_amount')
        );

        $receivables = (float) (clone $salesInvoices)->sum('amount_due');
        $payables = (float) (clone $purchaseInvoices)->sum('amount_due');
        $unpaidInvoices = (int) (clone $salesInvoices)->whereIn('status', ['unpaid', 'partial', 'overdue'])->count();
        $overdueInvoices = (int) (clone $salesInvoices)->where('status', 'overdue')->count();

        $cashBalance = (float) FinanceTreasuryAccount::query()->where('type', 'cash')->sum('current_balance');
        $bankBalance = (float) FinanceTreasuryAccount::query()->where('type', 'bank')->sum('current_balance');

        $salesTaxable = (float) (clone $salesInvoices)->sum('taxable_amount');
        $purchaseTaxable = (float) (clone $purchaseInvoices)->sum('taxable_amount');
        $profit = $salesTaxable - $purchaseTaxable - $totalExpenses;

        return [
            'cards' => [
                'sales_total' => round($totalSales, 2),
                'purchases_total' => round($totalPurchases, 2),
                'expenses_total' => round($totalExpenses, 2),
                'net_profit' => round($profit, 2),
                'receivables_total' => round($receivables, 2),
                'payables_total' => round($payables, 2),
                'unpaid_invoices' => $unpaidInvoices,
                'overdue_invoices' => $overdueInvoices,
                'output_vat' => round($outputVat, 2),
                'input_vat' => round($inputVat, 2),
                'net_vat' => round($outputVat - $inputVat, 2),
                'cash_balance' => round($cashBalance, 2),
                'bank_balance' => round($bankBalance, 2),
            ],
            'charts' => [
                'sales' => $this->monthlySeries('finance_invoices', 'total', [
                    ['type', '=', 'sales'],
                ]),
                'expenses' => $this->monthlySeries('finance_expenses', 'total', []),
                'profit' => $this->profitSeries(),
                'vat' => $this->vatSeries(),
                'cash_flow' => $this->cashFlowSeries(),
            ],
            'latest' => [
                'invoices' => FinanceInvoice::query()->with(['customer', 'supplier'])->latest('id')->limit(10)->get(),
                'payments' => FinanceInvoicePayment::query()->with('invoice')->latest('id')->limit(10)->get(),
                'expenses' => FinanceExpense::query()->with(['supplier', 'category'])->latest('id')->limit(10)->get(),
                'overdue_invoices' => FinanceInvoice::query()
                    ->with('customer')
                    ->where('type', 'sales')
                    ->where('status', 'overdue')
                    ->latest('due_date')
                    ->limit(10)
                    ->get(),
            ],
            'tax_rates' => FinanceTaxRate::query()->where('is_active', true)->orderByDesc('is_default')->get(),
        ];
    }

    /**
     * @param  array<int, array{0:string,1:string,2:string}>  $filters
     * @return array<int, array{month:string,value:float}>
     */
    private function monthlySeries(string $table, string $sumColumn, array $filters): array
    {
        $workspaceId = $this->workspaceContext->workspaceId();
        if (! $workspaceId) {
            return [];
        }

        $query = DB::table($table)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(".$sumColumn.") as value")
            ->where('workspace_id', $workspaceId)
            ->whereDate('created_at', '>=', now()->subMonths(11)->startOfMonth()->toDateString());

        foreach ($filters as $filter) {
            $query->where($filter[0], $filter[1], $filter[2]);
        }

        $rows = $query
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $this->filledMonths($rows);
    }

    /**
     * @return array<int, array{month:string,value:float}>
     */
    private function profitSeries(): array
    {
        $sales = collect($this->monthlySeries('finance_invoices', 'taxable_amount', [['type', '=', 'sales']]))
            ->keyBy('month');
        $purchases = collect($this->monthlySeries('finance_invoices', 'taxable_amount', [['type', '=', 'purchase']]))
            ->keyBy('month');
        $expenses = collect($this->monthlySeries('finance_expenses', 'total', []))
            ->keyBy('month');

        $months = $this->last12Months();

        return collect($months)->map(function (string $month) use ($sales, $purchases, $expenses): array {
            $salesValue = (float) ($sales[$month]['value'] ?? 0);
            $purchaseValue = (float) ($purchases[$month]['value'] ?? 0);
            $expenseValue = (float) ($expenses[$month]['value'] ?? 0);

            return [
                'month' => $month,
                'value' => round($salesValue - $purchaseValue - $expenseValue, 2),
            ];
        })->all();
    }

    /**
     * @return array<int, array{month:string,value:float}>
     */
    private function vatSeries(): array
    {
        $output = collect($this->monthlySeries('finance_invoices', 'tax_amount', [['type', '=', 'sales']]))
            ->keyBy('month');
        $inputFromPurchase = collect($this->monthlySeries('finance_invoices', 'tax_amount', [['type', '=', 'purchase']]))
            ->keyBy('month');
        $inputFromExpense = collect($this->monthlySeries('finance_expenses', 'tax_amount', []))
            ->keyBy('month');
        $months = $this->last12Months();

        return collect($months)->map(function (string $month) use ($output, $inputFromPurchase, $inputFromExpense): array {
            $outputValue = (float) ($output[$month]['value'] ?? 0);
            $inputValue = (float) ($inputFromPurchase[$month]['value'] ?? 0) + (float) ($inputFromExpense[$month]['value'] ?? 0);

            return [
                'month' => $month,
                'value' => round($outputValue - $inputValue, 2),
            ];
        })->all();
    }

    /**
     * @return array<int, array{month:string,value:float}>
     */
    private function cashFlowSeries(): array
    {
        $inflow = collect($this->monthlySeries('finance_invoice_payments', 'amount', []))->keyBy('month');
        $outflow = collect($this->monthlySeries('finance_expenses', 'total', []))->keyBy('month');
        $months = $this->last12Months();

        return collect($months)->map(function (string $month) use ($inflow, $outflow): array {
            $inflowValue = (float) ($inflow[$month]['value'] ?? 0);
            $outflowValue = (float) ($outflow[$month]['value'] ?? 0);

            return [
                'month' => $month,
                'value' => round($inflowValue - $outflowValue, 2),
            ];
        })->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, array{month:string,value:float}>
     */
    private function filledMonths(Collection $rows): array
    {
        $map = $rows->mapWithKeys(function ($row): array {
            return [(string) $row->month => round((float) $row->value, 2)];
        });

        return collect($this->last12Months())->map(function (string $month) use ($map): array {
            return [
                'month' => $month,
                'value' => round((float) ($map[$month] ?? 0), 2),
            ];
        })->all();
    }

    /**
     * @return array<int, string>
     */
    private function last12Months(): array
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = now()->subMonths($i)->format('Y-m');
        }

        return $months;
    }
}
