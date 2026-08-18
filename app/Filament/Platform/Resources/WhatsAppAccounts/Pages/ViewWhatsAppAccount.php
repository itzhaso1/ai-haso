<?php

namespace App\Filament\Platform\Resources\WhatsAppAccounts\Pages;

use App\Filament\Platform\Resources\WhatsAppAccounts\WhatsAppAccountResource;
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
