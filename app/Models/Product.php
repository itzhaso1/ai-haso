<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'category_id',
    'name',
    'slug',
    'description',
    'sku',
    'price',
    'sale_price',
    'currency',
    'stock',
    'status',
    'brand',
    'weight',
    'images',
    'attributes',
])]
class Product extends WorkspaceScopedModel
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'weight' => 'decimal:3',
            'images' => 'array',
            'attributes' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
