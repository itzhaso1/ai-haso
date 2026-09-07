<?php

namespace App\Models\Communication;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\User;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationTeamMember extends WorkspaceScopedModel
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'team_id',
        'user_id',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(CommunicationTeam::class, 'team_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
