<?php

namespace App\Filament\Workspace\Resources\AiSettings\Pages;

use App\Filament\Workspace\Resources\AiSettings\AiSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAiSetting extends EditRecord
{
    protected static string $resource = AiSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
