<?php

namespace App\Filament\Workspace\Resources\Customers;

use App\Filament\Workspace\Concerns\ResolvesCurrentWorkspace;
use App\Filament\Workspace\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Workspace\Resources\Customers\Pages\EditCustomer;
use App\Filament\Workspace\Resources\Customers\Pages\ListCustomers;
use App\Filament\Workspace\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Workspace\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Workspace\Resources\Customers\Schemas\CustomerInfolist;
use App\Filament\Workspace\Resources\Customers\Tables\CustomersTable;
use App\Models\Customer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerResource extends Resource
{
    use ResolvesCurrentWorkspace;

    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
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
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'view' => ViewCustomer::route('/{record}'),
            'edit' => EditCustomer::route('/{record}/edit'),
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
