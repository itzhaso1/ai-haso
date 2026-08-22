<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'workspace_id',
    'name',
    'email',
    'normalized_email',
])]
class EmailContact extends WorkspaceScopedModel
{
    use BelongsToWorkspace;
}
