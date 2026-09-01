<?php

namespace App\Services\Pos;

use App\Models\DiningTable;
use App\Models\PosItemCategory;
use App\Models\PosMenuItem;
use App\Models\PosSyncChange;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only workspace change log. Row id is the monotonic sync cursor.
 */
class PosSyncChangeRecorder
{
    public const ENTITY_PRODUCT = 'product';

    public const ENTITY_CATEGORY = 'category';

    public const ENTITY_TABLE = 'table';

    public function record(
        string $entityType,
        string $operation,
        Model $model,
        ?string $originDeviceId = null,
    ): PosSyncChange {
        $workspaceId = (int) $model->getAttribute('workspace_id');
        if ($workspaceId <= 0) {
            throw new \InvalidArgumentException('workspace_id required to record sync change');
        }

        $entityId = (int) $model->getKey();
        $payload = match ($operation) {
            'delete' => [
                'id' => $entityId,
                'deleted' => true,
            ],
            default => $this->snapshot($entityType, $model),
        };

        return PosSyncChange::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceId,
            'entity_type' => $entityType,
            'entity_id' => $entityId > 0 ? $entityId : null,
            'operation' => $operation,
            'payload' => $payload,
            'origin_device_id' => $originDeviceId,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(string $entityType, Model $model): array
    {
        return match ($entityType) {
            self::ENTITY_PRODUCT => $this->productPayload($model instanceof PosMenuItem ? $model : null),
            self::ENTITY_CATEGORY => $this->categoryPayload($model instanceof PosItemCategory ? $model : null),
            self::ENTITY_TABLE => $this->tablePayload($model instanceof DiningTable ? $model : null),
            default => [
                'id' => $model->getKey(),
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(?PosMenuItem $item): array
    {
        if (! $item) {
            return [];
        }

        return [
            'id' => $item->id,
            'pos_item_category_id' => $item->pos_item_category_id,
            'name' => $item->name,
            'sku' => $item->sku,
            'barcode' => $item->barcode,
            'item_type' => $item->item_type,
            'description' => $item->description,
            'price' => (float) $item->price,
            'currency' => $item->currency,
            'is_active' => (bool) $item->is_active,
            'sort_order' => (int) ($item->sort_order ?? 0),
            'updated_at' => optional($item->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryPayload(?PosItemCategory $category): array
    {
        if (! $category) {
            return [];
        }

        return [
            'id' => $category->id,
            'name' => $category->name,
            'is_active' => (bool) $category->is_active,
            'sort_order' => (int) ($category->sort_order ?? 0),
            'updated_at' => optional($category->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tablePayload(?DiningTable $table): array
    {
        if (! $table) {
            return [];
        }

        return [
            'id' => $table->id,
            'name' => $table->name,
            'status' => $table->status,
            'qr_token' => $table->qr_token,
            'updated_at' => optional($table->updated_at)?->toIso8601String(),
        ];
    }
}
