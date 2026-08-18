# AI-HASO (Laravel SaaS Platform)

Production-oriented Laravel backend for a multi-tenant platform supporting:

- Individuals (personal AI + smart replies + conversations)
- Companies/Stores (commerce workspace with products, inventory, orders, customers, payments, WhatsApp, AI, employees)

## Architecture Document

Full architecture, tenancy model, ERD, auth flows, security model, testing strategy, and implementation roadmap:

- `docs/architecture/production-architecture.md`

## Current Foundation Scope

This repository currently includes foundation modules:

1. Laravel project bootstrap
2. Multi-tenancy core (`workspaces`, memberships, context middleware)
3. Authentication foundations:
   - Email/password
   - Phone OTP flow
   - Social identity model (Google/Facebook token exchange)
4. Platform admin separation (`/platform/*`)
5. Plan/subscription/feature-access foundations
6. Audit and webhook-event base tables

## Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan test
```
