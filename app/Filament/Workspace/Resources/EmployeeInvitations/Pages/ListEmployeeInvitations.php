<?php

namespace App\Filament\Workspace\Resources\EmployeeInvitations\Pages;

use App\Filament\Workspace\Resources\EmployeeInvitations\EmployeeInvitationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeInvitations extends ListRecords
{
    protected static string $resource = EmployeeInvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
