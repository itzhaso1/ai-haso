<?php

namespace App\Services\Finance;

use App\Models\Finance\FinanceSetting;
use App\Models\Finance\FinanceTaxRate;
use App\Models\Workspace;

class TaxService
{
    /**
     * @return array{type:string, rate:float}
     */
    public function defaultProfileForWorkspace(Workspace $workspace): array
    {
        $defaultTaxRate = FinanceTaxRate::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($defaultTaxRate) {
            return [
                'type' => $defaultTaxRate->type,
                'rate' => (float) $defaultTaxRate->rate,
            ];
        }

        $settings = FinanceSetting::query()->first();

        return [
            'type' => 'standard',
            'rate' => (float) ($settings?->default_vat_rate ?? 15.00),
        ];
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

    public function isTaxable(string $taxType): bool
    {
        return $taxType === 'standard';
    }

    public function roundMoney(float $amount): float
    {
        return round($amount, 2);
    }
}
