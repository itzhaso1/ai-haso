<?php

namespace App\Filament\Workspace\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->relationship('category', 'name'),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('$')
                    ->minValue(0),
                TextInput::make('sale_price')
                    ->numeric()
                    ->prefix('$')
                    ->minValue(0),
                TextInput::make('currency')
                    ->required()
                    ->default('USD')
                    ->maxLength(3),
                TextInput::make('stock')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                Select::make('status')
                    ->required()
                    ->default('active')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'archived' => 'Archived',
                    ]),
                TextInput::make('brand'),
                TextInput::make('weight')
                    ->numeric()
                    ->minValue(0),
                FileUpload::make('images')
                    ->multiple()
                    ->image()
                    ->disk('public')
                    ->directory(fn (): string => 'workspaces/'.(string) session('current_workspace_id').'/products')
                    ->maxFiles(8)
                    ->maxSize(4096)
                    ->columnSpanFull(),
                Repeater::make('variants')
                    ->relationship()
                    ->schema([
                        TextInput::make('name')
                            ->label('Variant Name')
                            ->placeholder('Size 42 / Black'),
                        TextInput::make('sku')
                            ->label('Variant SKU'),
                        TextInput::make('price')
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0),
                        TextInput::make('sale_price')
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0),
                        TextInput::make('stock')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                        KeyValue::make('attributes')
                            ->columnSpanFull(),
                        Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                            ])
                            ->default('active'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
