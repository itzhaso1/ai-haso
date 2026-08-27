<?php

namespace App\Models\Appointment;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Customer;
use App\Models\User;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'booking_number',
    'service_id',
    'staff_id',
    'customer_id',
    'customer_name',
    'customer_phone',
    'starts_at',
    'ends_at',
    'status',
    'source',
    'notes',
    'cancel_reason',
    'booked_by',
    'metadata',
])]
class AppointmentBooking extends WorkspaceScopedModel
{
    use BelongsToWorkspace, SoftDeletes;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(AppointmentService::class, 'service_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(AppointmentStaff::class, 'staff_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function booker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }
}
