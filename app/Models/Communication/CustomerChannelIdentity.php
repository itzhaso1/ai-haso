<?php

namespace App\Models\Communication;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerChannelIdentity extends WorkspaceScopedModel
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'customer_id',
        'channel',
        'identifier',
        'identifier_raw',
        'channel_connection_id',
        'conversation_id',
        'match_rule',
        'matched_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'matched_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function channelConnection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'channel_connection_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
