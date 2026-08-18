<?php

namespace App\Filament\Workspace\Resources\Payments\Pages;

use App\Filament\Workspace\Resources\Payments\PaymentResource;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Services\Payment\PaymentService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $order = Order::query()->findOrFail((int) $data['order_id']);
        $gateway = ! empty($data['payment_gateway_id'])
            ? PaymentGateway::query()->findOrFail((int) $data['payment_gateway_id'])
            : null;

        $payment = app(PaymentService::class)->createPaymentLink($order, $gateway);

        Notification::make()
            ->title('تم إنشاء رابط الدفع')
            ->success()
            ->send();

        return $payment;
    }
}
