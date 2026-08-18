<?php

namespace App\Models;

use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

abstract class WorkspaceScopedModel extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('workspace', function (Builder $builder): void {
            $workspaceId = app(WorkspaceContext::class)->workspaceId();

            if ($workspaceId !== null) {
                $builder->where($builder->getModel()->qualifyColumn('workspace_id'), $workspaceId);
            }
        });

        static::creating(function (Model $model): void {
            if (! $model->getAttribute('workspace_id')) {
                $workspaceId = app(WorkspaceContext::class)->workspaceId();

                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace context is required for creating workspace scoped records.');
                }

                $model->setAttribute('workspace_id', $workspaceId);
            }
        });
    }
}
