<?php

namespace App\Filament\Workspace\Resources\InventoryMovements;

use App\Filament\Workspace\Concerns\ResolvesCurrentWorkspace;
use App\Filament\Workspace\Resources\InventoryMovements\Pages\CreateInventoryMovement;
use App\Filament\Workspace\Resources\InventoryMovements\Pages\EditInventoryMovement;
use App\Filament\Workspace\Resources\InventoryMovements\Pages\ListInventoryMovements;
use App\Filament\Workspace\Resources\InventoryMovements\Pages\ViewInventoryMovement;
use App\Filament\Workspace\Resources\InventoryMovements\Schemas\InventoryMovementForm;
use App\Filament\Workspace\Resources\InventoryMovements\Schemas\InventoryMovementInfolist;
use App\Filament\Workspace\Resources\InventoryMovements\Tables\InventoryMovementsTable;
use App\Models\InventoryMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InventoryMovementResource extends Resource
{
    use ResolvesCurrentWorkspace;

    protected static ?string $model = InventoryMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return InventoryMovementForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InventoryMovementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryMovementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryMovements::route('/'),
            'create' => CreateInventoryMovement::route('/create'),
            'view' => ViewInventoryMovement::route('/{record}'),
            'edit' => EditInventoryMovement::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::isCommercialWorkspace();
    }

    public static function canViewAny(): bool
    {
        return static::isCommercialWorkspace();
    }
}
