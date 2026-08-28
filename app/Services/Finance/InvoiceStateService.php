<?php

namespace App\Services\Finance;

use App\Models\Finance\FinanceInvoice;
use Illuminate\Support\Carbon;

class InvoiceStateService
{
    public const PAYMENT_TOLERANCE = 0.009;

    public function resolveInvoiceStatus(?string $requestedStatus): string
    {
        $normalized = strtolower(trim((string) $requestedStatus));

        return match ($normalized) {
            'cancelled' => 'cancelled',
            'issued', 'unpaid', 'partial', 'paid', 'overdue', 'sent' => 'issued',
            default => 'draft',
        };
    }

    public function resolvePaymentStatus(
        float $total,
        float $amountPaid,
        ?string $dueDate,
        string $invoiceStatus
    ): string {
        $paid = round(max(0, $amountPaid), 2);
        $due = $this->resolveAmountDue($total, $paid);

        if ($due <= self::PAYMENT_TOLERANCE) {
            return 'paid';
        }

        if ($paid > self::PAYMENT_TOLERANCE) {
            return 'partial';
        }

        if (
            $invoiceStatus === 'issued'
            && $due > self::PAYMENT_TOLERANCE
            && $dueDate
            && $this->isPastDate($dueDate)
        ) {
            return 'overdue';
        }

        return 'unpaid';
    }

    public function resolveAmountDue(float $total, float $amountPaid): float
    {
        return round(max(0, $total - $amountPaid), 2);
    }

    public function toLegacyStatus(string $invoiceStatus, string $paymentStatus): string
    {
        if ($invoiceStatus === 'cancelled') {
            return 'cancelled';
        }

        if ($invoiceStatus === 'draft') {
            return 'draft';
        }

        return in_array($paymentStatus, ['unpaid', 'partial', 'paid', 'overdue'], true)
            ? $paymentStatus
            : 'unpaid';
    }

    /**
     * Recalculate payment status and due amounts for issued invoices.
     * Returns number of updated rows.
     */
    public function refreshIssuedStatuses(?int $workspaceId = null): int
    {
        $updatedRows = 0;

        $query = FinanceInvoice::query()
            ->where(function ($builder): void {
                $builder->where('invoice_status', 'issued')
                    ->orWhere(function ($legacyBuilder): void {
                        $legacyBuilder->whereNull('invoice_status')
                            ->whereIn('status', ['sent', 'unpaid', 'partial', 'paid', 'overdue']);
                    });
            });

        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }

        $query->withSum('payments', 'amount')
            ->orderBy('id')
            ->chunkById(200, function ($invoices) use (&$updatedRows): void {
                foreach ($invoices as $invoice) {
                    $actualPaid = round((float) ($invoice->payments_sum_amount ?? 0), 2);
                    $due = $this->resolveAmountDue((float) $invoice->total, $actualPaid);
                    $paymentStatus = $this->resolvePaymentStatus(
                        total: (float) $invoice->total,
                        amountPaid: $actualPaid,
                        dueDate: $invoice->due_date?->toDateString(),
                        invoiceStatus: 'issued',
                    );
                    $legacyStatus = $this->toLegacyStatus('issued', $paymentStatus);

                    $dirty = false;
                    $attributes = [];

                    if ((float) $invoice->amount_paid !== $actualPaid) {
                        $attributes['amount_paid'] = $actualPaid;
                        $dirty = true;
                    }
                    if ((float) $invoice->amount_due !== $due) {
                        $attributes['amount_due'] = $due;
                        $dirty = true;
                    }
                    if ($invoice->payment_status !== $paymentStatus) {
                        $attributes['payment_status'] = $paymentStatus;
                        $dirty = true;
                    }
                    if ($invoice->status !== $legacyStatus) {
                        $attributes['status'] = $legacyStatus;
                        $dirty = true;
                    }

                    if ($dirty) {
                        $invoice->update($attributes);
                        $updatedRows++;
                    }
                }
            });

        return $updatedRows;
    }

    private function isPastDate(string $dueDate): bool
    {
        try {
            return Carbon::parse($dueDate)->isPast();
        } catch (\Throwable) {
            return false;
        }
    }
}
