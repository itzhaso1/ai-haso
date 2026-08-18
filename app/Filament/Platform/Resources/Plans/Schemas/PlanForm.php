<?php

namespace App\Filament\Platform\Resources\Plans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('workspace_type'),
                TextInput::make('billing_period')
                    ->required()
                    ->default('monthly'),
                TextInput::make('currency')
                    ->required()
                    ->default('USD'),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                Toggle::make('is_active')
                    ->required(),
                Textarea::make('features')
                    ->columnSpanFull(),
                Textarea::make('limits')
                    ->columnSpanFull(),
            ]);
    }
}
