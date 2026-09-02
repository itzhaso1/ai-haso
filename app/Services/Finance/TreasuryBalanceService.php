<?php

namespace App\Services\Finance;

use App\Models\Finance\FinanceTreasuryAccount;

class TreasuryBalanceService
{
    public function adjust(FinanceTreasuryAccount $account, float $delta): FinanceTreasuryAccount
    {
        $locked = FinanceTreasuryAccount::withoutGlobalScopes()
            ->whereKey($account->id)
            ->lockForUpdate()
            ->firstOrFail();

        $locked->update([
            'current_balance' => round((float) $locked->current_balance + $delta, 2),
        ]);

        return $locked->refresh();
    }
}
