# Product Overview

Honest status of the HASem SaaS productization layer as of the current codebase.

## What is production-ready enough to use

- **Workspace tenancy** with membership roles and workspace-scoped models.
- **Appointments core**: bookings, calendar, requests, staff/services, customer portal, reminders.
- **POS / cashier** tables, kitchen, invoices, QR menus (existing core — do not regress).
- **Finance** invoices, expenses, payroll modules (existing core — do not regress).
- **Website builder + public booking** funnel (Arabic UI), custom domain purchase flow (Namecheap), publish/preview.
- **Feature entitlements** matrix (`config/plans.php` + `FeatureAccessService`) with meters and overrides.
- **CRM light**: customer tags, groups, notes.
- **WhatsApp outbound foundation** (needs Meta tokens to actually send).
- **Analytics MVP**: workspace-scoped revenue / bookings / orders / customers / top services & products.
- **API Keys foundation**: create / list / revoke / regenerate; hash-only storage; plaintext shown once.
- **AI assistant** (public + member) with hardened prompts and no cross-workspace dumps.

## What is intentionally incomplete / placeholder

- **Paid subscription gateways** (Stripe Billing etc.): interface exists; runtime provider is `LocalSubscriptionBillingProvider` (local/placeholder checkout confirmation).
- **WhatsApp Cloud API**: UI + webhook routes exist; real send requires `WHATSAPP_*` tokens and Meta app setup.
- **Custom domain SSL**: app has `SslService` abstraction and jobs; **actual certificates need Linux + Certbot/ACME** (or equivalent) on the host — not issued inside Windows-only setups.
- **Public API consumption of ApiKey**: keys can be managed in UI; authenticating REST calls with those keys is foundation-stage (hash/verify helpers exist via `ApiKeyService`).

## Module map (high level)

| Area | Status |
|------|--------|
| Appointments | Core solid; holidays CRUD UI added |
| POS | Core solid |
| Finance | Core solid |
| Website / booking | Usable MVP |
| Analytics | MVP dashboards |
| API keys | Product foundation |
| Billing | Local provider |
| WhatsApp | Token-gated |
| Docs | This folder |

## Non-goals for this pass

Do not break Appointment / POS / Finance cores while productizing adjacent modules.
