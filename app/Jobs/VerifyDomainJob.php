<?php

namespace App\Jobs;

use App\Models\Website\WebsiteDomain;
use App\Services\Domain\DomainService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class VerifyDomainJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 120;

    public function __construct(
        public readonly int $websiteDomainId,
        public readonly ?int $actorUserId = null,
    ) {}

    public function handle(DomainService $domainService): void
    {
        $domain = WebsiteDomain::withoutGlobalScopes()->find($this->websiteDomainId);
        if (! $domain) {
            return;
        }

        $domainService->verifyDomain($domain);
    }
}
