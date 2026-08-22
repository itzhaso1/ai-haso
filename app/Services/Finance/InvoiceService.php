<?php

namespace App\Services\Finance;

use App\Models\Customer;
use App\Models\Finance\FinanceInvoice;
use App\Models\Finance\FinanceInvoiceItem;
use App\Models\Finance\FinanceSetting;
use App\Models\Finance\FinanceSupplier;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceService
{
    public function __construct(
        private readonly TaxService $taxService,
        private readonly ChartOfAccountsService $chartOfAccountsService,
        private readonly AccountingService $accountingService,
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

            if ($type === 'sales' && ! $customerId && $customerName === '') {
                throw new RuntimeException('فاتورة المبيعات تتطلب عميلًا مسجلًا أو اسم عميل نقدي.');
            }

            if ($type === 'purchase' && ! $supplierId) {
                throw new RuntimeException('فاتورة الشراء تتطلب اختيار مورد.');
            }

            if ($customerId) {
                $exists = Customer::query()->whereKey($customerId)->exists();
                if (! $exists) {
                    throw new RuntimeException('العميل المحدد غير صالح ضمن مساحة العمل الحالية.');
                }
            }

            if ($supplierId) {
                $exists = FinanceSupplier::query()->whereKey($supplierId)->exists();
                if (! $exists) {
                    throw new RuntimeException('المورد المحدد غير صالح ضمن مساحة العمل الحالية.');
                }
            }

            $items = $this->normalizeItems($payload['items'] ?? [], $profile['type'], $profile['rate']);
            if ($items === []) {
                throw new RuntimeException('يجب أن تحتوي الفاتورة على بند واحد على الأقل.');
            }

            $totals = $this->totals($items);
            $amountPaid = max(0, (float) ($payload['amount_paid'] ?? 0));
            $amountDue = max(0, $this->money($totals['total'] - $amountPaid));
            $status = $this->resolveStatus((string) ($payload['status'] ?? 'draft'), $amountDue, (string) ($payload['due_date'] ?? ''));

            $invoice = FinanceInvoice::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'customer_id' => $customerId,
                'customer_name' => $type === 'sales' && $customerName !== '' ? $customerName : null,
                'supplier_id' => $supplierId,
                'invoice_number' => $payload['invoice_number'] ?: $this->nextInvoiceNumber($workspace->id),
                'type' => $type,
                'status' => $status,
                'issue_date' => (string) $payload['issue_date'],
                'due_date' => $payload['due_date'] ?: null,
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
                'payment_terms' => $payload['payment_terms'] ?: null,
                'notes' => $payload['notes'] ?: null,
                'created_by' => $actorUserId,
            ]);

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

            if (! in_array($invoice->status, ['draft', 'cancelled'], true)) {
                $this->postInvoiceEntry($invoice, $actorUserId);
            }

            return $invoice->load(['items', 'customer', 'supplier']);
        });
    }

    public function cancel(FinanceInvoice $invoice): FinanceInvoice
    {
        if ($invoice->status === 'cancelled') {
            return $invoice;
        }

        if ((float) $invoice->amount_paid > 0) {
            throw new RuntimeException('لا يمكن إلغاء فاتورة مدفوعة أو مدفوعة جزئيًا بشكل مباشر.');
        }

        $invoice->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return $invoice->fresh(['items', 'customer', 'supplier']);
    }

    /**
     * @param  array<int, mixed>  $rawItems
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $rawItems, string $taxType, float $defaultTaxRate): array
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

            $items[] = [
                'product_id' => isset($rawItem['product_id']) ? (int) $rawItem['product_id'] : null,
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

    private function resolveStatus(string $requestedStatus, float $amountDue, string $dueDate): string
    {
        if ($requestedStatus === 'cancelled') {
            return 'cancelled';
        }

        if ($requestedStatus === 'draft') {
            return 'draft';
        }

        if ($amountDue <= 0.009) {
            return 'paid';
        }

        if ($amountDue > 0 && in_array($requestedStatus, ['partial', 'paid'], true)) {
            return 'partial';
        }

        if ($dueDate !== '' && Carbon::parse($dueDate)->isPast()) {
            return 'overdue';
        }

        return 'unpaid';
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
            type: $invoice->type === 'sales' ? 'sales_invoice' : 'purchase_invoice',
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
}
