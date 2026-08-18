<?php

namespace App\Filament\Workspace\Resources\PaymentGateways\Pages;

use App\Filament\Workspace\Resources\PaymentGateways\PaymentGatewayResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPaymentGateway extends ViewRecord
{
    protected static string $resource = PaymentGatewayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
