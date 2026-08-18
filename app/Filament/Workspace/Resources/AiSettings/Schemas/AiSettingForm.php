<?php

namespace App\Filament\Workspace\Resources\AiSettings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AiSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->default('AI Assistant'),
                Textarea::make('instructions')
                    ->columnSpanFull(),
                TextInput::make('tone'),
                TextInput::make('reply_style'),
                Textarea::make('rules')
                    ->columnSpanFull(),
                Textarea::make('business_information')
                    ->columnSpanFull(),
                TextInput::make('provider')
                    ->required()
                    ->default('openai'),
                TextInput::make('model')
                    ->required()
                    ->default('gpt-4o-mini'),
                TextInput::make('max_tokens')
                    ->required()
                    ->numeric()
                    ->default(512),
                TextInput::make('temperature')
                    ->required()
                    ->numeric()
                    ->default(0.4),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
