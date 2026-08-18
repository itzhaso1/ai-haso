<?php

namespace App\Filament\Workspace\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('workspace.name')
                    ->label('Workspace'),
                TextEntry::make('customer.name')
                    ->label('Customer')
                    ->placeholder('-'),
                TextEntry::make('order_number'),
                TextEntry::make('status'),
                TextEntry::make('payment_status'),
                TextEntry::make('fulfillment_status'),
                TextEntry::make('shipping_status'),
                TextEntry::make('currency'),
                TextEntry::make('subtotal')
                    ->numeric(),
                TextEntry::make('discount_amount')
                    ->numeric(),
                TextEntry::make('shipping_amount')
                    ->numeric(),
                TextEntry::make('total_amount')
                    ->numeric(),
                TextEntry::make('payment_link')
                    ->placeholder('-'),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('placed_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('cancelled_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Order $record): bool => $record->trashed()),
            ]);
    }
}
