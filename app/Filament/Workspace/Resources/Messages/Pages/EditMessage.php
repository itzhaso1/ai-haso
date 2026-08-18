<?php

namespace App\Filament\Workspace\Resources\Messages\Pages;

use App\Filament\Workspace\Resources\Messages\MessageResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMessage extends EditRecord
{
    protected static string $resource = MessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
