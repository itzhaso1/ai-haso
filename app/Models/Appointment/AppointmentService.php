<?php

namespace App\Models\Appointment;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'name',
    'description',
    'duration_minutes',
    'price',
    'color',
    'is_active',
    'requires_confirmation',
    'metadata',
])]
class AppointmentService extends WorkspaceScopedModel
{
    use BelongsToWorkspace, SoftDeletes;

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'requires_confirmation' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(AppointmentBooking::class, 'service_id');
    }
}
