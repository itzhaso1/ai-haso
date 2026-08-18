<?php

namespace App\Filament\Workspace\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('المنتج')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('category.name')
                    ->label('التصنيف')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('price')
                    ->label('السعر')
                    ->money()
                    ->sortable(),
                TextColumn::make('sale_price')
                    ->label('سعر العرض')
                    ->money()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('currency')
                    ->label('العملة'),
                TextColumn::make('stock')
                    ->label('المخزون')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('variants_count')
                    ->counts('variants')
                    ->label('عدد المتغيرات')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'draft' => 'warning',
                        'archived' => 'gray',
                        default => 'danger',
                    }),
                TextColumn::make('brand')
                    ->label('العلامة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('weight')
                    ->label('الوزن')
                    ->numeric()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'archived' => 'Archived',
                    ]),
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
