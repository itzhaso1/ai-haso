<?php

namespace App\Filament\Platform\Resources\Subscriptions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('workspace_id')
                    ->relationship('workspace', 'name')
                    ->required(),
                Select::make('plan_id')
                    ->relationship('plan', 'name')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('trialing'),
                DateTimePicker::make('starts_at'),
                DateTimePicker::make('trial_ends_at'),
                DateTimePicker::make('current_period_start'),
                DateTimePicker::make('current_period_end'),
                DateTimePicker::make('ends_at'),
                DateTimePicker::make('cancelled_at'),
                TextInput::make('provider'),
                TextInput::make('provider_customer_id'),
                TextInput::make('provider_subscription_id'),
                Textarea::make('metadata')
                    ->columnSpanFull(),
            ]);
    }
}
