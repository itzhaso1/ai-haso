<?php

namespace App\Filament\Workspace\Resources\Conversations\Pages;

use App\Filament\Workspace\Resources\Conversations\ConversationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewConversation extends ViewRecord
{
    protected static string $resource = ConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
