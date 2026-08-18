<?php

namespace App\Filament\Workspace\Resources\Conversations\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ConversationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->relationship('customer', 'name'),
                TextInput::make('channel')
                    ->required()
                    ->default('manual'),
                TextInput::make('external_id'),
                TextInput::make('status')
                    ->required()
                    ->default('open'),
                Toggle::make('ai_enabled')
                    ->required(),
                DateTimePicker::make('last_message_at'),
                Textarea::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
