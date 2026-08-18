<?php

namespace App\Filament\Platform\Resources\Workspaces\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class WorkspaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('uuid')
                    ->label('UUID')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('type')
                    ->required(),
                TextInput::make('owner_user_id')
                    ->numeric(),
                TextInput::make('status')
                    ->required()
                    ->default('active'),
                Textarea::make('settings')
                    ->columnSpanFull(),
            ]);
    }
}
