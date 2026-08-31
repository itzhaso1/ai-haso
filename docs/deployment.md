# Deployment Notes (Websites, Domains, Public Booking)

## Runtime stack assumptions

- Linux
- Nginx (or reverse proxy)
- PHP-FPM
- Queue workers
- Scheduler
- Database + cache configured for production

## Application-level domain resolution

Custom domain routing is implemented in Laravel via `WebsiteResolverService` + `ResolvePublicWebsite` middleware.

Infrastructure must still route all relevant hosts to this Laravel app instance.

## Required environment variables

Set values in production:

- Namecheap:
  - `NAMECHEAP_ENV=production`
  - `NAMECHEAP_API_USER`
  - `NAMECHEAP_API_KEY`
  - `NAMECHEAP_USERNAME`
  - `NAMECHEAP_CLIENT_IP`
- Website DNS:
  - `WEBSITE_PLATFORM_DOMAIN`
  - `WEBSITE_DNS_TARGET`
  - `WEBSITE_DNS_TARGET_TYPE`
  - `WEBSITE_DNS_TTL`
  - `WEBSITE_DNS_WWW_TARGET`
- Payment webhooks:
  - `STRIPE_WEBHOOK_SECRET`
  - `STRIPE_WEBHOOK_TOLERANCE_SECONDS`
  - optional `LOCAL_PAYMENT_WEBHOOK_SECRET`

## Queue and scheduler

Run workers for domain and webhook jobs:

- `RegisterDomainJob`
- `ConfigureDomainDnsJob`
- `VerifyDomainJob`
- `ProvisionSslJob`
- `RenewDomainJob`
- `SyncDomainStatusJob`
- payment/whatsapp processing jobs

Enable scheduler (`php artisan schedule:work` or cron) for:

- `domains:sync-status` (daily status synchronization)
- existing reminder/payment maintenance commands

## DNS and SSL caveat

DNS alone is not enough for HTTPS.

To serve custom domains over TLS, infrastructure must also:

1. accept incoming host
2. route request to Laravel app
3. issue/provision certificate for host
4. renew certificate lifecycle

`SslService` is currently an app-layer abstraction and should be integrated with your actual TLS automation stack (reverse proxy, ACME, CDN, etc.).

## Nginx guidance

- Use a catch-all server block and forward host header to Laravel.
- Do not require one static vhost file per customer domain.
- Ensure `X-Forwarded-*` headers are correct when using proxy/load balancer.

## Post-deploy checks

1. Create website and publish.
2. Search/purchase sandbox or production domain.
3. Verify DNS records and status transitions.
4. Confirm public website resolution by host and by slug.
5. Test public booking end-to-end.
6. Confirm webhook endpoints return expected status codes.
