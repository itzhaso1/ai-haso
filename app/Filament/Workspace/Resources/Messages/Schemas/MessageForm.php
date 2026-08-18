<?php

namespace App\Filament\Workspace\Resources\Messages\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('conversation_id')
                    ->relationship('conversation', 'id')
                    ->required(),
                Select::make('customer_id')
                    ->relationship('customer', 'name'),
                Select::make('user_id')
                    ->relationship('user', 'name'),
                TextInput::make('direction')
                    ->required()
                    ->default('inbound'),
                TextInput::make('message_type')
                    ->required()
                    ->default('text'),
                Textarea::make('content')
                    ->columnSpanFull(),
                TextInput::make('external_message_id'),
                Toggle::make('ai_generated')
                    ->required(),
                Textarea::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
