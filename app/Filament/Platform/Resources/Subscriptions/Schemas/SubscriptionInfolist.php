<?php

namespace App\Filament\Platform\Resources\Subscriptions\Schemas;

use App\Models\Subscription;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SubscriptionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('workspace.name')
                    ->label('Workspace'),
                TextEntry::make('plan.name')
                    ->label('Plan'),
                TextEntry::make('status'),
                TextEntry::make('starts_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('trial_ends_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('current_period_start')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('current_period_end')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('ends_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('cancelled_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('provider')
                    ->placeholder('-'),
                TextEntry::make('provider_customer_id')
                    ->placeholder('-'),
                TextEntry::make('provider_subscription_id')
                    ->placeholder('-'),
                TextEntry::make('metadata')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Subscription $record): bool => $record->trashed()),
            ]);
    }
}
