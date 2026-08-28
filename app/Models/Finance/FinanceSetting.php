<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'workspace_id',
    'company_name',
    'company_name_ar',
    'logo_path',
    'vat_number',
    'commercial_registration',
    'address_line',
    'building_number',
    'street',
    'district',
    'city',
    'postal_code',
    'country_code',
    'phone',
    'email',
    'website',
    'currency',
    'invoice_prefix',
    'next_invoice_sequence',
    'invoice_primary_color',
    'invoice_footer_text',
    'default_payment_terms',
    'default_vat_rate',
    'zatca_integration_mode',
    'zatca_certificate_serial',
    'zatca_last_synced_at',
    'metadata',
])]
class FinanceSetting extends WorkspaceScopedModel
{
    use BelongsToWorkspace;

    protected function casts(): array
    {
        return [
            'default_vat_rate' => 'decimal:2',
            'next_invoice_sequence' => 'integer',
            'zatca_last_synced_at' => 'datetime',
            'invoice_primary_color' => 'string',
            'metadata' => 'array',
        ];
    }
}
