<?php

namespace App\Filament\Workspace\Resources\WhatsAppAccounts;

use App\Filament\Workspace\Concerns\ResolvesCurrentWorkspace;
use App\Filament\Workspace\Resources\WhatsAppAccounts\Pages\CreateWhatsAppAccount;
use App\Filament\Workspace\Resources\WhatsAppAccounts\Pages\EditWhatsAppAccount;
use App\Filament\Workspace\Resources\WhatsAppAccounts\Pages\ListWhatsAppAccounts;
use App\Filament\Workspace\Resources\WhatsAppAccounts\Pages\ViewWhatsAppAccount;
use App\Filament\Workspace\Resources\WhatsAppAccounts\Schemas\WhatsAppAccountForm;
use App\Filament\Workspace\Resources\WhatsAppAccounts\Schemas\WhatsAppAccountInfolist;
use App\Filament\Workspace\Resources\WhatsAppAccounts\Tables\WhatsAppAccountsTable;
use App\Models\WhatsAppAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WhatsAppAccountResource extends Resource
{
    use ResolvesCurrentWorkspace;

    protected static ?string $model = WhatsAppAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return WhatsAppAccountForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WhatsAppAccountInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsAppAccountsTable::configure($table);
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
            'index' => ListWhatsAppAccounts::route('/'),
            'create' => CreateWhatsAppAccount::route('/create'),
            'view' => ViewWhatsAppAccount::route('/{record}'),
            'edit' => EditWhatsAppAccount::route('/{record}/edit'),
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
