<?php

namespace App\Http\Controllers\Workspace\Finance;

use App\Models\Customer;
use App\Models\Finance\FinanceInvoice;
use App\Models\Finance\FinanceSupplier;
use App\Models\Finance\FinanceTaxRate;
use App\Models\Finance\FinanceTreasuryAccount;
use App\Models\Product;
use App\Services\Finance\FinanceBootstrapService;
use App\Services\Finance\InvoicePaymentService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PdfInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class InvoiceController extends FinanceBaseController
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoicePaymentService $invoicePaymentService,
        private readonly FinanceBootstrapService $financeBootstrapService,
        private readonly PdfInvoiceService $pdfInvoiceService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeFinance($request, 'invoices.view');
        $workspace = $this->currentWorkspace();
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($workspace);

        $invoiceStatus = $request->string('invoice_status')->toString();
        $paymentStatus = $request->string('payment_status')->toString();
        $legacyStatus = $request->string('status')->toString();
        $hasSplitStatusColumns = FinanceInvoice::hasSeparatedStatusColumns();

        if ($legacyStatus !== '' && $invoiceStatus === '' && $paymentStatus === '') {
            if (in_array($legacyStatus, ['draft', 'cancelled'], true)) {
                $invoiceStatus = $legacyStatus;
            } elseif ($legacyStatus === 'sent') {
                $invoiceStatus = 'issued';
            } elseif (in_array($legacyStatus, ['unpaid', 'partial', 'paid', 'overdue'], true)) {
                $paymentStatus = $legacyStatus;
            }
        }

        $invoices = FinanceInvoice::query()
            ->with(['customer', 'supplier'])
            ->when($request->string('search')->toString(), function ($query, $search) use ($hasSplitStatusColumns): void {
                $query->where(function ($inner) use ($search, $hasSplitStatusColumns): void {
                    $inner->where('invoice_number', 'like', '%'.$search.'%')
                        ->orWhere('customer_name', 'like', '%'.$search.'%')
                        ->orWhere('status', 'like', '%'.$search.'%');

                    if ($hasSplitStatusColumns) {
                        $inner->orWhere('invoice_status', 'like', '%'.$search.'%')
                            ->orWhere('payment_status', 'like', '%'.$search.'%');
                    }
                });
            })
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->when($invoiceStatus !== '', fn ($query) => $query->whereInvoiceStatus($invoiceStatus))
            ->when($paymentStatus !== '', fn ($query) => $query->wherePaymentStatus($paymentStatus))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('workspace.finance.invoices.index', [
            'invoices' => $invoices,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeFinance($request, 'invoices.create');
        $workspace = $this->currentWorkspace();
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($workspace);

        return view('workspace.finance.invoices.create', [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone']),
            'suppliers' => FinanceSupplier::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'price', 'currency', 'sku']),
            'taxRates' => FinanceTaxRate::query()->where('is_active', true)->orderByDesc('is_default')->get(['id', 'name', 'type', 'rate', 'code']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeFinance($request, 'invoices.create');
        $workspace = $this->currentWorkspace();
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($workspace);

        $validated = $request->validate([
            'type' => ['required', 'in:sales,purchase'],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspace->id)
                ),
            ],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('finance_suppliers', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspace->id)
                ),
            ],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'invoice_status' => ['nullable', 'in:draft,issued'],
            'status' => ['nullable', 'in:draft,sent,unpaid,partial,paid,overdue,cancelled'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'tax_profile_type' => ['nullable', 'in:standard,zero_rated,exempt,out_of_scope'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items_json' => ['required', 'string'],
        ]);

        if (
            $validated['type'] === 'sales'
            && empty($validated['customer_id'])
            && trim((string) ($validated['customer_name'] ?? '')) === ''
        ) {
            return back()->withInput()->withErrors([
                'customer_name' => 'يرجى اختيار عميل مسجل أو إدخال اسم عميل نقدي.',
            ]);
        }

        if ($validated['type'] === 'purchase' && empty($validated['supplier_id'])) {
            return back()->withInput()->withErrors([
                'supplier_id' => 'يرجى اختيار المورد لفاتورة الشراء.',
            ]);
        }

        $items = json_decode($validated['items_json'], true);
        if (! is_array($items) || $items === []) {
            return back()->withInput()->withErrors(['items_json' => 'يجب إدخال عنصر واحد على الأقل في الفاتورة.']);
        }

        $payload = Arr::except($validated, ['items_json']);
        if (! isset($payload['invoice_status']) && isset($payload['status'])) {
            $payload['invoice_status'] = $payload['status'];
        }
        $payload['items'] = $items;

        try {
            $invoice = $this->invoiceService->create($workspace, $payload, (int) $request->user()?->id);
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('workspace.finance.invoices.show', $invoice)->with('success', 'تم إنشاء الفاتورة بنجاح.');
    }

    public function show(Request $request, FinanceInvoice $invoice): View
    {
        $this->authorizeFinance($request, 'invoices.view');
        $this->assertSameWorkspace($invoice->workspace_id);
        $invoice = $this->invoiceService->syncPaymentStatus($invoice);

        return view('workspace.finance.invoices.show', [
            'invoice' => $invoice->load([
                'customer',
                'supplier',
                'items.product',
                'payments.treasuryAccount',
            ]),
            'treasuryAccounts' => FinanceTreasuryAccount::query()->where('is_active', true)->orderBy('type')->get(),
        ]);
    }

    public function cancel(Request $request, FinanceInvoice $invoice): RedirectResponse
    {
        $this->authorizeFinance($request, 'invoices.cancel');
        $this->invoiceService->cancel($invoice);

        return redirect()->route('workspace.finance.invoices.show', $invoice)->with('success', 'تم إلغاء الفاتورة.');
    }

    public function storePayment(Request $request, FinanceInvoice $invoice): RedirectResponse
    {
        $this->authorizeFinance($request, 'payments.manage');
        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash,bank_transfer,card,other'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'treasury_account_id' => [
                'nullable',
                'integer',
                Rule::exists('finance_treasury_accounts', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $invoice->workspace_id)
                ),
            ],
        ]);

        try {
            $this->invoicePaymentService->recordPayment($invoice, $validated, (int) $request->user()?->id);
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('workspace.finance.invoices.show', $invoice)->with('success', 'تم تسجيل الدفعة وتحديث الفاتورة.');
    }

    public function downloadPdf(Request $request, FinanceInvoice $invoice)
    {
        $this->authorizeFinance($request, 'invoices.view');
        $invoice = $this->invoiceService->syncPaymentStatus($invoice);

        try {
            return $this->pdfInvoiceService->download($invoice);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}
