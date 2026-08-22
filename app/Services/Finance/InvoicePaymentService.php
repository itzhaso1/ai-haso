<?php

namespace App\Services\Finance;

use App\Models\Finance\FinanceInvoice;
use App\Models\Finance\FinanceInvoicePayment;
use App\Models\Finance\FinanceTreasuryAccount;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoicePaymentService
{
    public function __construct(
        private readonly AccountingService $accountingService,
        private readonly ChartOfAccountsService $chartOfAccountsService,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     */
    public function recordPayment(FinanceInvoice $invoice, array $payload, int $actorUserId): FinanceInvoicePayment
    {
        if (! in_array($invoice->status, ['unpaid', 'partial', 'overdue', 'sent'], true)) {
            throw new RuntimeException('Payment cannot be recorded for the current invoice status.');
        }

        $amount = round((float) ($payload['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        if ($amount > (float) $invoice->amount_due) {
            throw new RuntimeException('Payment amount cannot exceed invoice due amount.');
        }

        return DB::transaction(function () use ($invoice, $payload, $amount, $actorUserId): FinanceInvoicePayment {
            $treasuryAccount = null;
            if (! empty($payload['treasury_account_id'])) {
                $treasuryAccount = FinanceTreasuryAccount::query()
                    ->whereKey((int) $payload['treasury_account_id'])
                    ->first();
            }

            if (! $treasuryAccount) {
                $treasuryAccount = FinanceTreasuryAccount::query()
                    ->where('type', 'bank')
                    ->orderBy('id')
                    ->first()
                    ?? FinanceTreasuryAccount::query()
                        ->where('type', 'cash')
                        ->orderBy('id')
                        ->first();
            }

            $payment = FinanceInvoicePayment::withoutGlobalScopes()->create([
                'workspace_id' => $invoice->workspace_id,
                'invoice_id' => $invoice->id,
                'treasury_account_id' => $treasuryAccount?->id,
                'payment_date' => $payload['payment_date'] ?? now()->toDateString(),
                'method' => $payload['method'] ?? 'cash',
                'reference' => $payload['reference'] ?? null,
                'amount' => $amount,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actorUserId,
            ]);

            $paid = round((float) $invoice->amount_paid + $amount, 2);
            $due = max(0, round((float) $invoice->total - $paid, 2));
            $status = $due <= 0.009 ? 'paid' : 'partial';

            $invoice->update([
                'amount_paid' => $paid,
                'amount_due' => $due,
                'status' => $status,
            ]);

            $this->postPaymentEntry($invoice, $payment, $treasuryAccount?->id, $actorUserId);

            if ($treasuryAccount) {
                $treasuryAccount->update([
                    'current_balance' => round((float) $treasuryAccount->current_balance + $amount, 2),
                ]);
            }

            return $payment;
        });
    }

    private function postPaymentEntry(FinanceInvoice $invoice, FinanceInvoicePayment $payment, ?int $treasuryAccountId, int $actorUserId): void
    {
        $ar = $this->chartOfAccountsService->byCode('1200');
        $ap = $this->chartOfAccountsService->byCode('2000');
        $cash = $this->chartOfAccountsService->byCode('1000');
        $bank = $this->chartOfAccountsService->byCode('1100');

        if (! $ar || ! $ap || ! $cash || ! $bank) {
            throw new RuntimeException('Chart of accounts is incomplete.');
        }

        $targetDrOrCr = $cash;
        if ($treasuryAccountId) {
            $treasury = FinanceTreasuryAccount::query()->whereKey($treasuryAccountId)->first();
            if ($treasury?->linked_finance_account_id) {
                $linked = $treasury->linkedAccount;
                if ($linked) {
                    $targetDrOrCr = $linked;
                }
            } elseif ($treasury?->type === 'bank') {
                $targetDrOrCr = $bank;
            }
        }

        $amount = (float) $payment->amount;
        if ($invoice->type === 'sales') {
            $lines = [
                [
                    'account_id' => $targetDrOrCr->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Invoice payment received',
                    'entity_type' => FinanceInvoicePayment::class,
                    'entity_id' => $payment->id,
                ],
                [
                    'account_id' => $ar->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'Reduce accounts receivable',
                    'entity_type' => FinanceInvoicePayment::class,
                    'entity_id' => $payment->id,
                ],
            ];
        } else {
            $lines = [
                [
                    'account_id' => $ap->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Reduce accounts payable',
                    'entity_type' => FinanceInvoicePayment::class,
                    'entity_id' => $payment->id,
                ],
                [
                    'account_id' => $targetDrOrCr->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'Cash/Bank paid out',
                    'entity_type' => FinanceInvoicePayment::class,
                    'entity_id' => $payment->id,
                ],
            ];
        }

        $this->accountingService->createEntry(
            workspaceId: (int) $invoice->workspace_id,
            entryDate: $payment->payment_date?->toDateString() ?? now()->toDateString(),
            type: 'invoice_payment',
            lines: $lines,
            description: 'Payment for invoice '.$invoice->invoice_number,
            referenceType: FinanceInvoicePayment::class,
            referenceId: $payment->id,
            postedBy: $actorUserId
        );
    }
}
