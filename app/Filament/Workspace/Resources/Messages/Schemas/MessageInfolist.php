<?php

namespace App\Filament\Workspace\Resources\Messages\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MessageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('workspace.name')
                    ->label('Workspace'),
                TextEntry::make('conversation.id')
                    ->label('Conversation'),
                TextEntry::make('customer.name')
                    ->label('Customer')
                    ->placeholder('-'),
                TextEntry::make('user.name')
                    ->label('User')
                    ->placeholder('-'),
                TextEntry::make('direction'),
                TextEntry::make('message_type'),
                TextEntry::make('content')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('external_message_id')
                    ->placeholder('-'),
                IconEntry::make('ai_generated')
                    ->boolean(),
                TextEntry::make('metadata')
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
