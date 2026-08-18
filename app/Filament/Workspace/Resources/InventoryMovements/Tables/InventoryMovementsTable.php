<?php

namespace App\Filament\Workspace\Resources\InventoryMovements\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InventoryMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workspace.name')
                    ->searchable(),
                TextColumn::make('product.name')
                    ->searchable(),
                TextColumn::make('product_variant_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('before_quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('after_quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reference_type')
                    ->searchable(),
                TextColumn::make('reference_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->searchable(),
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
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
