<?php

namespace App\Filament\Workspace\Resources\EmployeeInvitations\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class EmployeeInvitationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('workspace.name')
                    ->label('Workspace'),
                TextEntry::make('invited_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('role'),
                TextEntry::make('status'),
                TextEntry::make('token'),
                TextEntry::make('expires_at')
                    ->dateTime(),
                TextEntry::make('accepted_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
