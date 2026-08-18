<?php

namespace App\Filament\Workspace\Resources\Customers\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->required(),
                TextInput::make('whatsapp'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('orders_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_purchases')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('last_order_at'),
                DateTimePicker::make('last_conversation_at'),
                Textarea::make('notes')
                    ->columnSpanFull(),
                Textarea::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
