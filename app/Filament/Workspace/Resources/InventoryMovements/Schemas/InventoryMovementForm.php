<?php

namespace App\Filament\Workspace\Resources\InventoryMovements\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class InventoryMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->relationship('product', 'name')
                    ->required(),
                Select::make('product_variant_id')
                    ->relationship('variant', 'name'),
                Select::make('type')
                    ->required()
                    ->options([
                        'add' => 'Add',
                        'remove' => 'Remove',
                        'reserve' => 'Reserve',
                        'release' => 'Release',
                        'adjustment' => 'Manual Adjustment',
                        'return' => 'Return',
                    ]),
                TextInput::make('quantity')
                    ->required()
                    ->numeric(),
                TextInput::make('reference_type'),
                TextInput::make('reference_id')
                    ->numeric(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
