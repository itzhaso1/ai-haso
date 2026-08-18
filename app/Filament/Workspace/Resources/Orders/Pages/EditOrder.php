<?php

namespace App\Filament\Workspace\Resources\Orders\Pages;

use App\Filament\Workspace\Resources\Orders\OrderResource;
use App\Services\Order\OrderService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (($data['status'] ?? null) === 'cancelled') {
            $record = app(OrderService::class)->cancel($record, auth()->user());
        } else {
            $record->update($data);
        }

        Notification::make()
            ->title('تم تحديث الطلب')
            ->success()
            ->send();

        return $record->refresh();
    }
}
