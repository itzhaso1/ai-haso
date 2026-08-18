<?php

namespace App\Filament\Workspace\Resources\AiSettings\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AiSettingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('workspace.name')
                    ->label('Workspace'),
                TextEntry::make('name'),
                TextEntry::make('instructions')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('tone')
                    ->placeholder('-'),
                TextEntry::make('reply_style')
                    ->placeholder('-'),
                TextEntry::make('rules')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('business_information')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('provider'),
                TextEntry::make('model'),
                TextEntry::make('max_tokens')
                    ->numeric(),
                TextEntry::make('temperature')
                    ->numeric(),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
