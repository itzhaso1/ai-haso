<?php

namespace App\Filament\Workspace\Resources\Payments\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_id')
                    ->relationship('order', 'order_number')
                    ->searchable()
                    ->required(),
                Select::make('payment_gateway_id')
                    ->relationship('gateway', 'provider')
                    ->searchable(),
                TextInput::make('status')->disabled(),
                TextInput::make('amount')->disabled(),
                TextInput::make('currency')->disabled(),
                TextInput::make('payment_link')->disabled(),
                DateTimePicker::make('paid_at'),
                DateTimePicker::make('failed_at'),
                TextInput::make('failure_reason')->disabled(),
            ]);
    }
}
