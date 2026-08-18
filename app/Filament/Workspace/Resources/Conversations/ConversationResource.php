<?php

namespace App\Filament\Workspace\Resources\Conversations;

use App\Filament\Workspace\Resources\Conversations\Pages\CreateConversation;
use App\Filament\Workspace\Resources\Conversations\Pages\EditConversation;
use App\Filament\Workspace\Resources\Conversations\Pages\ListConversations;
use App\Filament\Workspace\Resources\Conversations\Pages\ViewConversation;
use App\Filament\Workspace\Resources\Conversations\RelationManagers\MessagesRelationManager;
use App\Filament\Workspace\Resources\Conversations\Schemas\ConversationForm;
use App\Filament\Workspace\Resources\Conversations\Schemas\ConversationInfolist;
use App\Filament\Workspace\Resources\Conversations\Tables\ConversationsTable;
use App\Models\Conversation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ConversationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConversationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConversationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConversations::route('/'),
            'create' => CreateConversation::route('/create'),
            'view' => ViewConversation::route('/{record}'),
            'edit' => EditConversation::route('/{record}/edit'),
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
