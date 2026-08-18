<?php

namespace App\Filament\Platform\Resources\Payments\Pages;

use App\Filament\Platform\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;
}
