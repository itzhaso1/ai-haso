<?php

namespace App\Services\Finance;

use App\Models\Customer;
use App\Models\Finance\FinanceInvoice;
use App\Models\Finance\FinanceInvoiceItem;
use App\Models\Finance\FinanceJournalEntry;
use App\Models\Finance\FinanceInvoicePayment;
use App\Models\Finance\FinanceSetting;
use App\Models\Finance\FinanceSupplier;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceService
{
    public function __construct(
        private readonly TaxService $taxService,
        private readonly ChartOfAccountsService $chartOfAccountsService,
        private readonly AccountingService $accountingService,
        private readonly InvoiceStateService $invoiceStateService,
        private readonly FinancialPeriodGuardService $financialPeriodGuardService,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     */
    public function create(Workspace $workspace, array $payload, int $actorUserId): FinanceInvoice
    {
        $this->chartOfAccountsService->ensureDefaultAccounts($workspace);
        $profile = $this->resolveTaxProfile($workspace, $payload);

        return DB::transaction(function () use ($workspace, $payload, $actorUserId, $profile): FinanceInvoice {
            $customerId = isset($payload['customer_id']) ? (int) $payload['customer_id'] : null;
            $customerName = trim((string) ($payload['customer_name'] ?? ''));
            $supplierId = isset($payload['supplier_id']) ? (int) $payload['supplier_id'] : null;
            $type = (string) $payload['type'];
            $requestedStatus = (string) ($payload['invoice_status'] ?? $payload['status'] ?? 'issued');

            if ($type === 'sales' && ! $customerId && $customerName === '') {
                throw new RuntimeException('فاتورة المبيعات تتطلب عميلًا مسجلًا أو اسم عميل نقدي.');
            }

            if ($type === 'purchase' && ! $supplierId) {
                throw new RuntimeException('فاتورة الشراء تتطلب اختيار مورد.');
            }

            if ($customerId) {
                $exists = Customer::withoutGlobalScopes()
                    ->where('workspace_id', $workspace->id)
                    ->whereKey($customerId)
                    ->exists();
                if (! $exists) {
                    throw new RuntimeException('العميل المحدد غير صالح ضمن مساحة العمل الحالية.');
                }
            }

            if ($supplierId) {
                $exists = FinanceSupplier::withoutGlobalScopes()
                    ->where('workspace_id', $workspace->id)
                    ->whereKey($supplierId)
                    ->exists();
                if (! $exists) {
                    throw new RuntimeException('المورد المحدد غير صالح ضمن مساحة العمل الحالية.');
                }
            }

            $customer = $customerId
                ? Customer::withoutGlobalScopes()
                    ->where('workspace_id', $workspace->id)
                    ->whereKey($customerId)
                    ->first()
                : null;
            $supplier = $supplierId
                ? FinanceSupplier::withoutGlobalScopes()
                    ->where('workspace_id', $workspace->id)
                    ->whereKey($supplierId)
                    ->first()
                : null;

            $items = $this->normalizeItems($payload['items'] ?? [], $workspace->id, $profile['type'], $profile['rate']);
            if ($items === []) {
                throw new RuntimeException('يجب أن تحتوي الفاتورة على بند واحد على الأقل.');
            }

            $totals = $this->totals($items);
            $amountPaid = max(0, (float) ($payload['amount_paid'] ?? 0));
            $amountDue = $this->invoiceStateService->resolveAmountDue($totals['total'], $amountPaid);
            $invoiceStatus = $this->invoiceStateService->resolveInvoiceStatus($requestedStatus);
            $paymentStatus = $this->invoiceStateService->resolvePaymentStatus(
                total: $totals['total'],
                amountPaid: $amountPaid,
                dueDate: ! empty($payload['due_date']) ? (string) $payload['due_date'] : null,
                invoiceStatus: $invoiceStatus,
            );
            $legacyStatus = $this->invoiceStateService->toLegacyStatus($invoiceStatus, $paymentStatus);
            $supportsSplitStatuses = FinanceInvoice::hasSeparatedStatusColumns();
            $supportsSnapshots = FinanceInvoice::hasSnapshotColumns();

            if ($invoiceStatus === 'issued') {
                $this->financialPeriodGuardService->assertDateIsOpen(
                    workspaceId: $workspace->id,
                    date: (string) $payload['issue_date'],
                    context: 'إصدار الفاتورة'
                );
            }

            $settings = FinanceSetting::query()->first();
            $companySnapshot = $this->buildCompanySnapshot($settings);
            $recipientSnapshot = $this->buildRecipientSnapshot($type, $customer, $supplier, $customerName);
            $pdfSnapshot = $this->buildPdfSnapshot($settings, $companySnapshot);
            $issuedAt = $invoiceStatus === 'issued' ? now() : null;

            $attributes = [
                'workspace_id' => $workspace->id,
                'customer_id' => $customerId,
                'customer_name' => $type === 'sales' && $customerName !== '' ? $customerName : null,
                'supplier_id' => $supplierId,
                'invoice_number' => ($payload['invoice_number'] ?? null) ?: $this->nextInvoiceNumber($workspace->id),
                'type' => $type,
                'status' => $legacyStatus,
                'issue_date' => (string) $payload['issue_date'],
                'due_date' => ($payload['due_date'] ?? null) ?: null,
                'currency' => (string) ($payload['currency'] ?? 'SAR'),
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'taxable_amount' => $totals['taxable_amount'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'amount_paid' => $this->money($amountPaid),
                'amount_due' => $amountDue,
                'tax_profile_type' => $profile['type'],
                'tax_rate' => $this->money($profile['rate']),
                'payment_terms' => ($payload['payment_terms'] ?? null) ?: null,
                'notes' => ($payload['notes'] ?? null) ?: null,
                'created_by' => $actorUserId,
            ];

            if ($supportsSplitStatuses) {
                $attributes['invoice_status'] = $invoiceStatus;
                $attributes['payment_status'] = $paymentStatus;
                $attributes['issued_at'] = $issuedAt;
            }

            if ($supportsSnapshots) {
                $attributes['company_snapshot'] = $companySnapshot;
                $attributes['recipient_snapshot'] = $recipientSnapshot;
                $attributes['pdf_snapshot'] = $pdfSnapshot;
            }

            $invoice = FinanceInvoice::withoutGlobalScopes()->create($attributes);

            foreach ($items as $item) {
                FinanceInvoiceItem::withoutGlobalScopes()->create([
                    'workspace_id' => $workspace->id,
                    'invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'],
                    'tax_rate' => $item['tax_rate'],
                    'tax_amount' => $item['tax_amount'],
                    'taxable_amount' => $item['taxable_amount'],
                    'total' => $item['total'],
                    'metadata' => $item['metadata'],
                ]);
            }

            if ($invoice->invoice_status === 'issued') {
                $this->postInvoiceEntry($invoice, $actorUserId);
            }

            return $invoice->load(['items', 'customer', 'supplier']);
        });
    }

    public function cancel(FinanceInvoice $invoice): FinanceInvoice
    {
        $currentInvoiceStatus = $invoice->invoice_status
            ?? $this->invoiceStateService->resolveInvoiceStatus($invoice->status);

        if ($currentInvoiceStatus === 'cancelled') {
            return $invoice;
        }

        if ((float) $invoice->amount_paid > 0) {
            throw new RuntimeException('لا يمكن إلغاء فاتورة مدفوعة أو مدفوعة جزئيًا بشكل مباشر.');
        }

        $paymentStatus = $this->invoiceStateService->resolvePaymentStatus(
            total: (float) $invoice->total,
            amountPaid: (float) $invoice->amount_paid,
            dueDate: null,
            invoiceStatus: 'cancelled',
        );

        $attributes = [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ];
        if (FinanceInvoice::hasSeparatedStatusColumns()) {
            $attributes['invoice_status'] = 'cancelled';
            $attributes['payment_status'] = $paymentStatus;
        }

        $invoice->update($attributes);

        return $invoice->fresh(['items', 'customer', 'supplier']);
    }

    /**
     * @param  array<int, mixed>  $rawItems
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $rawItems, int $workspaceId, string $taxType, float $defaultTaxRate): array
    {
        $items = [];

        foreach ($rawItems as $rawItem) {
            $quantity = max(0.001, (float) ($rawItem['quantity'] ?? 0));
            $unitPrice = max(0.0, (float) ($rawItem['unit_price'] ?? 0));
            $discount = max(0.0, (float) ($rawItem['discount'] ?? 0));
            $lineTaxRate = isset($rawItem['tax_rate'])
                ? (float) $rawItem['tax_rate']
                : $defaultTaxRate;
            $lineTaxType = (string) ($rawItem['tax_type'] ?? $taxType);

            $lineCalc = $this->taxService->calculateLine($quantity, $unitPrice, $discount, $lineTaxType, $lineTaxRate);

            $productId = isset($rawItem['product_id']) ? (int) $rawItem['product_id'] : null;
            if ($productId) {
                $validProduct = Product::withoutGlobalScopes()
                    ->where('workspace_id', $workspaceId)
                    ->whereKey($productId)
                    ->exists();
                if (! $validProduct) {
                    throw new RuntimeException('أحد المنتجات المحددة غير صالح ضمن مساحة العمل الحالية.');
                }
            }

            $items[] = [
                'product_id' => $productId,
                'product_name' => (string) ($rawItem['product_name'] ?? ''),
                'description' => $rawItem['description'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $this->money($unitPrice),
                'discount' => $this->money($discount),
                'tax_rate' => $this->money($lineTaxRate),
                'tax_amount' => $this->money($lineCalc['tax_amount']),
                'taxable_amount' => $this->money($lineCalc['taxable_amount']),
                'total' => $this->money($lineCalc['total']),
                'metadata' => is_array($rawItem['metadata'] ?? null) ? $rawItem['metadata'] : null,
            ];
        }

        return array_values(array_filter($items, function (array $item): bool {
            return $item['product_name'] !== '' || ((float) $item['total']) > 0;
        }));
    }

    /**
     * @param  array<int, array<string,mixed>>  $items
     * @return array{subtotal:float,discount:float,taxable_amount:float,tax_amount:float,total:float}
     */
    private function totals(array $items): array
    {
        $subtotal = 0.0;
        $discount = 0.0;
        $taxable = 0.0;
        $tax = 0.0;
        $total = 0.0;

        foreach ($items as $item) {
            $lineSubtotal = $this->money(((float) $item['quantity']) * ((float) $item['unit_price']));
            $subtotal += $lineSubtotal;
            $discount += (float) $item['discount'];
            $taxable += (float) $item['taxable_amount'];
            $tax += (float) $item['tax_amount'];
            $total += (float) $item['total'];
        }

        return [
            'subtotal' => $this->money($subtotal),
            'discount' => $this->money($discount),
            'taxable_amount' => $this->money($taxable),
            'tax_amount' => $this->money($tax),
            'total' => $this->money($total),
        ];
    }

    public function refreshIssuedPaymentStatuses(?int $workspaceId = null): int
    {
        return $this->invoiceStateService->refreshIssuedStatuses($workspaceId);
    }

    public function syncPaymentStatus(FinanceInvoice $invoice): FinanceInvoice
    {
        $invoiceStatus = $invoice->invoice_status
            ?? $this->invoiceStateService->resolveInvoiceStatus($invoice->status);

        if ($invoiceStatus !== 'issued') {
            return $invoice;
        }

        $paid = round((float) FinanceInvoicePayment::withoutGlobalScopes()
            ->where('workspace_id', $invoice->workspace_id)
            ->where('invoice_id', $invoice->id)
            ->sum('amount'), 2);
        $due = $this->invoiceStateService->resolveAmountDue((float) $invoice->total, $paid);
        $paymentStatus = $this->invoiceStateService->resolvePaymentStatus(
            total: (float) $invoice->total,
            amountPaid: $paid,
            dueDate: $invoice->due_date?->toDateString(),
            invoiceStatus: $invoiceStatus,
        );
        $legacyStatus = $this->invoiceStateService->toLegacyStatus($invoiceStatus, $paymentStatus);

        $attributes = [
            'amount_paid' => $paid,
            'amount_due' => $due,
            'status' => $legacyStatus,
        ];
        if (FinanceInvoice::hasSeparatedStatusColumns()) {
            $attributes['invoice_status'] = $invoiceStatus;
            $attributes['payment_status'] = $paymentStatus;
        }

        if (
            (float) $invoice->amount_paid !== $paid
            || (float) $invoice->amount_due !== $due
            || $invoice->status !== $legacyStatus
            || (
                FinanceInvoice::hasSeparatedStatusColumns()
                && (
                    $invoice->payment_status !== $paymentStatus
                    || $invoice->invoice_status !== $invoiceStatus
                )
            )
        ) {
            $invoice->update($attributes);

            return $invoice->fresh();
        }

        return $invoice;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{type:string,rate:float}
     */
    private function resolveTaxProfile(Workspace $workspace, array $payload): array
    {
        if (isset($payload['tax_profile_type']) || isset($payload['tax_rate'])) {
            return [
                'type' => (string) ($payload['tax_profile_type'] ?? 'standard'),
                'rate' => (float) ($payload['tax_rate'] ?? 0),
            ];
        }

        return $this->taxService->defaultProfileForWorkspace($workspace);
    }

    private function postInvoiceEntry(FinanceInvoice $invoice, int $actorUserId): void
    {
        $entryType = $invoice->type === 'sales' ? 'sales_invoice' : 'purchase_invoice';
        $alreadyPosted = FinanceJournalEntry::withoutGlobalScopes()
            ->where('workspace_id', $invoice->workspace_id)
            ->where('type', $entryType)
            ->where('reference_type', FinanceInvoice::class)
            ->where('reference_id', $invoice->id)
            ->exists();
        if ($alreadyPosted) {
            return;
        }

        $ar = $this->chartOfAccountsService->byCode('1200');
        $ap = $this->chartOfAccountsService->byCode('2000');
        $sales = $this->chartOfAccountsService->byCode('4000');
        $generalExpense = $this->chartOfAccountsService->byCode('5900');
        $outputVat = $this->chartOfAccountsService->byCode('2100');
        $inputVat = $this->chartOfAccountsService->byCode('1400');

        if (! $ar || ! $ap || ! $sales || ! $generalExpense || ! $outputVat || ! $inputVat) {
            throw new RuntimeException('دليل الحسابات غير مكتمل ولا يمكن ترحيل الفاتورة محاسبيًا.');
        }

        if ($invoice->type === 'sales') {
            $lines = [
                [
                    'account_id' => $ar->id,
                    'debit' => (float) $invoice->total,
                    'credit' => 0,
                    'description' => 'Sales invoice receivable',
                    'entity_type' => FinanceInvoice::class,
                    'entity_id' => $invoice->id,
                ],
                [
                    'account_id' => $sales->id,
                    'debit' => 0,
                    'credit' => (float) $invoice->taxable_amount,
                    'description' => 'Sales revenue',
                    'entity_type' => FinanceInvoice::class,
                    'entity_id' => $invoice->id,
                ],
            ];

            if ((float) $invoice->tax_amount > 0) {
                $lines[] = [
                    'account_id' => $outputVat->id,
                    'debit' => 0,
                    'credit' => (float) $invoice->tax_amount,
                    'description' => 'Output VAT',
                    'entity_type' => FinanceInvoice::class,
                    'entity_id' => $invoice->id,
                ];
            }
        } else {
            $lines = [
                [
                    'account_id' => $generalExpense->id,
                    'debit' => (float) $invoice->taxable_amount,
                    'credit' => 0,
                    'description' => 'Purchase expense/inventory',
                    'entity_type' => FinanceInvoice::class,
                    'entity_id' => $invoice->id,
                ],
            ];

            if ((float) $invoice->tax_amount > 0) {
                $lines[] = [
                    'account_id' => $inputVat->id,
                    'debit' => (float) $invoice->tax_amount,
                    'credit' => 0,
                    'description' => 'Input VAT',
                    'entity_type' => FinanceInvoice::class,
                    'entity_id' => $invoice->id,
                ];
            }

            $lines[] = [
                'account_id' => $ap->id,
                'debit' => 0,
                'credit' => (float) $invoice->total,
                'description' => 'Accounts payable',
                'entity_type' => FinanceInvoice::class,
                'entity_id' => $invoice->id,
            ];
        }

        $this->accountingService->createEntry(
            workspaceId: (int) $invoice->workspace_id,
            entryDate: $invoice->issue_date?->toDateString() ?? now()->toDateString(),
            type: $entryType,
            lines: $lines,
            description: 'Invoice '.$invoice->invoice_number,
            referenceType: FinanceInvoice::class,
            referenceId: $invoice->id,
            postedBy: $actorUserId
        );
    }

    private function nextInvoiceNumber(int $workspaceId): string
    {
        $settings = FinanceSetting::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->lockForUpdate()
            ->first();

        if (! $settings) {
            $settings = FinanceSetting::withoutGlobalScopes()->create([
                'workspace_id' => $workspaceId,
                'currency' => 'SAR',
                'country_code' => 'SA',
                'invoice_prefix' => 'INV',
                'next_invoice_sequence' => 1,
                'default_vat_rate' => 15.00,
            ]);
        }

        $prefix = $settings->invoice_prefix ?: 'INV';
        $sequence = (int) $settings->next_invoice_sequence;
        $number = sprintf('%s-%06d', $prefix, $sequence);

        $settings->update(['next_invoice_sequence' => $sequence + 1]);

        return $number;
    }

    private function money(float $value): float
    {
        return round($value, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCompanySnapshot(?FinanceSetting $setting): array
    {
        return [
            'company_name' => $setting?->company_name,
            'company_name_ar' => $setting?->company_name_ar,
            'vat_number' => $setting?->vat_number,
            'commercial_registration' => $setting?->commercial_registration,
            'address_line' => $setting?->address_line,
            'building_number' => $setting?->building_number,
            'street' => $setting?->street,
            'district' => $setting?->district,
            'city' => $setting?->city,
            'postal_code' => $setting?->postal_code,
            'country_code' => $setting?->country_code,
            'phone' => $setting?->phone,
            'email' => $setting?->email,
            'website' => $setting?->website,
            'currency' => $setting?->currency,
            'invoice_prefix' => $setting?->invoice_prefix,
            'default_payment_terms' => $setting?->default_payment_terms,
            // Store path only to avoid binary snapshot bloat.
            'logo_path' => $setting?->logo_path,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRecipientSnapshot(
        string $type,
        ?Customer $customer,
        ?FinanceSupplier $supplier,
        string $cashCustomerName
    ): array {
        if ($type === 'purchase') {
            return [
                'kind' => 'supplier',
                'name' => $supplier?->name,
                'name_ar' => $supplier?->arabic_name,
                'vat_number' => $supplier?->vat_number,
                'commercial_registration' => $supplier?->commercial_registration,
                'address' => $supplier?->address,
                'phone' => $supplier?->phone,
                'email' => $supplier?->email,
            ];
        }

        return [
            'kind' => 'customer',
            'name' => $customer?->name ?: $cashCustomerName,
            'vat_number' => $customer?->vat_number,
            'commercial_registration' => $customer?->commercial_registration,
            'address' => $customer?->address,
            'phone' => $customer?->phone,
            'email' => $customer?->email,
            'payment_terms' => $customer?->payment_terms,
        ];
    }

    /**
     * @param  array<string, mixed>  $companySnapshot
     * @return array<string, mixed>
     */
    private function buildPdfSnapshot(?FinanceSetting $setting, array $companySnapshot): array
    {
        return [
            'primary_color' => $setting?->invoice_primary_color ?: '#06C2A4',
            'footer_text' => $setting?->invoice_footer_text ?: null,
            'company' => $companySnapshot,
        ];
    }
}
