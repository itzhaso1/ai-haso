<?php

namespace App\Filament\Workspace\Resources\PaymentGateways\Pages;

use App\Filament\Workspace\Resources\PaymentGateways\PaymentGatewayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaymentGateways extends ListRecords
{
    protected static string $resource = PaymentGatewayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
