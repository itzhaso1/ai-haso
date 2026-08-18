<?php

namespace App\Filament\Workspace\Resources\PaymentGateways\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PaymentGatewayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('provider')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('disconnected'),
                Textarea::make('config')
                    ->columnSpanFull(),
                DateTimePicker::make('last_verified_at'),
            ]);
    }
}
