<?php

namespace App\Filament\Workspace\Resources\AiSettings\Pages;

use App\Filament\Workspace\Resources\AiSettings\AiSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAiSettings extends ListRecords
{
    protected static string $resource = AiSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
