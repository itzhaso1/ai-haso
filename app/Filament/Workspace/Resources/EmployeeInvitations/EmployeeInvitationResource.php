<?php

namespace App\Filament\Workspace\Resources\EmployeeInvitations;

use App\Filament\Workspace\Concerns\ResolvesCurrentWorkspace;
use App\Filament\Workspace\Resources\EmployeeInvitations\Pages\CreateEmployeeInvitation;
use App\Filament\Workspace\Resources\EmployeeInvitations\Pages\EditEmployeeInvitation;
use App\Filament\Workspace\Resources\EmployeeInvitations\Pages\ListEmployeeInvitations;
use App\Filament\Workspace\Resources\EmployeeInvitations\Pages\ViewEmployeeInvitation;
use App\Filament\Workspace\Resources\EmployeeInvitations\Schemas\EmployeeInvitationForm;
use App\Filament\Workspace\Resources\EmployeeInvitations\Schemas\EmployeeInvitationInfolist;
use App\Filament\Workspace\Resources\EmployeeInvitations\Tables\EmployeeInvitationsTable;
use App\Models\EmployeeInvitation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EmployeeInvitationResource extends Resource
{
    use ResolvesCurrentWorkspace;

    protected static ?string $model = EmployeeInvitation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return EmployeeInvitationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeInvitationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeInvitationsTable::configure($table);
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
            'index' => ListEmployeeInvitations::route('/'),
            'create' => CreateEmployeeInvitation::route('/create'),
            'view' => ViewEmployeeInvitation::route('/{record}'),
            'edit' => EditEmployeeInvitation::route('/{record}/edit'),
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
