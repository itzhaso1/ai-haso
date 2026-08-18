<?php

namespace App\Filament\Workspace\Resources\AiSettings;

use App\Filament\Workspace\Resources\AiSettings\Pages\CreateAiSetting;
use App\Filament\Workspace\Resources\AiSettings\Pages\EditAiSetting;
use App\Filament\Workspace\Resources\AiSettings\Pages\ListAiSettings;
use App\Filament\Workspace\Resources\AiSettings\Pages\ViewAiSetting;
use App\Filament\Workspace\Resources\AiSettings\Schemas\AiSettingForm;
use App\Filament\Workspace\Resources\AiSettings\Schemas\AiSettingInfolist;
use App\Filament\Workspace\Resources\AiSettings\Tables\AiSettingsTable;
use App\Models\AiSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AiSettingResource extends Resource
{
    protected static ?string $model = AiSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return AiSettingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AiSettingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AiSettingsTable::configure($table);
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
            'index' => ListAiSettings::route('/'),
            'create' => CreateAiSetting::route('/create'),
            'view' => ViewAiSetting::route('/{record}'),
            'edit' => EditAiSetting::route('/{record}/edit'),
        ];
    }
}
