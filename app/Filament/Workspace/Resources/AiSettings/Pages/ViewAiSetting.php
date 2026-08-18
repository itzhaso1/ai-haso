<?php

namespace App\Filament\Workspace\Resources\AiSettings\Pages;

use App\Filament\Workspace\Resources\AiSettings\AiSettingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAiSetting extends ViewRecord
{
    protected static string $resource = AiSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
