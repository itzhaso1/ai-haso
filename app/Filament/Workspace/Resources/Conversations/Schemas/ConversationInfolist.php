<?php

namespace App\Filament\Workspace\Resources\Conversations\Schemas;

use App\Models\Conversation;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ConversationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('workspace.name')
                    ->label('Workspace'),
                TextEntry::make('customer.name')
                    ->label('Customer')
                    ->placeholder('-'),
                TextEntry::make('channel'),
                TextEntry::make('external_id')
                    ->placeholder('-'),
                TextEntry::make('status'),
                IconEntry::make('ai_enabled')
                    ->boolean(),
                TextEntry::make('last_message_at')
                    ->dateTime()
                    ->placeholder('-'),
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
                    ->visible(fn (Conversation $record): bool => $record->trashed()),
            ]);
    }
}
