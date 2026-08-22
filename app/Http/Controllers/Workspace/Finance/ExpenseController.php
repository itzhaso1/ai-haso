<?php

namespace App\Http\Controllers\Workspace\Finance;

use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceExpenseCategory;
use App\Models\Finance\FinanceSupplier;
use App\Models\Finance\FinanceTaxRate;
use App\Models\Finance\FinanceTreasuryAccount;
use App\Services\Finance\ExpenseService;
use App\Services\Finance\FinanceBootstrapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends FinanceBaseController
{
    public function __construct(
        private readonly ExpenseService $expenseService,
        private readonly FinanceBootstrapService $financeBootstrapService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeFinance($request, 'expenses.view');
        $workspace = $this->currentWorkspace();
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($workspace);

        $expenses = FinanceExpense::query()
            ->with(['supplier', 'category', 'treasuryAccount'])
            ->when($request->string('search')->toString(), function ($query, $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('expense_number', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('workspace.finance.expenses.index', [
            'expenses' => $expenses,
            'suppliers' => FinanceSupplier::query()->orderBy('name')->get(['id', 'name']),
            'categories' => FinanceExpenseCategory::query()->orderBy('name')->get(['id', 'name']),
            'treasuryAccounts' => FinanceTreasuryAccount::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']),
            'taxRates' => FinanceTaxRate::query()->where('is_active', true)->orderByDesc('is_default')->get(['id', 'name', 'type', 'rate']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeFinance($request, 'expenses.create');
        $workspace = $this->currentWorkspace();

        $payload = $request->validate([
            'supplier_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'treasury_account_id' => ['nullable', 'integer'],
            'expense_number' => ['nullable', 'string', 'max:255'],
            'expense_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'tax_profile_type' => ['nullable', 'in:standard,zero_rated,exempt,out_of_scope'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_method' => ['nullable', 'in:cash,bank_transfer,card,other,credit'],
            'status' => ['nullable', 'in:draft,approved,paid,cancelled'],
            'is_recurring' => ['nullable', 'boolean'],
            'recurring_frequency' => ['nullable', 'in:weekly,monthly,quarterly,yearly'],
            'next_due_date' => ['nullable', 'date'],
            'attachment_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $this->expenseService->create($workspace, $payload, (int) $request->user()?->id);

        return redirect()->route('workspace.finance.expenses.index')->with('success', 'تم إنشاء المصروف وربطه محاسبيًا.');
    }
}
