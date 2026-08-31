<?php

namespace App\Jobs;

use App\Models\Website\WebsiteDomain;
use App\Services\Domain\DomainService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RenewDomainJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 180;

    public function __construct(
        public readonly int $websiteDomainId,
        public readonly int $years = 1,
    ) {}

    public function handle(DomainService $domainService): void
    {
        $domain = WebsiteDomain::withoutGlobalScopes()->find($this->websiteDomainId);
        if (! $domain) {
            return;
        }

        $domainService->executeRenewDomain($domain, $this->years);
    }
}
