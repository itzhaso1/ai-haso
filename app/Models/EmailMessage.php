<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'workspace_id',
    'email_account_id',
    'sender',
    'recipient',
    'subject',
    'body',
    'type',
    'message_id',
    'in_reply_to',
    'thread_key',
])]
class EmailMessage extends WorkspaceScopedModel
{
    use BelongsToWorkspace;

    public function account(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class, 'email_account_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class, 'message_id');
    }
}
