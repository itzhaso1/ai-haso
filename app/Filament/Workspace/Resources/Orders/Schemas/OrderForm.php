<?php

namespace App\Filament\Workspace\Resources\Orders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
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
                Select::make('customer_id')
                    ->relationship('customer', 'name'),
                Select::make('status')
                    ->required()
                    ->options([
                        'draft' => 'Draft',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                    ])
                    ->default('confirmed'),
                Select::make('payment_status')
                    ->required()
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ])
                    ->default('pending'),
                Select::make('fulfillment_status')
                    ->required()
                    ->options([
                        'unfulfilled' => 'Unfulfilled',
                        'processing' => 'Processing',
                        'fulfilled' => 'Fulfilled',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('unfulfilled'),
                Select::make('shipping_status')
                    ->required()
                    ->options([
                        'not_shipped' => 'Not Shipped',
                        'processing' => 'Processing',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'returned' => 'Returned',
                    ])
                    ->default('not_shipped'),
                TextInput::make('currency')
                    ->required()
                    ->default('USD')
                    ->maxLength(3),
                TextInput::make('discount_amount')
                    ->numeric()
                    ->default(0),
                TextInput::make('shipping_amount')
                    ->numeric()
                    ->default(0),
                Repeater::make('items')
                    ->schema([
                        Select::make('product_id')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable(),
                        Select::make('product_variant_id')
                            ->relationship('variant', 'name')
                            ->searchable(),
                        TextInput::make('quantity')
                            ->required()
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('unit_price')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('discount_amount')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columnSpanFull()
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
                DateTimePicker::make('placed_at'),
                DateTimePicker::make('cancelled_at'),
            ]);
    }
}
