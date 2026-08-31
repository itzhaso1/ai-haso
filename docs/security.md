# Security

## Tenancy

- Workspace-scoped Eloquent models refuse cross-workspace writes when context is set.
- Public booking and website resolution bind a single website/workspace from host or slug — never from attacker-controlled foreign IDs without membership checks.
- Public assistant **does not** accept arbitrary `workspace_id` for unauthenticated users; products appear only for authenticated members or a **published** website resolved by host.

## API keys

- Table `api_keys` stores `key_hash` (SHA-256) + `key_prefix` only.
- Plaintext `hs_…` secret is shown **once** on create/regenerate.
- Revoke sets `revoked_at`; regenerate rotates hash.

## Assistant hardening

- System prompt forbids secrets, tokens, `.env`, webhook secrets, and cross-tenant dumps.
- Fallback path rejects secret-seeking prompts in Arabic/English heuristics.
- Product **stock** is omitted in public website context.
- Extra per-user/IP message rate limit in `AssistantController` (in addition to route `throttle:20,1`).

## Uploads

- Website assets reject SVG; MIME allow-list JPEG/PNG/WebP/GIF; size capped (~5MB in service).
- Client-side image uploader mirrors accept list + 5MB hint (server still validates).

## Ops reminders

- Never log Namecheap `ApiKey` or WhatsApp tokens.
- Webhook signature verification required in production (`WHATSAPP_APP_SECRET`, Stripe secrets).
