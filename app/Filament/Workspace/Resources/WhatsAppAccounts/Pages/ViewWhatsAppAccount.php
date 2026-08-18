<?php

namespace App\Filament\Workspace\Resources\WhatsAppAccounts\Pages;

use App\Filament\Workspace\Resources\WhatsAppAccounts\WhatsAppAccountResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWhatsAppAccount extends ViewRecord
{
    protected static string $resource = WhatsAppAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
