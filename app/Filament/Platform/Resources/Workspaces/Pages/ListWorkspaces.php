<?php

namespace App\Filament\Platform\Resources\Workspaces\Pages;

use App\Filament\Platform\Resources\Workspaces\WorkspaceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkspaces extends ListRecords
{
    protected static string $resource = WorkspaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
