<?php

namespace App\Filament\Workspace\Resources\PaymentGateways\Pages;

use App\Filament\Workspace\Resources\PaymentGateways\PaymentGatewayResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentGateway extends CreateRecord
{
    protected static string $resource = PaymentGatewayResource::class;
}
