# Website + Domain Deployment Notes

## Application Expectations

The Laravel app resolves websites at application level (not per-customer static Nginx config):

- host header → `ResolvePublicWebsite` middleware
- host mapped to `website_domains.normalized_domain`
- fallback available using `/public/{slug}`

## Required Infrastructure

1. **Wildcard platform domain**  
   Point `*.your-platform-domain.com` to your app/load balancer.

2. **Custom domains**  
   Ensure DNS target (`WEBSITE_DNS_TARGET`) points to edge/load balancer IP or CNAME endpoint.

3. **HTTPS termination**  
   SSL provisioning/renewal must be integrated at reverse-proxy or edge layer.
   `SslService` only tracks provisioning request state (`ssl_pending` etc.).

4. **Queue workers**  
   Required for domain operations:
   - register
   - DNS configure
   - verify
   - renew
   - sync

5. **Scheduler**
   Enable Laravel scheduler (`php artisan schedule:run`) for domain sync command.

## Key Environment Variables

```dotenv
WEBSITE_PLATFORM_DOMAIN=your-platform-domain.com
WEBSITE_DNS_TARGET=203.0.113.10
WEBSITE_DNS_TARGET_TYPE=A
WEBSITE_DNS_TTL=300
WEBSITE_DNS_WWW_TARGET=@
WEBSITE_PREVIEW_URL_TTL_MINUTES=120
WEBSITE_RESOLVER_CACHE_TTL_SECONDS=300
WEBSITE_PUBLIC_RATE_LIMIT=60,1
WEBSITE_DOMAIN_SEARCH_TLDS=com,net,org
WEBSITE_DOMAIN_MARKUP_PERCENT=0
```

## Nginx Considerations

- preserve incoming `Host` header
- forward `X-Forwarded-Proto` for canonical URL generation
- allow `/.well-known/*` paths if your SSL provider needs ACME challenges
- do not block long query strings for webhook-like third-party callbacks

## Health Checks

- verify `GET /` serves landing page on app domain
- verify custom-domain host resolves and renders tenant website
- verify `/robots.txt` and `/sitemap.xml` on both host-resolved and slug-resolved routes
