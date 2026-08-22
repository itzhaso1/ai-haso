# HASEM (Laravel SaaS Platform)

Production-oriented Laravel SaaS platform (Backend + Frontend inside Laravel) supporting:

- Individuals (personal AI + smart replies + conversations)
- Companies/Stores (commerce workspace with products, inventory, orders, customers, payments, WhatsApp, AI, employees)

## Architecture Document

Full architecture, tenancy model, ERD, auth flows, security model, testing strategy, and implementation roadmap:

- `docs/architecture/production-architecture.md`

## Implemented Modules (Current)

This repository now includes a functional multi-tenant foundation plus operational modules:

1. **Frontend**
   - Landing page, login/register, OTP login, workspace chooser, profile, notifications.
   - Blade MVC workspace dashboard (`/workspace`) and platform dashboard (`/platform`).
2. **Authentication**
   - Email/password, OTP (phone), Google/Facebook OAuth redirects, email verification, password reset.
3. **Workspace Isolation**
   - Workspace context middleware + workspace-scoped models with write-protection.
4. **Commerce & CRM**
   - Categories, products (+variants), inventory movements, customers, orders, payments.
5. **Conversations & AI**
   - Conversations, messages, AI settings, AI response pipeline with provider abstraction.
6. **Email CRM & Inbox Hub**
   - Workspace-isolated email accounts (IMAP/SMTP settings), branded outbound templates, queue-based send/sync jobs, archived messages, and attachments.
7. **Integrations**
   - WhatsApp webhook architecture + processing jobs.
   - Payment gateway abstraction + webhook processing + idempotency.
8. **Subscriptions / Employees / Roles**
   - Plans, subscriptions, employee invitations, role/permission APIs (team-scoped).
9. **Ops**
   - Notifications (database/mail), queue jobs, audit/webhook logging schema.

## Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
php artisan migrate --seed
php artisan storage:link
php artisan serve
npm run dev
```

### Database (MySQL)

- Default setup now targets **MySQL** (`DB_CONNECTION=mysql`).
- Configure in `.env`:
  - `DB_HOST`
  - `DB_PORT`
  - `DB_DATABASE`
  - `DB_USERNAME`
  - `DB_PASSWORD`

### AI Connector (Google AI Studio)

- Configure Gemini API key in `.env`:
  - `AI_DEFAULT_PROVIDER=google_ai_studio`
  - `GEMINI_API_KEY=...`
  - `GEMINI_MODEL=gemini-2.5-flash`

### WhatsApp Webhook (Meta)

- Verification endpoint: `GET /api/webhooks/whatsapp`
- Incoming messages endpoint: `POST /api/webhooks/whatsapp`
- Configure in `.env`:
  - `WHATSAPP_VERIFY_TOKEN=your_verify_token`
  - `WHATSAPP_APP_SECRET=your_meta_app_secret`
- Controller used:
  - `App\Http\Controllers\Webhook\WhatsAppWebhookController`

## Default Platform Admin (local)

- Email: `admin@hasem.local`
- Password: `password`

Override via `.env`:

- `PLATFORM_ADMIN_EMAIL`
- `PLATFORM_ADMIN_PASSWORD`
- `PLATFORM_ADMIN_NAME`
