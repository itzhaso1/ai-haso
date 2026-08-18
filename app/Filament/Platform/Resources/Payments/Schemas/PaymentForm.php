<?php

namespace App\Filament\Platform\Resources\Payments\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('workspace_id')
                    ->relationship('workspace', 'name')
                    ->required(),
                Select::make('order_id')
                    ->relationship('order', 'id')
                    ->required(),
                TextInput::make('payment_gateway_id')
                    ->numeric(),
                TextInput::make('provider')
                    ->required(),
                TextInput::make('provider_payment_id'),
                TextInput::make('idempotency_key'),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('currency')
                    ->required()
                    ->default('USD'),
                TextInput::make('payment_link'),
                Textarea::make('provider_payload')
                    ->columnSpanFull(),
                DateTimePicker::make('paid_at'),
                DateTimePicker::make('failed_at'),
                TextInput::make('failure_reason'),
            ]);
    }
}
