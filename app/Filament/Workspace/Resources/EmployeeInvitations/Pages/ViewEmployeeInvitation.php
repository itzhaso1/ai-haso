<?php

namespace App\Filament\Workspace\Resources\EmployeeInvitations\Pages;

use App\Filament\Workspace\Resources\EmployeeInvitations\EmployeeInvitationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeInvitation extends ViewRecord
{
    protected static string $resource = EmployeeInvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
