<?php

namespace App\Services\Domain;

use App\Jobs\ConfigureDomainDnsJob;
use App\Jobs\ProvisionSslJob;
use App\Jobs\RegisterDomainJob;
use App\Jobs\RenewDomainJob;
use App\Jobs\SyncDomainStatusJob;
use App\Jobs\VerifyDomainJob;
use App\Models\Website\Website;
use App\Models\Website\WebsiteDomain;
use App\Models\Website\WebsiteDomainContact;
use App\Models\Website\WebsiteDomainOperation;
use App\Services\Domain\Contracts\DomainRegistrarInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DomainService
{
    public function __construct(
        private readonly DomainRegistrarInterface $registrar,
    ) {}

    /**
     * @param  array<int, string>|null  $extensions
     * @return array<int, array<string, mixed>>
     */
    public function searchDomains(string $query, ?array $extensions = null): array
    {
        $base = strtolower(trim($query));
        $base = preg_replace('/[^a-z0-9-]/', '', $base) ?? '';
        $base = trim($base, '-');
        if ($base === '') {
            throw new RuntimeException('Domain query is required.');
        }

        $extensions = $extensions ?: (array) config('website.domain_search_tlds', ['com']);
        $extensions = collect($extensions)
            ->map(fn ($tld) => strtolower(trim((string) $tld)))
            ->filter(fn (string $tld) => preg_match('/^[a-z0-9.-]{2,}$/', $tld) === 1)
            ->unique()
            ->take(20)
            ->values()
            ->all();

        $domains = collect($extensions)->map(fn (string $tld): string => "{$base}.{$tld}")->all();
        $results = $this->registrar->checkAvailability($domains);

        $markupPercent = (float) config('website.domain_markup_percent', 0);

        return array_map(function (array $result) use ($markupPercent): array {
            $rawRegistration = (float) ($result['registration_price'] ?? 0);
            $rawRenewal = (float) ($result['renewal_price'] ?? 0);
            $rawTransfer = (float) ($result['transfer_price'] ?? 0);

            $withMarkup = fn (float $amount): ?float => $amount > 0
                ? round($amount + ($amount * $markupPercent / 100), 2)
                : null;

            return [
                ...$result,
                'registration_price' => $withMarkup($rawRegistration),
                'renewal_price' => $withMarkup($rawRenewal),
                'transfer_price' => $withMarkup($rawTransfer),
                'markup_percent' => $markupPercent,
            ];
        }, $results);
    }

    /**
     * @param  array<string, array<string, mixed>>  $contacts
     */
    public function purchaseDomain(
        Website $website,
        string $domain,
        int $years,
        array $contacts,
        ?int $actorUserId = null,
    ): WebsiteDomain {
        $normalizedDomain = DomainName::normalize($domain);
        if ($normalizedDomain === '') {
            throw new RuntimeException('Invalid domain name.');
        }

        $existing = WebsiteDomain::withoutGlobalScopes()
            ->where('normalized_domain', $normalizedDomain)
            ->first();

        if ($existing && (int) $existing->website_id !== (int) $website->id) {
            throw new RuntimeException('Domain is already connected to another website.');
        }

        $websiteDomain = DB::transaction(function () use ($website, $domain, $normalizedDomain, $existing): WebsiteDomain {
            $model = $existing ?: new WebsiteDomain();
            if ($existing && method_exists($existing, 'trashed') && $existing->trashed()) {
                $existing->restore();
            }
            $model->workspace_id = $website->workspace_id;
            $model->website_id = $website->id;
            $model->domain = $domain;
            $model->normalized_domain = $normalizedDomain;
            $model->type = 'custom_domain';
            $model->provider = 'namecheap';
            $model->status = 'registering';
            $model->verification_status = 'unverified';
            $model->dns_status = 'pending';
            $model->save();

            return $model->refresh();
        });

        RegisterDomainJob::dispatch($websiteDomain->id, max(1, min(10, $years)), $contacts, $actorUserId);

        return $websiteDomain->refresh();
    }

    /**
     * @param  array<string, array<string, mixed>>  $contacts
     */
    public function executeDomainRegistration(WebsiteDomain $websiteDomain, int $years, array $contacts, ?int $actorUserId = null): WebsiteDomain
    {
        $idempotencyKey = 'register:'.$websiteDomain->normalized_domain.':'.$years;

        $operation = WebsiteDomainOperation::withoutGlobalScopes()->firstOrCreate(
            [
                'workspace_id' => $websiteDomain->workspace_id,
                'provider' => 'namecheap',
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'workspace_id' => $websiteDomain->workspace_id,
                'website_id' => $websiteDomain->website_id,
                'website_domain_id' => $websiteDomain->id,
                'operation_type' => 'purchase',
                'status' => 'processing',
                'request_payload' => [
                    'domain' => $websiteDomain->domain,
                    'years' => $years,
                ],
            ]
        );

        if ($operation->status === 'succeeded' && filled($websiteDomain->provider_domain_id)) {
            return $websiteDomain;
        }

        try {
            $check = $this->registrar->checkAvailability([$websiteDomain->domain]);
            $first = $check[0] ?? null;
            if (! $first || ($first['available'] ?? false) !== true) {
                throw new RuntimeException('Domain is no longer available for registration.');
            }

            $registration = $this->registrar->register($websiteDomain->domain, $years, $contacts);

            DB::transaction(function () use ($websiteDomain, $registration, $contacts, $operation): void {
                $websiteDomain->update([
                    'provider_domain_id' => $registration['provider_domain_id'] ?: $websiteDomain->provider_domain_id,
                    'status' => 'registered',
                    'verification_status' => 'verifying',
                    'dns_status' => 'pending',
                    'metadata' => array_merge(
                        is_array($websiteDomain->metadata) ? $websiteDomain->metadata : [],
                        [
                            'registration' => $registration,
                        ]
                    ),
                ]);

                foreach (['registrant', 'admin', 'tech', 'aux_billing'] as $contactType) {
                    if (! is_array($contacts[$contactType] ?? null)) {
                        continue;
                    }
                    WebsiteDomainContact::withoutGlobalScopes()->updateOrCreate(
                        [
                            'workspace_id' => $websiteDomain->workspace_id,
                            'website_domain_id' => $websiteDomain->id,
                            'contact_type' => $contactType,
                        ],
                        [
                            'workspace_id' => $websiteDomain->workspace_id,
                            'website_domain_id' => $websiteDomain->id,
                            'contact_type' => $contactType,
                            'organization_name' => trim((string) ($contacts[$contactType]['organization_name'] ?? '')) ?: null,
                            'job_title' => trim((string) ($contacts[$contactType]['job_title'] ?? '')) ?: null,
                            'first_name' => (string) ($contacts[$contactType]['first_name'] ?? ''),
                            'last_name' => (string) ($contacts[$contactType]['last_name'] ?? ''),
                            'address1' => (string) ($contacts[$contactType]['address1'] ?? ''),
                            'address2' => trim((string) ($contacts[$contactType]['address2'] ?? '')) ?: null,
                            'city' => (string) ($contacts[$contactType]['city'] ?? ''),
                            'state_province' => (string) ($contacts[$contactType]['state_province'] ?? ''),
                            'postal_code' => (string) ($contacts[$contactType]['postal_code'] ?? ''),
                            'country' => strtoupper((string) ($contacts[$contactType]['country'] ?? 'US')),
                            'phone' => (string) ($contacts[$contactType]['phone'] ?? ''),
                            'email' => (string) ($contacts[$contactType]['email'] ?? ''),
                            'metadata' => null,
                        ]
                    );
                }

                $operation->update([
                    'status' => 'succeeded',
                    'response_payload' => $registration,
                    'processed_at' => now(),
                ]);
            });

            ConfigureDomainDnsJob::dispatch($websiteDomain->id, $actorUserId);
        } catch (\Throwable $exception) {
            $operation->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'processed_at' => now(),
            ]);

            $websiteDomain->update([
                'status' => 'failed',
                'verification_status' => 'failed',
                'metadata' => array_merge(
                    is_array($websiteDomain->metadata) ? $websiteDomain->metadata : [],
                    ['last_error' => $exception->getMessage()]
                ),
            ]);

            throw $exception;
        }

        return $websiteDomain->refresh();
    }

    public function configureDns(WebsiteDomain $websiteDomain, ?int $actorUserId = null): WebsiteDomain
    {
        $websiteDomain->update([
            'status' => 'dns_pending',
            'dns_status' => 'pending',
        ]);

        VerifyDomainJob::dispatch($websiteDomain->id, $actorUserId)->delay(now()->addSeconds(5));

        return $websiteDomain;
    }

    public function setPrimaryDomain(WebsiteDomain $domain): WebsiteDomain
    {
        DB::transaction(function () use ($domain): void {
            WebsiteDomain::withoutGlobalScopes()
                ->where('website_id', $domain->website_id)
                ->update(['is_primary' => false]);

            $domain->update(['is_primary' => true]);

            $website = $domain->website()->withoutGlobalScopes()->firstOrFail();
            $website->update(['primary_domain_id' => $domain->id]);
        });

        return $domain->refresh();
    }

    public function verifyDomain(WebsiteDomain $websiteDomain): WebsiteDomain
    {
        $websiteDomain->update([
            'status' => 'verifying',
            'verification_status' => 'verifying',
        ]);

        $dns = $this->registrar->getDnsRecords($websiteDomain->domain);
        $target = trim((string) config('website.dns_target'));
        $targetType = strtoupper((string) config('website.dns_target_type', 'A'));

        $verified = collect($dns['records'] ?? [])->contains(function ($record) use ($target, $targetType): bool {
            if (! is_array($record)) {
                return false;
            }

            return strtolower((string) ($record['name'] ?? '')) === '@'
                && strtoupper((string) ($record['type'] ?? '')) === $targetType
                && (string) ($record['address'] ?? '') === $target;
        });

        $websiteDomain->update([
            'status' => $verified ? 'verified' : 'failed',
            'verification_status' => $verified ? 'verified' : 'failed',
            'dns_status' => $verified ? 'configured' : 'failed',
        ]);

        if ($verified) {
            ProvisionSslJob::dispatch($websiteDomain->id);
        }

        return $websiteDomain->refresh();
    }

    public function renewDomain(WebsiteDomain $websiteDomain, int $years = 1): WebsiteDomain
    {
        RenewDomainJob::dispatch($websiteDomain->id, max(1, min(10, $years)));

        return $websiteDomain;
    }

    public function executeRenewDomain(WebsiteDomain $websiteDomain, int $years = 1): WebsiteDomain
    {
        $result = $this->registrar->renew($websiteDomain->domain, max(1, min(10, $years)));
        $info = $this->registrar->getInfo($websiteDomain->domain);

        $expiresAt = $this->extractExpiryDate($info);

        $websiteDomain->update([
            'status' => 'active',
            'verification_status' => 'verified',
            'expires_at' => $expiresAt,
            'metadata' => array_merge(
                is_array($websiteDomain->metadata) ? $websiteDomain->metadata : [],
                [
                    'renewal' => $result,
                ]
            ),
        ]);

        WebsiteDomainOperation::withoutGlobalScopes()->create([
            'workspace_id' => $websiteDomain->workspace_id,
            'website_id' => $websiteDomain->website_id,
            'website_domain_id' => $websiteDomain->id,
            'operation_type' => 'renew',
            'provider' => 'namecheap',
            'status' => 'succeeded',
            'idempotency_key' => 'renew:'.$websiteDomain->normalized_domain.':'.Str::uuid(),
            'request_payload' => ['years' => $years],
            'response_payload' => $result,
            'processed_at' => now(),
        ]);

        return $websiteDomain->refresh();
    }

    public function syncDomainStatus(WebsiteDomain $websiteDomain): WebsiteDomain
    {
        SyncDomainStatusJob::dispatch($websiteDomain->id);

        return $websiteDomain;
    }

    public function executeSyncDomainStatus(WebsiteDomain $websiteDomain): WebsiteDomain
    {
        $info = $this->registrar->getInfo($websiteDomain->domain);
        $expiresAt = $this->extractExpiryDate($info);
        $status = strtolower((string) ($info['status'] ?? 'unknown'));

        $websiteDomain->update([
            'expires_at' => $expiresAt,
            'status' => $status === 'expired' ? 'expired' : $websiteDomain->status,
            'metadata' => array_merge(
                is_array($websiteDomain->metadata) ? $websiteDomain->metadata : [],
                ['provider_info' => $info]
            ),
        ]);

        WebsiteDomainOperation::withoutGlobalScopes()->create([
            'workspace_id' => $websiteDomain->workspace_id,
            'website_id' => $websiteDomain->website_id,
            'website_domain_id' => $websiteDomain->id,
            'operation_type' => 'sync_status',
            'provider' => 'namecheap',
            'status' => 'succeeded',
            'idempotency_key' => 'sync:'.$websiteDomain->normalized_domain.':'.Str::uuid(),
            'response_payload' => $info,
            'processed_at' => now(),
        ]);

        return $websiteDomain->refresh();
    }

    private function extractExpiryDate(array $info): ?Carbon
    {
        $raw = data_get($info, 'raw.DomainDetails.ExpiredDate');
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
