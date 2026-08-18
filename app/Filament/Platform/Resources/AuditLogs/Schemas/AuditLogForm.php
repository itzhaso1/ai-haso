<?php

namespace App\Filament\Platform\Resources\AuditLogs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AuditLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('workspace_id')
                    ->relationship('workspace', 'name'),
                Select::make('user_id')
                    ->relationship('user', 'name'),
                TextInput::make('action')
                    ->required(),
                TextInput::make('entity_type')
                    ->required(),
                TextInput::make('entity_id')
                    ->numeric(),
                Textarea::make('old_values')
                    ->columnSpanFull(),
                Textarea::make('new_values')
                    ->columnSpanFull(),
                Textarea::make('meta')
                    ->columnSpanFull(),
                TextInput::make('ip_address'),
                Textarea::make('user_agent')
                    ->columnSpanFull(),
                DateTimePicker::make('occurred_at')
                    ->required(),
            ]);
    }
}
