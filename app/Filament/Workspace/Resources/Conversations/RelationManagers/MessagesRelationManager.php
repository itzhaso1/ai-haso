<?php

namespace App\Filament\Workspace\Resources\Conversations\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->relationship('customer', 'name'),
                Select::make('user_id')
                    ->relationship('user', 'name'),
                Select::make('direction')
                    ->required()
                    ->default('outbound')
                    ->options([
                        'inbound' => 'Inbound',
                        'outbound' => 'Outbound',
                        'internal_note' => 'Internal note',
                    ]),
                Select::make('message_type')
                    ->required()
                    ->default('text')
                    ->options([
                        'text' => 'Text',
                        'image' => 'Image',
                        'file' => 'File',
                        'system' => 'System',
                    ]),
                Textarea::make('content')
                    ->columnSpanFull(),
                TextInput::make('external_message_id'),
                Toggle::make('ai_generated')
                    ->default(false)
                    ->required(),
                Textarea::make('metadata')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('content')
            ->columns([
                TextColumn::make('customer.name')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('direction')
                    ->searchable(),
                TextColumn::make('message_type')
                    ->searchable(),
                TextColumn::make('external_message_id')
                    ->searchable(),
                IconColumn::make('ai_generated')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
