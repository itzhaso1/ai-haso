<?php

namespace App\Filament\Workspace\Resources\InventoryMovements\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class InventoryMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('workspace.name')
                    ->label('Workspace'),
                TextEntry::make('product.name')
                    ->label('Product'),
                TextEntry::make('product_variant_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('type'),
                TextEntry::make('quantity')
                    ->numeric(),
                TextEntry::make('before_quantity')
                    ->numeric(),
                TextEntry::make('after_quantity')
                    ->numeric(),
                TextEntry::make('reference_type')
                    ->placeholder('-'),
                TextEntry::make('reference_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('user.name')
                    ->label('User')
                    ->placeholder('-'),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
