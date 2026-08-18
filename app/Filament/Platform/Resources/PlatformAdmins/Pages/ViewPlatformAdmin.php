<?php

namespace App\Filament\Platform\Resources\PlatformAdmins\Pages;

use App\Filament\Platform\Resources\PlatformAdmins\PlatformAdminResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPlatformAdmin extends ViewRecord
{
    protected static string $resource = PlatformAdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
