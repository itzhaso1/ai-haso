<?php

namespace App\Filament\Workspace\Resources\Products\Pages;

use App\Filament\Workspace\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
}
