<?php

namespace App\Models\Appointment;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'workspace_id',
    'business_type',
    'business_label',
    'timezone',
    'slot_interval_minutes',
    'start_hour',
    'end_hour',
    'allow_walk_in',
    'metadata',
])]
class AppointmentSetting extends WorkspaceScopedModel
{
    use BelongsToWorkspace;

    protected function casts(): array
    {
        return [
            'slot_interval_minutes' => 'integer',
            'allow_walk_in' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
