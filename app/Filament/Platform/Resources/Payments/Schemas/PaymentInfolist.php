<?php

namespace App\Filament\Platform\Resources\Payments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('workspace.name')
                    ->label('Workspace'),
                TextEntry::make('order.id')
                    ->label('Order'),
                TextEntry::make('payment_gateway_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('provider'),
                TextEntry::make('provider_payment_id')
                    ->placeholder('-'),
                TextEntry::make('idempotency_key')
                    ->placeholder('-'),
                TextEntry::make('status'),
                TextEntry::make('amount')
                    ->numeric(),
                TextEntry::make('currency'),
                TextEntry::make('payment_link')
                    ->placeholder('-'),
                TextEntry::make('provider_payload')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('paid_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('failed_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('failure_reason')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
