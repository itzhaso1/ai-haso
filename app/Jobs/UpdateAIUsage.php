<?php

namespace App\Jobs;

use App\Models\AiLog;
use App\Models\Workspace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class UpdateAIUsage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $workspaceId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $usage = AiLog::withoutGlobalScopes()
            ->where('workspace_id', $this->workspaceId)
            ->sum('tokens_used');

        Cache::put('workspace:'.$this->workspaceId.':ai_usage_tokens', $usage, now()->addHours(2));
    }
}
