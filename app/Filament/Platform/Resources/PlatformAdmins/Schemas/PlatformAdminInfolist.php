<?php

namespace App\Filament\Platform\Resources\PlatformAdmins\Schemas;

use App\Models\PlatformAdmin;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PlatformAdminInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('email_verified_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('two_factor_secret')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('last_login_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (PlatformAdmin $record): bool => $record->trashed()),
            ]);
    }
}
