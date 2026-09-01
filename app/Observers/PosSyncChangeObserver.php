<?php

namespace App\Observers;

use App\Models\DiningTable;
use App\Models\PosItemCategory;
use App\Models\PosMenuItem;
use App\Services\Pos\PosSyncChangeRecorder;
use Illuminate\Database\Eloquent\Model;

/**
 * Records catalog/table mutations into the monotonic pos_sync_changes log.
 * Soft deletes emit operation=delete so offline devices learn about removals.
 */
class PosSyncChangeObserver
{
    public function __construct(
        private readonly PosSyncChangeRecorder $recorder,
    ) {}

    public function created(Model $model): void
    {
        $this->write($model, 'create');
    }

    public function updated(Model $model): void
    {
        $this->write($model, 'update');
    }

    public function deleted(Model $model): void
    {
        $this->write($model, 'delete');
    }

    public function forceDeleted(Model $model): void
    {
        $this->write($model, 'delete');
    }

    public function restored(Model $model): void
    {
        $this->write($model, 'update');
    }

    private function write(Model $model, string $operation): void
    {
        $entity = $this->entityType($model);
        if ($entity === null) {
            return;
        }

        if (! $model->getAttribute('workspace_id')) {
            return;
        }

        $origin = null;
        try {
            $origin = request()?->header('X-Device-Id');
            if (is_string($origin)) {
                $origin = trim($origin);
                if ($origin === '') {
                    $origin = null;
                }
            } else {
                $origin = null;
            }
        } catch (\Throwable) {
            $origin = null;
        }

        $this->recorder->record($entity, $operation, $model, $origin);
    }

    private function entityType(Model $model): ?string
    {
        return match (true) {
            $model instanceof PosMenuItem => PosSyncChangeRecorder::ENTITY_PRODUCT,
            $model instanceof PosItemCategory => PosSyncChangeRecorder::ENTITY_CATEGORY,
            $model instanceof DiningTable => PosSyncChangeRecorder::ENTITY_TABLE,
            default => null,
        };
    }
}
