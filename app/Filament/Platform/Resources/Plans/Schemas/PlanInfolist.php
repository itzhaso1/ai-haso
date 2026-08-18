<?php

namespace App\Filament\Platform\Resources\Plans\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PlanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('name'),
                TextEntry::make('workspace_type')
                    ->placeholder('-'),
                TextEntry::make('billing_period'),
                TextEntry::make('currency'),
                TextEntry::make('price')
                    ->money(),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('features')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('limits')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
