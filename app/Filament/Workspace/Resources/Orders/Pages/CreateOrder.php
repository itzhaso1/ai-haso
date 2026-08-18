<?php

namespace App\Filament\Workspace\Resources\Orders\Pages;

use App\Filament\Workspace\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\Order\OrderService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var OrderService $service */
        $service = app(OrderService::class);
        $order = $service->create($data, auth()->user());

        Notification::make()
            ->title('تم إنشاء الطلب بنجاح')
            ->success()
            ->send();

        return $order;
    }
}
