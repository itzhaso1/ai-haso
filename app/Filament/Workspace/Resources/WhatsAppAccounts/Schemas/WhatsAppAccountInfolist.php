<?php

namespace App\Filament\Workspace\Resources\WhatsAppAccounts\Schemas;

use App\Models\WhatsAppAccount;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class WhatsAppAccountInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('workspace.name')
                    ->label('Workspace'),
                TextEntry::make('business_account_id'),
                TextEntry::make('app_id')
                    ->placeholder('-'),
                TextEntry::make('display_name')
                    ->placeholder('-'),
                TextEntry::make('status'),
                TextEntry::make('metadata')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (WhatsAppAccount $record): bool => $record->trashed()),
            ]);
    }
}
