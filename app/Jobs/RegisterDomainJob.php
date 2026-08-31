<?php

namespace App\Jobs;

use App\Models\Website\WebsiteDomain;
use App\Services\Domain\DomainService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RegisterDomainJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 180;

    /**
     * @param  array<string, array<string, mixed>>  $contacts
     */
    public function __construct(
        public readonly int $websiteDomainId,
        public readonly int $years,
        public readonly array $contacts,
        public readonly ?int $actorUserId = null,
    ) {}

    public function handle(DomainService $domainService): void
    {
        $domain = WebsiteDomain::withoutGlobalScopes()->find($this->websiteDomainId);
        if (! $domain) {
            return;
        }

        $domainService->executeDomainRegistration(
            websiteDomain: $domain,
            years: $this->years,
            contacts: $this->contacts,
            actorUserId: $this->actorUserId,
        );
    }
}
