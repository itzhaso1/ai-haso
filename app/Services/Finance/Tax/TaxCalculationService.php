<?php

namespace App\Services\Finance\Tax;

use App\Enums\Finance\TaxProfileType;
use App\Models\Finance\FinanceSetting;
use App\Models\Finance\FinanceTaxRate;
use App\Models\Workspace;
use App\Support\Money\Money;

/**
 * Invoice-domain tax calculator.
 *
 * Phase 1 preserves current exclusive-price rounding behavior.
 * Phase 2 will extend this service with ZATCA category codes, exemption
 * reasons, and category totals. Do not call ZATCA from here.
 */
class TaxCalculationService
{
    /**
     * Bootstrap/fallback standard VAT rate used only when a workspace has
     * no finance settings and no default tax rate row yet.
     *
     * Technical debt: this is not a legal rate table. Workspace defaults
     * live on finance_settings.default_vat_rate.
     */
    public const FALLBACK_STANDARD_RATE = 15.00;

    /**
     * @return array{type:string, rate:float}
     */
    public function defaultProfileForWorkspace(Workspace $workspace): array
    {
        $defaultTaxRate = FinanceTaxRate::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($defaultTaxRate) {
            return [
                'type' => TaxProfileType::tryFrom((string) $defaultTaxRate->type)?->value ?? TaxProfileType::Standard->value,
                'rate' => (float) $defaultTaxRate->rate,
            ];
        }

        $settings = FinanceSetting::forWorkspaceId((int) $workspace->id);

        return [
            'type' => TaxProfileType::Standard->value,
            'rate' => (float) ($settings?->default_vat_rate ?? self::FALLBACK_STANDARD_RATE),
        ];
    }

    public function normalizeProfileType(?string $type, ?string $fallback = null): string
    {
        return TaxProfileType::tryFrom((string) $type)?->value
            ?? ($fallback ?? TaxProfileType::Standard->value);
    }

    /**
     * @return array{taxable_amount:float,tax_amount:float,total:float}
     */
    public function calculateAmount(float $amount, string $taxType, float $rate): array
    {
        $taxableAmount = $this->roundMoney($amount);
        $taxAmount = $this->isTaxable($taxType)
            ? $this->roundMoney($taxableAmount * ($rate / 100))
            : 0.0;

        return [
            'taxable_amount' => $taxableAmount,
            'tax_amount' => $taxAmount,
            'total' => $this->roundMoney($taxableAmount + $taxAmount),
        ];
    }

    /**
     * @return array{taxable_amount:float,tax_amount:float,total:float}
     */
    public function calculateLine(float $quantity, float $unitPrice, float $discount, string $taxType, float $rate): array
    {
        $lineSubtotal = $this->roundMoney($quantity * $unitPrice);
        $lineDiscount = min($this->roundMoney($discount), $lineSubtotal);
        $taxableAmount = $this->roundMoney($lineSubtotal - $lineDiscount);
        $taxAmount = $this->isTaxable($taxType)
            ? $this->roundMoney($taxableAmount * ($rate / 100))
            : 0.0;

        return [
            'taxable_amount' => $taxableAmount,
            'tax_amount' => $taxAmount,
            'total' => $this->roundMoney($taxableAmount + $taxAmount),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal:float,discount:float,taxable_amount:float,tax_amount:float,total:float}
     */
    public function totals(array $items): array
    {
        $subtotal = 0.0;
        $discount = 0.0;
        $taxable = 0.0;
        $tax = 0.0;
        $total = 0.0;

        foreach ($items as $item) {
            $lineSubtotal = $this->roundMoney(((float) $item['quantity']) * ((float) $item['unit_price']));
            $subtotal += $lineSubtotal;
            $discount += (float) $item['discount'];
            $taxable += (float) $item['taxable_amount'];
            $tax += (float) $item['tax_amount'];
            $total += (float) $item['total'];
        }

        return [
            'subtotal' => $this->roundMoney($subtotal),
            'discount' => $this->roundMoney($discount),
            'taxable_amount' => $this->roundMoney($taxable),
            'tax_amount' => $this->roundMoney($tax),
            'total' => $this->roundMoney($total),
        ];
    }

    public function isTaxable(string $taxType): bool
    {
        return $this->normalizeProfileType($taxType) === TaxProfileType::Standard->value;
    }

    public function roundMoney(float $amount): float
    {
        return Money::round($amount);
    }
}
