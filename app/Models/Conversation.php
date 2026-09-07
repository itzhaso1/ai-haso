<?php

namespace App\Models;

use App\Models\Communication\ChannelConnection;
use App\Models\Communication\CommunicationTeam;
use App\Models\Communication\CustomerChannelIdentity;
use App\Models\Concerns\BelongsToWorkspace;
use App\Support\Communication\ConversationIdentityKey;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'customer_id',
    'channel',
    'channel_connection_id',
    'external_id',
    'identity_key',
    'status',
    'priority',
    'assigned_team_id',
    'assigned_user_id',
    'assigned_at',
    'ai_enabled',
    'last_message_at',
    'metadata',
])]
class Conversation extends WorkspaceScopedModel
{
    /** @use HasFactory<ConversationFactory> */
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'ai_enabled' => 'boolean',
            'last_message_at' => 'datetime',
            'assigned_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        parent::booted();

        static::saving(function (Conversation $conversation): void {
            $conversation->identity_key = ConversationIdentityKey::make(
                $conversation->channel_connection_id ? (int) $conversation->channel_connection_id : null,
                (string) ($conversation->channel ?: 'manual'),
                $conversation->external_id,
            );
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function channelConnection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'channel_connection_id');
    }

    public function assignedTeam(): BelongsTo
    {
        return $this->belongsTo(CommunicationTeam::class, 'assigned_team_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('id');
    }

    public function userStates(): HasMany
    {
        return $this->hasMany(ConversationUserState::class);
    }

    public function channelIdentities(): HasMany
    {
        return $this->hasMany(CustomerChannelIdentity::class);
    }
}
