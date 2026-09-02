<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id',
    'credit_note_id',
    'product_name',
    'description',
    'quantity',
    'unit_price',
    'discount',
    'tax_rate',
    'tax_amount',
    'taxable_amount',
    'total',
    'metadata',
])]
class FinanceCreditNoteItem extends WorkspaceScopedModel
{
    use BelongsToWorkspace;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(FinanceCreditNote::class, 'credit_note_id');
    }
}
