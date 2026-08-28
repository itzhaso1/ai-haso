<?php

namespace App\Services\Finance;

use App\Models\Finance\FinancePayrollAdjustment;
use App\Models\Finance\FinancePayrollRun;
use App\Models\WorkspaceUser;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayrollAdjustmentService
{
    public function __construct(
        private readonly AccountingService $accountingService,
        private readonly ChartOfAccountsService $chartOfAccountsService,
        private readonly FinancialPeriodGuardService $financialPeriodGuardService,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     */
    public function create(Workspace $workspace, string $type, array $payload): FinancePayrollAdjustment
    {
        $amount = round((float) ($payload['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new RuntimeException('قيمة الحركة يجب أن تكون أكبر من صفر.');
        }

        $workspaceUserExists = WorkspaceUser::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', (int) $payload['user_id'])
            ->where('status', 'active')
            ->exists();
        if (! $workspaceUserExists) {
            throw new RuntimeException('الموظف المحدد غير مرتبط بمساحة العمل الحالية.');
        }

        return FinancePayrollAdjustment::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'user_id' => (int) $payload['user_id'],
            'type' => $type,
            'title' => trim((string) $payload['title']),
            'amount' => $amount,
            'effective_date' => $payload['effective_date'],
            'status' => $payload['status'] ?? 'draft',
            'notes' => $payload['notes'] ?? null,
        ]);
    }

    public function approve(FinancePayrollAdjustment $adjustment, int $actorUserId): FinancePayrollAdjustment
    {
        if (! in_array($adjustment->status, ['draft', 'approved'], true)) {
            throw new RuntimeException('لا يمكن اعتماد حركة غير مسموح بحالتها الحالية.');
        }

        $adjustment->update([
            'status' => 'approved',
            'approved_by' => $actorUserId,
            'approved_at' => now(),
        ]);

        return $adjustment->refresh();
    }

    public function cancel(FinancePayrollAdjustment $adjustment): FinancePayrollAdjustment
    {
        if ($adjustment->status === 'posted') {
            throw new RuntimeException('لا يمكن إلغاء حركة تم ترحيلها محاسبيًا.');
        }

        $adjustment->update(['status' => 'cancelled']);

        return $adjustment->refresh();
    }

    public function post(FinancePayrollAdjustment $adjustment, int $actorUserId, ?FinancePayrollRun $payrollRun = null): FinancePayrollAdjustment
    {
        if (! in_array($adjustment->status, ['approved', 'draft'], true)) {
            throw new RuntimeException('يجب أن تكون الحركة في حالة مسودة أو معتمدة قبل الترحيل.');
        }

        $salaryExpense = $this->chartOfAccountsService->byCode('5000');
        $payrollPayable = $this->chartOfAccountsService->byCode('2200') ?? $this->chartOfAccountsService->byCode('2000');
        if (! $salaryExpense || ! $payrollPayable) {
            throw new RuntimeException('حسابات الرواتب غير مكتملة في دليل الحسابات.');
        }

        return DB::transaction(function () use ($adjustment, $actorUserId, $payrollRun, $salaryExpense, $payrollPayable): FinancePayrollAdjustment {
            if ($adjustment->posted_journal_entry_id) {
                return $adjustment->refresh();
            }

            $amount = round((float) $adjustment->amount, 2);
            $isPositiveExpense = in_array($adjustment->type, ['allowance', 'bonus'], true);
            $effectiveDate = $adjustment->effective_date?->toDateString() ?? now()->toDateString();

            $this->financialPeriodGuardService->assertDateIsOpen(
                workspaceId: (int) $adjustment->workspace_id,
                date: $effectiveDate,
                context: 'ترحيل حركة رواتب'
            );

            $entry = $this->accountingService->createEntry(
                workspaceId: (int) $adjustment->workspace_id,
                entryDate: $effectiveDate,
                type: 'payroll',
                lines: $isPositiveExpense
                    ? [
                        [
                            'account_id' => $salaryExpense->id,
                            'debit' => $amount,
                            'credit' => 0,
                            'description' => 'Payroll adjustment expense',
                            'entity_type' => FinancePayrollAdjustment::class,
                            'entity_id' => $adjustment->id,
                        ],
                        [
                            'account_id' => $payrollPayable->id,
                            'debit' => 0,
                            'credit' => $amount,
                            'description' => 'Payroll liability',
                            'entity_type' => FinancePayrollAdjustment::class,
                            'entity_id' => $adjustment->id,
                        ],
                    ]
                    : [
                        [
                            'account_id' => $payrollPayable->id,
                            'debit' => $amount,
                            'credit' => 0,
                            'description' => 'Payroll liability reduction',
                            'entity_type' => FinancePayrollAdjustment::class,
                            'entity_id' => $adjustment->id,
                        ],
                        [
                            'account_id' => $salaryExpense->id,
                            'debit' => 0,
                            'credit' => $amount,
                            'description' => 'Payroll expense reduction',
                            'entity_type' => FinancePayrollAdjustment::class,
                            'entity_id' => $adjustment->id,
                        ],
                    ],
                description: 'Payroll adjustment: '.$adjustment->title,
                referenceType: FinancePayrollAdjustment::class,
                referenceId: (int) $adjustment->id,
                postedBy: $actorUserId
            );

            $adjustment->update([
                'status' => 'posted',
                'payroll_run_id' => $payrollRun?->id,
                'posted_journal_entry_id' => $entry->id,
                'posted_by' => $actorUserId,
                'posted_at' => now(),
            ]);

            return $adjustment->refresh();
        });
    }
}
