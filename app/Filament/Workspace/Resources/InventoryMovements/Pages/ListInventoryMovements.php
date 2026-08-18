<?php

namespace App\Filament\Workspace\Resources\InventoryMovements\Pages;

use App\Filament\Workspace\Resources\InventoryMovements\InventoryMovementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryMovements extends ListRecords
{
    protected static string $resource = InventoryMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
