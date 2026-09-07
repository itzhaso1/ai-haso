<?php

namespace App\Models\Communication;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Conversation;
use App\Models\EmailAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChannelConnection extends WorkspaceScopedModel
{
    use BelongsToWorkspace, SoftDeletes;

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_ERROR = 'error';

    public const STATUS_COMING_SOON = 'coming_soon';

    public const SOURCE_WHATSAPP_PHONE = 'whats_app_phone_number';

    public const SOURCE_EMAIL_ACCOUNT = 'email_account';

    protected $fillable = [
        'workspace_id',
        'channel',
        'display_name',
        'status',
        'provider',
        'external_account_id',
        'credentials_ref',
        'capabilities',
        'source_type',
        'source_id',
        'connected_at',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'metadata' => 'array',
            'connected_at' => 'datetime',
        ];
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'channel_connection_id');
    }

    public function whatsAppPhoneNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsAppPhoneNumber::class, 'source_id');
    }

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class, 'source_id');
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }
}
