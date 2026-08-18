<?php

namespace App\Filament\Platform\Resources\Orders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('workspace_id')
                    ->relationship('workspace', 'name')
                    ->required(),
                Select::make('customer_id')
                    ->relationship('customer', 'name'),
                TextInput::make('order_number')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('draft'),
                TextInput::make('payment_status')
                    ->required()
                    ->default('pending'),
                TextInput::make('fulfillment_status')
                    ->required()
                    ->default('unfulfilled'),
                TextInput::make('shipping_status')
                    ->required()
                    ->default('not_shipped'),
                TextInput::make('currency')
                    ->required()
                    ->default('USD'),
                TextInput::make('subtotal')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('discount_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('shipping_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('payment_link'),
                Textarea::make('notes')
                    ->columnSpanFull(),
                DateTimePicker::make('placed_at'),
                DateTimePicker::make('cancelled_at'),
            ]);
    }
}
