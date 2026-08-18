<?php

namespace App\Filament\Workspace\Resources\PaymentGateways;

use App\Filament\Workspace\Concerns\ResolvesCurrentWorkspace;
use App\Filament\Workspace\Resources\PaymentGateways\Pages\CreatePaymentGateway;
use App\Filament\Workspace\Resources\PaymentGateways\Pages\EditPaymentGateway;
use App\Filament\Workspace\Resources\PaymentGateways\Pages\ListPaymentGateways;
use App\Filament\Workspace\Resources\PaymentGateways\Pages\ViewPaymentGateway;
use App\Filament\Workspace\Resources\PaymentGateways\Schemas\PaymentGatewayForm;
use App\Filament\Workspace\Resources\PaymentGateways\Schemas\PaymentGatewayInfolist;
use App\Filament\Workspace\Resources\PaymentGateways\Tables\PaymentGatewaysTable;
use App\Models\PaymentGateway;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentGatewayResource extends Resource
{
    use ResolvesCurrentWorkspace;

    protected static ?string $model = PaymentGateway::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PaymentGatewayForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaymentGatewayInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentGatewaysTable::configure($table);
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
            'index' => ListPaymentGateways::route('/'),
            'create' => CreatePaymentGateway::route('/create'),
            'view' => ViewPaymentGateway::route('/{record}'),
            'edit' => EditPaymentGateway::route('/{record}/edit'),
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
