# Website System (Appointments Module)

## Scope

The website module is layered on top of the existing appointment core. It does not replace booking models/services.

## Main entities

- `website_templates`
- `websites`
- `website_pages`
- `website_sections`
- `website_domains`
- `website_assets`
- `website_domain_operations`
- `website_domain_contacts`

All tenant-owned website entities include `workspace_id` and use workspace-scoped models.

## Lifecycle

Website states:

- `draft`
- `published`
- `unpublished`
- `suspended`

Publish checks:

1. Template selected.
2. Business name exists in settings.
3. At least one active/verified domain exists.

## Services

- `App\Services\Website\TemplateService`
  - seeds default templates
  - bootstraps sections/pages
- `App\Services\Website\WebsiteService`
  - create site, select template, customize, publish/unpublish
- `App\Services\Website\WebsiteResolverService`
  - resolve site by host or slug with cache
- `App\Services\Website\PublicWebsiteService`
  - builds section/view data from workspace + appointment data
- `App\Services\Website\PublicBookingService`
  - public services/staff/availability/booking APIs

## Dashboard routes

Under: `workspace.appointments.website.*`

- overview
- create
- templates/select
- customize/update
- sections/update
- preview
- publish/unpublish
- domains management pages/actions

Website and domain routes are guarded by:

- auth + workspace middleware
- `workspace.feature:website_builder`
- `workspace.feature:custom_domains` for domain actions
- policy checks (`WebsitePolicy`, `WebsiteDomainPolicy`)

## Public rendering routes

- Host-resolved mode:
  - `/` (home)
  - `/booking`
  - `/contact`
  - `/api/public/*` (public booking API on resolved host)
- Slug mode:
  - `/public/{website}`
  - `/public/{website}/{page}`
- Preview mode:
  - `/public-preview/{token}/{page?}`

Middleware `public.website.resolve` maps request host/slug to website + workspace context.

## SEO endpoints

- Host-resolved:
  - `/robots.txt`
  - `/sitemap.xml`
- Slug mode:
  - `/public/{website}/robots.txt`
  - `/public/{website}/sitemap.xml`

Draft/unpublished websites return restrictive robots behavior.

## Multi-tenancy

- Website models extend `WorkspaceScopedModel`.
- Public middleware sets workspace context per resolved website.
- Controllers enforce same-workspace checks and policy authorization.

## Caching

`WebsiteResolverService` caches host and slug lookups and invalidates on website/domain changes.
