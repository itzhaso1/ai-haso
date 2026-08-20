<?php

namespace App\Models;

use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'name',
    'slug',
    'type',
    'owner_user_id',
    'status',
    'settings',
])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_users')
            ->withPivot(['membership_role', 'status', 'is_primary', 'joined_at', 'invited_by'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceUser::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscriptionCheckoutSessions(): HasMany
    {
        return $this->hasMany(SubscriptionCheckoutSession::class);
    }

    public function featureFlags(): HasMany
    {
        return $this->hasMany(WorkspaceFeatureFlag::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function paymentGateways(): HasMany
    {
        return $this->hasMany(PaymentGateway::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function aiSetting(): HasOne
    {
        return $this->hasOne(AiSetting::class);
    }

    public function whatsappAccounts(): HasMany
    {
        return $this->hasMany(WhatsAppAccount::class);
    }
}
