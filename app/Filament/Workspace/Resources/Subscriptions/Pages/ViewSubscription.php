<?php

namespace App\Filament\Workspace\Resources\Subscriptions\Pages;

use App\Filament\Workspace\Resources\Subscriptions\SubscriptionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSubscription extends ViewRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
