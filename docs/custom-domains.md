# Custom Domains (Appointments Website Builder)

## Domain model

Domain data is stored in `website_domains` and includes:

- `workspace_id`, `website_id`
- `domain`, `normalized_domain`
- `type` (`platform_subdomain`, `custom_domain`)
- `provider`, `provider_domain_id`
- status fields:
  - `status`
  - `verification_status`
  - `dns_status`
  - `ssl_status`
- `expires_at`, `auto_renew`, `is_primary`, `metadata`

`normalized_domain` is unique to prevent collisions across websites.

## Domain statuses used

`pending`, `registering`, `registered`, `dns_pending`, `dns_configured`, `verifying`, `verified`, `ssl_pending`, `active`, `failed`, `expired`, `cancelled`

## Main flow

1. Search availability/pricing (`DomainService::searchDomains`).
2. Purchase domain (`DomainService::purchaseDomain`).
3. Register domain via queue job (`RegisterDomainJob`).
4. Configure DNS via queue job (`ConfigureDomainDnsJob`).
5. Verify DNS (`VerifyDomainJob` / manual verify action).
6. Request SSL provisioning (`ProvisionSslJob` abstraction).
7. Activate and optionally set primary domain.

## DNS handling

DNS configuration is implemented by `DnsService`:

1. Read existing hosts from registrar.
2. Preserve non-website records.
3. Upsert required website records (`@` and `www`) using configured targets.
4. Set merged record list with registrar API.
5. Verify DNS state after write.

Target values come from config/env (`WEBSITE_DNS_TARGET`, `WEBSITE_DNS_TARGET_TYPE`, etc.), never hardcoded in code.

## Background jobs

- `RegisterDomainJob`
- `ConfigureDomainDnsJob`
- `VerifyDomainJob`
- `ProvisionSslJob`
- `RenewDomainJob`
- `SyncDomainStatusJob`

Scheduled sync command:

- `domains:sync-status` (scheduled daily)

DNS verification retries:

- If verification does not pass immediately, the system keeps domain status in `dns_pending`.
- Retries are queued every `WEBSITE_DOMAIN_VERIFICATION_RETRY_SECONDS` (default 600).
- After `WEBSITE_DOMAIN_VERIFICATION_MAX_ATTEMPTS` (default 12), status is marked `failed`.

## Security/authorization

- Domain management routes require dashboard auth/workspace membership.
- Feature flags required: `website_builder`, `custom_domains`.
- Policies:
  - `WebsitePolicy::manageDomains`
  - `WebsiteDomainPolicy` actions (`setPrimary`, `renew`, `delete`, etc.)
- Domain search/purchase actions are rate limited.

## Notes on SSL

`SslService` is currently an abstraction entry point that marks provisioning lifecycle state and stores metadata. Final certificate issuance is infrastructure-dependent and must be connected to your reverse-proxy/certificate automation pipeline.
