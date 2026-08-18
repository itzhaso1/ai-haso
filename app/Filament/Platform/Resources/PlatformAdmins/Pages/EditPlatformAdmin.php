<?php

namespace App\Filament\Platform\Resources\PlatformAdmins\Pages;

use App\Filament\Platform\Resources\PlatformAdmins\PlatformAdminResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPlatformAdmin extends EditRecord
{
    protected static string $resource = PlatformAdminResource::class;

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
