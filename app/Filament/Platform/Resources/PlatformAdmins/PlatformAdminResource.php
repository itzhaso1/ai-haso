<?php

namespace App\Filament\Platform\Resources\PlatformAdmins;

use App\Filament\Platform\Resources\PlatformAdmins\Pages\CreatePlatformAdmin;
use App\Filament\Platform\Resources\PlatformAdmins\Pages\EditPlatformAdmin;
use App\Filament\Platform\Resources\PlatformAdmins\Pages\ListPlatformAdmins;
use App\Filament\Platform\Resources\PlatformAdmins\Pages\ViewPlatformAdmin;
use App\Filament\Platform\Resources\PlatformAdmins\Schemas\PlatformAdminForm;
use App\Filament\Platform\Resources\PlatformAdmins\Schemas\PlatformAdminInfolist;
use App\Filament\Platform\Resources\PlatformAdmins\Tables\PlatformAdminsTable;
use App\Models\PlatformAdmin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PlatformAdminResource extends Resource
{
    protected static ?string $model = PlatformAdmin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PlatformAdminForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PlatformAdminInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlatformAdminsTable::configure($table);
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
            'index' => ListPlatformAdmins::route('/'),
            'create' => CreatePlatformAdmin::route('/create'),
            'view' => ViewPlatformAdmin::route('/{record}'),
            'edit' => EditPlatformAdmin::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
