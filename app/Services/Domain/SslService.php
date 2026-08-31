<?php

namespace App\Services\Domain;

use App\Models\Website\WebsiteDomain;

class SslService
{
    /**
     * This abstraction intentionally does not mark SSL as active.
     * Infrastructure integration (reverse-proxy/CDN/ACME) should set final status.
     *
     * @return array<string, mixed>
     */
    public function requestProvisioning(WebsiteDomain $domain): array
    {
        $domain->update([
            'ssl_status' => 'pending',
            'status' => in_array($domain->status, ['verified', 'ssl_pending', 'active'], true)
                ? 'ssl_pending'
                : $domain->status,
        ]);

        return [
            'status' => 'pending',
            'message' => 'SSL provisioning has been requested. Infrastructure integration is required to activate HTTPS.',
        ];
    }
}
