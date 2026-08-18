<?php

namespace App\Filament\Platform\Resources\Users\Pages;

use App\Filament\Platform\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
