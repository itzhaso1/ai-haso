<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Customer;
use App\Models\User;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'customer_id',
    'supplier_id',
    'invoice_number',
    'type',
    'status',
    'issue_date',
    'due_date',
    'currency',
    'subtotal',
    'discount',
    'taxable_amount',
    'tax_amount',
    'total',
    'amount_paid',
    'amount_due',
    'tax_profile_type',
    'tax_rate',
    'payment_terms',
    'notes',
    'zatca_uuid',
    'zatca_qr_code',
    'zatca_xml_hash',
    'created_by',
    'cancelled_at',
])]
class FinanceInvoice extends WorkspaceScopedModel
{
    use BelongsToWorkspace, SoftDeletes;

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(FinanceSupplier::class, 'supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FinanceInvoiceItem::class, 'invoice_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FinanceInvoicePayment::class, 'invoice_id');
    }
}
