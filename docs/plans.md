# Plans & Entitlements

## Source of truth

Runtime access is decided by:

1. `plans` table (`features`, `limits`, `tier`, …) — edited in **Platform Dashboard → Plans**
2. `workspace_feature_flags` overrides
3. Active `workspace_addons` grants
4. Workspace type allow-list (`config/workspace.php`)

`FeatureAccessService` combines these.  
`config/plans.php` holds **labels + seed defaults only** — changing Platform plans does **not** require code changes.

## Commercial matrix (Starter / Pro / Business / Enterprise)

| Feature | Starter | Pro | Business | Enterprise |
|---|---|---|---|---|
| Appointments | ✓ | ✓ | ✓ | ✓ |
| Website | ✓ | ✓ | ✓ | ✓ |
| Custom domain | — | ✓ | ✓ | ✓ |
| AI | ✓ | ✓ | ✓ | ✓ |
| WhatsApp | — | ✓ | ✓ | ✓ |
| POS | — | ✓ | ✓ | ✓ |
| Finance | — | ✓ | ✓ | ✓ |
| Email | — | ✓ | ✓ | ✓ |
| API | — | — | ✓ | ✓ |
| Analytics | — | ✓ | ✓ | ✓ |
| Advanced customers | — | — | ✓ | ✓ |

Seeded into `company_*` / `store_*` plan rows. Legacy codes (`company_basic`, …) keep working via `tier` + `legacy_code_tier_map`.

## Platform Dashboard

Existing `/platform/plans` UI shows the matrix from DB and edits features/limits/trial/status without a second plans system.

## Enforcement

Route middleware `workspace.feature:*` + service asserts. Missing feature → Arabic upgrade redirect / JSON 402.
