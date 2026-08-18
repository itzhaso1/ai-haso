<?php

namespace App\Filament\Workspace\Resources\Customers\Pages;

use App\Filament\Workspace\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;
}
