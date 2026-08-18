<?php

namespace App\Filament\Workspace\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('workspace.name')
                    ->label('Workspace'),
                TextEntry::make('category.name')
                    ->label('Category')
                    ->placeholder('-'),
                TextEntry::make('name'),
                TextEntry::make('slug'),
                TextEntry::make('description')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('sku')
                    ->label('SKU'),
                TextEntry::make('price')
                    ->money(),
                TextEntry::make('sale_price')
                    ->money()
                    ->placeholder('-'),
                TextEntry::make('currency'),
                TextEntry::make('stock')
                    ->numeric(),
                TextEntry::make('status'),
                TextEntry::make('brand')
                    ->placeholder('-'),
                TextEntry::make('weight')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('images')
                    ->label('الصور')
                    ->formatStateUsing(function (mixed $state): string {
                        if (! is_array($state) || $state === []) {
                            return '-';
                        }

                        return nl2br(e(implode("\n", $state)));
                    })
                    ->columnSpanFull()
                    ->html(),
                TextEntry::make('attributes')
                    ->label('خصائص إضافية')
                    ->formatStateUsing(function (mixed $state): string {
                        if (! is_array($state) || $state === []) {
                            return '-';
                        }

                        $text = collect($state)
                            ->map(fn (mixed $value, string $key): string => $key.': '.(is_scalar($value) ? (string) $value : json_encode($value)))
                            ->implode("\n");

                        return nl2br(e($text));
                    })
                    ->html()
                    ->columnSpanFull(),
                TextEntry::make('variants_summary')
                    ->label('المتغيرات')
                    ->state(function (Product $record): string {
                        $variants = $record->variants()
                            ->orderBy('id')
                            ->get(['name', 'sku', 'price', 'stock']);

                        if ($variants->isEmpty()) {
                            return '-';
                        }

                        return $variants
                            ->map(fn ($variant): string => sprintf(
                                '%s | SKU: %s | Price: %s | Stock: %s',
                                $variant->name ?: 'Variant',
                                $variant->sku ?: '-',
                                number_format((float) $variant->price, 2),
                                (string) $variant->stock
                            ))
                            ->implode("\n");
                    })
                    ->html()
                    ->formatStateUsing(fn (string $state): string => nl2br(e($state)))
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Product $record): bool => $record->trashed()),
            ]);
    }
}
