# Features

Feature flags and plan entitlements gate product modules.

## How to check in code

```php
$featureAccess->hasFeature($user, $workspace, 'analytics');
$featureAccess->assertCanUse($user, $workspace, 'ai', 'ai_usage');
```

Middleware example:

```php
Route::middleware('workspace.feature:analytics')->group(...);
Route::middleware('workspace.feature:api')->group(...);
```

## Recently productized surfaces

| Feature | Route / UI |
|---------|------------|
| Holidays | `workspace/appointments/holidays` (auth via appointments elevated members) |
| Analytics | `workspace/analytics` |
| API keys | `workspace/api-keys` |
| Image uploader | Blade component `x-image-uploader` (used on website logo) |

## Overrides

`workspace_feature_flags` can force-enable/disable a key per workspace (`source = manual` commonly used in tests).

## Compatibility

Plans that only grant `appointments` also unlock `website_builder`, `custom_domains`, and `public_booking` via compatibility aliases in `FeatureAccessService`.
