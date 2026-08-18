<?php

namespace App\Filament\Workspace\Resources\Orders\Pages;

use App\Filament\Workspace\Resources\Orders\OrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
