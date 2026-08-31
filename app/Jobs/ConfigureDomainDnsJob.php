<?php

namespace App\Jobs;

use App\Models\Website\WebsiteDomain;
use App\Models\Website\WebsiteDomainOperation;
use App\Services\Domain\DnsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class ConfigureDomainDnsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 180;

    public function __construct(
        public readonly int $websiteDomainId,
        public readonly ?int $actorUserId = null,
    ) {}

    public function handle(DnsService $dnsService): void
    {
        $domain = WebsiteDomain::withoutGlobalScopes()->find($this->websiteDomainId);
        if (! $domain) {
            return;
        }

        $domain->update([
            'status' => 'dns_pending',
            'dns_status' => 'pending',
        ]);

        $operation = WebsiteDomainOperation::withoutGlobalScopes()->create([
            'workspace_id' => $domain->workspace_id,
            'website_id' => $domain->website_id,
            'website_domain_id' => $domain->id,
            'operation_type' => 'configure_dns',
            'provider' => 'namecheap',
            'status' => 'processing',
            'idempotency_key' => 'dns:'.$domain->normalized_domain.':'.Str::uuid(),
            'request_payload' => [
                'domain' => $domain->domain,
                'target' => config('website.dns_target'),
                'target_type' => config('website.dns_target_type'),
            ],
        ]);

        try {
            $result = $dnsService->configureWebsiteDns($domain);

            $domain->update([
                'status' => 'dns_configured',
                'dns_status' => ($result['verified'] ?? false) ? 'configured' : 'pending',
                'metadata' => array_merge(
                    is_array($domain->metadata) ? $domain->metadata : [],
                    ['dns' => $result]
                ),
            ]);

            $operation->update([
                'status' => 'succeeded',
                'response_payload' => $result,
                'processed_at' => now(),
            ]);

            VerifyDomainJob::dispatch($domain->id, $this->actorUserId)->delay(now()->addSeconds(5));
        } catch (\Throwable $exception) {
            $domain->update([
                'status' => 'failed',
                'dns_status' => 'failed',
                'metadata' => array_merge(
                    is_array($domain->metadata) ? $domain->metadata : [],
                    ['dns_error' => $exception->getMessage()]
                ),
            ]);

            $operation->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'processed_at' => now(),
            ]);

            throw $exception;
        }
    }
}
