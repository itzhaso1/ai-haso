<?php

namespace App\Jobs;

use App\Models\Website\WebsiteDomain;
use App\Models\Website\WebsiteDomainOperation;
use App\Services\Domain\SslService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class ProvisionSslJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public readonly int $websiteDomainId) {}

    public function handle(SslService $sslService): void
    {
        $domain = WebsiteDomain::withoutGlobalScopes()->find($this->websiteDomainId);
        if (! $domain) {
            return;
        }

        $result = $sslService->requestProvisioning($domain);

        WebsiteDomainOperation::withoutGlobalScopes()->create([
            'workspace_id' => $domain->workspace_id,
            'website_id' => $domain->website_id,
            'website_domain_id' => $domain->id,
            'operation_type' => 'verify',
            'provider' => 'ssl',
            'status' => 'succeeded',
            'idempotency_key' => 'ssl:'.$domain->normalized_domain.':'.Str::uuid(),
            'response_payload' => $result,
            'processed_at' => now(),
        ]);
    }
}
