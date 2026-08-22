<?php

namespace App\Http\Controllers\Workspace\Finance;

use App\Models\Customer;
use App\Models\Finance\FinanceEmployeeProfile;
use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceInvoice;
use App\Models\Finance\FinanceInvoiceItem;
use App\Models\Finance\FinancePayrollRun;
use App\Models\Finance\FinanceTaxRate;
use App\Models\Finance\FinanceTreasuryAccount;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\WorkspaceUser;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModulePageController extends FinanceBaseController
{
    public function customers(Request $request): View
    {
        $this->authorizeFinance($request, 'finance.view');

        $customers = Customer::query()
            ->withCount(['orders', 'conversations'])
            ->withSum('orders as orders_total_amount', 'total_amount')
            ->latest('id')
            ->paginate(15);

        $invoiceMap = FinanceInvoice::query()
            ->where('type', 'sales')
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as invoices_count, COALESCE(SUM(amount_due),0) as due_total')
            ->pluck('invoices_count', 'customer_id')
            ->all();

        return view('workspace.finance.modules.customers', [
            'customers' => $customers,
            'invoiceCountByCustomer' => $invoiceMap,
        ]);
    }

    public function products(Request $request): View
    {
        $this->authorizeFinance($request, 'finance.view');

        $products = Product::query()
            ->with('category')
            ->withCount('variants')
            ->latest('id')
            ->paginate(20);

        $salesByProduct = FinanceInvoiceItem::query()
            ->selectRaw('product_id, COALESCE(SUM(quantity),0) as sold_qty, COALESCE(SUM(total),0) as sold_total')
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->pluck('sold_total', 'product_id')
            ->all();

        return view('workspace.finance.modules.products', [
            'products' => $products,
            'salesByProduct' => $salesByProduct,
        ]);
    }

    public function inventory(Request $request): View
    {
        $this->authorizeFinance($request, 'finance.view');

        $movements = InventoryMovement::query()
            ->with(['product', 'variant', 'user'])
            ->latest('id')
            ->paginate(20);

        return view('workspace.finance.modules.inventory', [
            'movements' => $movements,
        ]);
    }

    public function payroll(Request $request): View
    {
        $this->authorizeFinance($request, 'payroll.view');

        $workspace = $this->currentWorkspace();

        $profiles = FinanceEmployeeProfile::query()
            ->with('user')
            ->latest('id')
            ->paginate(12);

        $runs = FinancePayrollRun::query()->latest('period_month')->limit(12)->get();

        $employees = WorkspaceUser::query()
            ->where('workspace_id', $workspace->id)
            ->with('user')
            ->where('status', 'active')
            ->orderBy('membership_role')
            ->get();

        return view('workspace.finance.modules.payroll', [
            'profiles' => $profiles,
            'runs' => $runs,
            'employees' => $employees,
        ]);
    }

    public function vat(Request $request): View
    {
        $this->authorizeFinance($request, 'accounting.view');

        $rates = FinanceTaxRate::query()->orderByDesc('is_default')->get();
        $output = (float) FinanceInvoice::query()
            ->where('type', 'sales')
            ->sum('tax_amount');
        $inputFromPurchases = (float) FinanceInvoice::query()
            ->where('type', 'purchase')
            ->sum('tax_amount');
        $inputFromExpenses = (float) FinanceExpense::query()->sum('tax_amount');
        $input = $inputFromPurchases + $inputFromExpenses;

        return view('workspace.finance.modules.vat', [
            'rates' => $rates,
            'vat' => [
                'output' => round($output, 2),
                'input' => round($input, 2),
                'net' => round($output - $input, 2),
            ],
        ]);
    }

    public function banks(Request $request): View
    {
        $this->authorizeFinance($request, 'finance.view');

        $treasuryAccounts = FinanceTreasuryAccount::query()
            ->with('linkedAccount')
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(20);

        return view('workspace.finance.modules.banks', [
            'treasuryAccounts' => $treasuryAccounts,
        ]);
    }

    public function placeholder(Request $request, string $key): View
    {
        $this->authorizeFinance($request, 'finance.view');

        $titles = [
            'sales' => 'المبيعات',
            'purchases' => 'المشتريات',
            'cashbox' => 'الصندوق',
            'settings-accounting' => 'إعدادات المحاسبة',
        ];

        return view('workspace.finance.modules.placeholder', [
            'title' => $titles[$key] ?? 'وحدة مالية',
            'moduleKey' => $key,
        ]);
    }

    public function sales(Request $request): View
    {
        return $this->placeholder($request, 'sales');
    }

    public function purchases(Request $request): View
    {
        return $this->placeholder($request, 'purchases');
    }

    public function cashbox(Request $request): View
    {
        return $this->placeholder($request, 'cashbox');
    }
}
