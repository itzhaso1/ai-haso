<?php

namespace App\Filament\Platform\Resources\Workspaces\Schemas;

use App\Models\Workspace;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class WorkspaceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('uuid')
                    ->label('UUID'),
                TextEntry::make('name'),
                TextEntry::make('slug'),
                TextEntry::make('type'),
                TextEntry::make('owner_user_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('status'),
                TextEntry::make('settings')
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
                    ->visible(fn (Workspace $record): bool => $record->trashed()),
            ]);
    }
}
