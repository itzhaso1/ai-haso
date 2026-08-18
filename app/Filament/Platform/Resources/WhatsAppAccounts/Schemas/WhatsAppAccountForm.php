<?php

namespace App\Filament\Platform\Resources\WhatsAppAccounts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class WhatsAppAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('workspace_id')
                    ->relationship('workspace', 'name')
                    ->required(),
                TextInput::make('business_account_id')
                    ->required(),
                TextInput::make('app_id'),
                TextInput::make('display_name'),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                Textarea::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
