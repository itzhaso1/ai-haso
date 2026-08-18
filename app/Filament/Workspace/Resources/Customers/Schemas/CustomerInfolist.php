<?php

namespace App\Filament\Workspace\Resources\Customers\Schemas;

use App\Models\Customer;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('workspace.name')
                    ->label('Workspace'),
                TextEntry::make('name'),
                TextEntry::make('phone'),
                TextEntry::make('whatsapp')
                    ->placeholder('-'),
                TextEntry::make('email')
                    ->label('Email address')
                    ->placeholder('-'),
                TextEntry::make('orders_count')
                    ->numeric(),
                TextEntry::make('total_purchases')
                    ->numeric(),
                TextEntry::make('last_order_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('last_conversation_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
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
                    ->visible(fn (Customer $record): bool => $record->trashed()),
            ]);
    }
}
