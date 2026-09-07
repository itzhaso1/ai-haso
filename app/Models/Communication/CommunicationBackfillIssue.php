<?php

namespace App\Models\Communication;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\WorkspaceScopedModel;

class CommunicationBackfillIssue extends WorkspaceScopedModel
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'issue_type',
        'entity_type',
        'entity_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
