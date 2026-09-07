<?php

namespace App\Models;

use App\Models\Communication\ChannelConnection;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'workspace_id',
    'conversation_id',
    'channel_connection_id',
    'customer_id',
    'user_id',
    'direction',
    'message_type',
    'content',
    'external_message_id',
    'ai_generated',
    'delivery_status',
    'delivery_error',
    'delivered_at',
    'metadata',
])]
class Message extends WorkspaceScopedModel
{
    /** @use HasFactory<MessageFactory> */
    use BelongsToWorkspace, HasFactory;

    public const DELIVERY_PENDING = 'pending';

    public const DELIVERY_SENT = 'sent';

    public const DELIVERY_FAILED = 'failed';

    public const DELIVERY_RECEIVED = 'received';

    public const DELIVERY_DELIVERED = 'delivered';

    public const DELIVERY_NA = 'n_a';

    protected function casts(): array
    {
        return [
            'ai_generated' => 'boolean',
            'metadata' => 'array',
            'delivered_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channelConnection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'channel_connection_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }
}
