# Billing

## Current provider

`App\Providers\AppServiceProvider` binds:

`SubscriptionBillingProviderInterface` → `LocalSubscriptionBillingProvider`

That means:

- Checkout sessions can be created and confirmed **locally**.
- There is **no live Stripe Billing / paid gateway** wired for platform subscriptions in this binding.
- Order/payment **payment links** for customers still use the existing Payment gateway manager (local + optional Stripe payment config) — separate from subscription billing.

## Subscription UI

Workspace routes under `workspace/subscriptions` drive plan selection and local checkout confirmation.

## What to do for production payments

1. Implement a real provider (e.g. Stripe) against `SubscriptionBillingProviderInterface`.
2. Bind it in `AppServiceProvider` behind env (`BILLING_PROVIDER=stripe`).
3. Configure webhook secrets (`STRIPE_WEBHOOK_SECRET`, etc.).
4. Keep `LocalSubscriptionBillingProvider` for CI/dev.

## Status label

**Placeholder / local for paid subscription gateways.** Do not advertise “card billing live” until a non-local provider is bound and webhooks verified.
