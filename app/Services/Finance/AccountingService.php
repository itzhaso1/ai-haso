<?php

namespace App\Services\Finance;

use App\Models\Finance\FinanceAccount;
use App\Models\Finance\FinanceJournalEntry;
use App\Models\Finance\FinanceJournalEntryLine;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AccountingService
{
    /**
     * @param  array<int, array{account_id:int,debit:float|int|string,credit:float|int|string,description?:string|null,entity_type?:string|null,entity_id?:int|null}>  $lines
     */
    public function createEntry(
        int $workspaceId,
        string $entryDate,
        string $type,
        array $lines,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $postedBy = null,
        string $status = 'posted',
        ?int $reversesEntryId = null,
    ): FinanceJournalEntry {
        if (count($lines) < 2) {
            throw new RuntimeException('Journal entry requires at least two lines.');
        }

        [$totalDebit, $totalCredit] = $this->totals($lines);
        if (abs($totalDebit - $totalCredit) > 0.009) {
            throw new RuntimeException('Journal entry is not balanced. Total debit must equal total credit.');
        }

        return DB::transaction(function () use (
            $workspaceId,
            $entryDate,
            $type,
            $lines,
            $description,
            $referenceType,
            $referenceId,
            $postedBy,
            $status,
            $reversesEntryId
        ): FinanceJournalEntry {
            $entryNumber = $this->nextEntryNumber($workspaceId, $entryDate);

            $entry = FinanceJournalEntry::withoutGlobalScopes()->create([
                'workspace_id' => $workspaceId,
                'entry_number' => $entryNumber,
                'entry_date' => $entryDate,
                'type' => $type,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reverses_entry_id' => $reversesEntryId,
                'description' => $description,
                'status' => $status,
                'posted_by' => $postedBy,
            ]);

            foreach ($lines as $line) {
                $account = FinanceAccount::withoutGlobalScopes()
                    ->where('workspace_id', $workspaceId)
                    ->whereKey((int) $line['account_id'])
                    ->first();

                if (! $account) {
                    throw new RuntimeException('Accounting line account is invalid for this workspace.');
                }

                FinanceJournalEntryLine::withoutGlobalScopes()->create([
                    'workspace_id' => $workspaceId,
                    'journal_entry_id' => $entry->id,
                    'account_id' => $account->id,
                    'description' => $line['description'] ?? null,
                    'debit' => $this->money((float) $line['debit']),
                    'credit' => $this->money((float) $line['credit']),
                    'entity_type' => $line['entity_type'] ?? null,
                    'entity_id' => $line['entity_id'] ?? null,
                ]);
            }

            return $entry->load('lines');
        });
    }

    public function reverseEntry(
        FinanceJournalEntry $entry,
        string $type,
        int $actorUserId,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): FinanceJournalEntry {
        $alreadyReversed = FinanceJournalEntry::withoutGlobalScopes()
            ->where('workspace_id', $entry->workspace_id)
            ->where('reverses_entry_id', $entry->id)
            ->exists();
        if ($alreadyReversed || $entry->status === 'reversed') {
            throw new RuntimeException('Journal entry is already reversed.');
        }

        $entry->loadMissing('lines');
        if ($entry->lines->count() < 2) {
            throw new RuntimeException('Cannot reverse an incomplete journal entry.');
        }

        $lines = $entry->lines->map(fn (FinanceJournalEntryLine $line): array => [
            'account_id' => (int) $line->account_id,
            'debit' => (float) $line->credit,
            'credit' => (float) $line->debit,
            'description' => 'Reversal: '.((string) ($line->description ?: $entry->description)),
            'entity_type' => $line->entity_type,
            'entity_id' => $line->entity_id,
        ])->all();

        return DB::transaction(function () use ($entry, $type, $actorUserId, $description, $referenceType, $referenceId, $lines): FinanceJournalEntry {
            $reversal = $this->createEntry(
                workspaceId: (int) $entry->workspace_id,
                entryDate: now()->toDateString(),
                type: $type,
                lines: $lines,
                description: $description ?: ('Reversal of '.$entry->entry_number),
                referenceType: $referenceType ?: $entry->reference_type,
                referenceId: $referenceId ?: $entry->reference_id,
                postedBy: $actorUserId,
                reversesEntryId: $entry->id,
            );

            $metadataNote = trim((string) ($entry->description ?? ''));
            $entry->update([
                'description' => trim($metadataNote.' [reversed by '.$reversal->entry_number.']'),
            ]);

            return $reversal;
        });
    }

    /**
     * @param  array<int, array{debit:float|int|string,credit:float|int|string}>  $lines
     * @return array{0:float,1:float}
     */
    private function totals(array $lines): array
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $debit = $this->money((float) $line['debit']);
            $credit = $this->money((float) $line['credit']);

            if ($debit < 0 || $credit < 0) {
                throw new RuntimeException('Debit/Credit cannot be negative.');
            }

            if ($debit > 0 && $credit > 0) {
                throw new RuntimeException('Single line cannot have both debit and credit amounts.');
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        return [$this->money($totalDebit), $this->money($totalCredit)];
    }

    private function nextEntryNumber(int $workspaceId, string $entryDate): string
    {
        $year = date('Y', strtotime($entryDate));
        $prefix = 'JE-'.$year.'-';

        $last = FinanceJournalEntry::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('entry_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        if (! $last) {
            return $prefix.'000001';
        }

        $lastNumber = (int) substr($last->entry_number, -6);

        return $prefix.str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }

    private function money(float $value): float
    {
        return round($value, 2);
    }
}
