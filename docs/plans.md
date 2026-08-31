# Plans & Entitlements

## Source of truth

- Commercial matrix: `config/plans.php` (`feature_matrix`, meters, comparison rows).
- Workspace type defaults: `config/workspace.php` → `features_by_type`.
- Runtime checks: `App\Services\Feature\FeatureAccessService`.
- Middleware: `workspace.feature:{key}` (`EnsureFeatureAccess`).

## Decision order

1. Active membership in the workspace.
2. Per-workspace `workspace_feature_flags` override (if present).
3. Feature must appear in `features_by_type` for the workspace type.
4. Active/trialing/past_due subscription plan must include the feature (with appointments→website compatibility aliases).

## Notable feature keys

| Key | Notes |
|-----|-------|
| `analytics` | Workspace analytics UI |
| `api` | API keys management UI |
| `website_builder` / `custom_domains` / `public_booking` | Website stack |
| `whatsapp` | WhatsApp accounts + outbound |
| `ai` | AI settings / assistant entitlements |

## Limits / meters

Meters such as `ai_usage`, `whatsapp_messages`, `api_calls`, `bookings`, `orders` are defined in `config/plans.php`. Usage is tracked in `workspace_usage_meters`.

## Honest gaps

- Seeded legacy plan rows in `FoundationSeeder` may lag the newer `feature_matrix` until re-seeded.
- Paid plan upgrades go through the **local** billing provider unless a real provider is bound.
