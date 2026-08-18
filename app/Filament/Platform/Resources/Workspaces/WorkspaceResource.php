<?php

namespace App\Filament\Platform\Resources\Workspaces;

use App\Filament\Platform\Resources\Workspaces\Pages\CreateWorkspace;
use App\Filament\Platform\Resources\Workspaces\Pages\EditWorkspace;
use App\Filament\Platform\Resources\Workspaces\Pages\ListWorkspaces;
use App\Filament\Platform\Resources\Workspaces\Pages\ViewWorkspace;
use App\Filament\Platform\Resources\Workspaces\Schemas\WorkspaceForm;
use App\Filament\Platform\Resources\Workspaces\Schemas\WorkspaceInfolist;
use App\Filament\Platform\Resources\Workspaces\Tables\WorkspacesTable;
use App\Models\Workspace;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WorkspaceResource extends Resource
{
    protected static ?string $model = Workspace::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return WorkspaceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkspaceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkspacesTable::configure($table);
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
            'index' => ListWorkspaces::route('/'),
            'create' => CreateWorkspace::route('/create'),
            'view' => ViewWorkspace::route('/{record}'),
            'edit' => EditWorkspace::route('/{record}/edit'),
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
