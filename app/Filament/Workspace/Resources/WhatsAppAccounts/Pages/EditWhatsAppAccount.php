<?php

namespace App\Filament\Workspace\Resources\WhatsAppAccounts\Pages;

use App\Filament\Workspace\Resources\WhatsAppAccounts\WhatsAppAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWhatsAppAccount extends EditRecord
{
    protected static string $resource = WhatsAppAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
