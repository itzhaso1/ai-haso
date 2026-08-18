<?php

namespace App\Filament\Workspace\Resources\InventoryMovements\Pages;

use App\Filament\Workspace\Resources\InventoryMovements\InventoryMovementResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditInventoryMovement extends EditRecord
{
    protected static string $resource = InventoryMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
