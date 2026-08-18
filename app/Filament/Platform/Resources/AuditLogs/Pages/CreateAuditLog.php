<?php

namespace App\Filament\Platform\Resources\AuditLogs\Pages;

use App\Filament\Platform\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAuditLog extends CreateRecord
{
    protected static string $resource = AuditLogResource::class;
}
