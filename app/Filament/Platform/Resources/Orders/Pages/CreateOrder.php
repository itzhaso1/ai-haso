<?php

namespace App\Filament\Platform\Resources\Orders\Pages;

use App\Filament\Platform\Resources\Orders\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;
}
