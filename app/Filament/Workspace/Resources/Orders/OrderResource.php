<?php

namespace App\Filament\Workspace\Resources\Orders;

use App\Filament\Workspace\Concerns\ResolvesCurrentWorkspace;
use App\Filament\Workspace\Resources\Orders\Pages\CreateOrder;
use App\Filament\Workspace\Resources\Orders\Pages\EditOrder;
use App\Filament\Workspace\Resources\Orders\Pages\ListOrders;
use App\Filament\Workspace\Resources\Orders\Pages\ViewOrder;
use App\Filament\Workspace\Resources\Orders\Schemas\OrderForm;
use App\Filament\Workspace\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Workspace\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrderResource extends Resource
{
    use ResolvesCurrentWorkspace;

    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
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
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'view' => ViewOrder::route('/{record}'),
            'edit' => EditOrder::route('/{record}/edit'),
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

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
