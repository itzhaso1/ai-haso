<?php

namespace App\Models\Appointment;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\User;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'user_id',
    'name',
    'role',
    'phone',
    'color',
    'is_active',
    'metadata',
])]
class AppointmentStaff extends WorkspaceScopedModel
{
    use BelongsToWorkspace, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(AppointmentBooking::class, 'staff_id');
    }
}
