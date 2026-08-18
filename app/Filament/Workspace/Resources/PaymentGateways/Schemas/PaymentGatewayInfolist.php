<?php

namespace App\Filament\Workspace\Resources\PaymentGateways\Schemas;

use App\Models\PaymentGateway;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PaymentGatewayInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('workspace.name')
                    ->label('Workspace'),
                TextEntry::make('provider'),
                TextEntry::make('status'),
                TextEntry::make('config')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('last_verified_at')
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
                    ->visible(fn (PaymentGateway $record): bool => $record->trashed()),
            ]);
    }
}
