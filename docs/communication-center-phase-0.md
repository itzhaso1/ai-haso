# Communication Center — Phase 0 Architecture Audit

**Status:** audit only. No migrations, no refactor, no channel adapters implemented in this phase.  
**Scope:** existing Laravel conversations module inside HASEM (not a new app, not a microservice).  
**Constraint:** Conversation is the core entity. Channel is a connector. CRM `Customer` remains the customer source of truth.

This document is the gate for Phase 1. Implementation starts only after explicit approval of the conflicts listed in §13.

---

## 0. Verdict

HASEM already has a **working conversations module**, a **working WhatsApp inbound webhook**, a **working Email Hub**, and a **working mobile inbox API**. It does **not** yet have a Communication Center.

What exists today is three loosely related surfaces:

1. **Conversation Inbox** (`conversations` + `messages`) — used by Web and Mobile.
2. **WhatsApp provider stack** (accounts, phones, webhook, outbound job) — inbound works; human outbound from UI does not send to Meta.
3. **Email Hub** (`email_*` tables) — independent IMAP/SMTP/Resend product. Not a conversation channel.

The UI already *looks* omnichannel (Instagram / Messenger / Email filters). The backend does not. Instagram and Messenger are placeholders. Email conversations in the inbox are labels on `channel=manual`, not real mail. Web Chat is an enum value only.

The independent-module pattern to copy is **Finance / POS**: same Laravel app, own routes/services/UI, shared tenancy/RBAC/plans, no duplicated Customer or User systems.

---

## 1. Current architecture

### 1.1 Placement inside HASEM

Communication is a **sidebar module**, not an independent system like Finance:

```
Sidebar "التواصل"
  ├── المحادثات     workspace.conversations.*
  ├── Channels      workspace.channels.*
  ├── البريد        workspace.emails.*     (feature: email)
  └── واتساب        workspace.whatsapp-accounts.*  (feature: whatsapp)
```

Conversations web routes are **not** gated by `workspace.feature:conversations`. WhatsApp connect is gated by `whatsapp`. Email Hub is gated by `email`.

There is no Communication domain namespace. Controllers live under `App\Http\Controllers\Workspace\*`. Services are split:

| Layer | Location |
|---|---|
| Conversation core | `App\Services\Conversation\ConversationService` |
| Mobile inbox | `App\Services\Mobile\ConversationInboxService` |
| WhatsApp | `App\Services\WhatsApp\*` |
| Email | `App\Services\Email\*` |
| AI | `App\Services\AI\AIService` |
| Plans | `App\Services\Feature\FeatureAccessService` |

Business logic is **already partly in services**, but web/REST controllers still call `ConversationService.addMessage` while mobile uses a second service. Outbound channel send is **not** in the conversation core.

### 1.2 What actually works (verified in backend, not from UI copy)

| Capability | Reality |
|---|---|
| Create / list / open conversations | Yes (web + REST + mobile) |
| Store inbound/outbound/internal_note in `messages` | Yes |
| WhatsApp Embedded Signup | Yes — persists WABA + phone numbers |
| WhatsApp inbound webhook | Yes — signature, idempotent `webhook_events`, queue job |
| WhatsApp auto-create Customer by phone | Yes (`firstOrCreate` on `workspace_id + phone`) |
| WhatsApp Graph outbound | Yes — **only** from `ProcessAIResponse` via `WhatsAppOutboundService` |
| Human reply from Web/Mobile/REST → WhatsApp | **No.** DB insert only |
| Email IMAP sync + compose + campaigns | Yes, separate tables |
| Email → Conversation | **No** |
| Instagram / Messenger integration | **No** (UI cards + `metadata.channel_source` labels) |
| Web Chat widget / realtime visitor | **No** (`channel=web` exists as enum) |
| Assignment to agent/team | **No columns, no APIs** |
| Per-user unread on mobile | Yes (`conversation_user_states`) |
| Per-user unread on web | **No** (global `metadata.unread_count`) |
| Canned replies | **No** (`smart_replies` is AI; `whatsapp_templates` unused) |
| Broadcasting to browser | Events exist; default `BROADCAST_CONNECTION=log` |
| Tenant isolation | Global scope + mobile test exists |

### 1.3 Target shape (approved direction, not yet built)

```
CRM Customer
    └── CustomerChannelIdentity[]   (whatsapp / email / ig / …)
            └── Conversation        (core)
                    ├── Messages (inbound | outbound | internal_note)
                    ├── Assignment (team + agent = workspace users)
                    ├── Per-user state (read / mute / archive)
                    └── ChannelConnection → Channel Adapter → Provider
```

Adapters (future): WhatsApp, Email, Web Chat, Instagram, Messenger, SMS, Telegram.  
Inbox and Conversation Core must not change when a new adapter is added.

---

## 2. Models and tables today

### 2.1 Conversation core

**`conversations`** (`2026_08_18_151007`)

| Column | Notes |
|---|---|
| `workspace_id` | tenant |
| `customer_id` | nullable FK → `customers` |
| `channel` | **enum `whatsapp \| web \| manual` only** |
| `external_id` | unique per workspace (`workspace_id + external_id`) |
| `status` | enum `open \| closed \| archived` |
| `ai_enabled` | bool, default true |
| `last_message_at` | timestamp |
| `metadata` | JSON: `channel_source`, `unread_count`, `phone_number_id`, … |
| timestamps + soft deletes | |

Missing vs target Conversation model: `channel_connection_id`, `priority`, `assigned_team_id`, `assigned_user_id`, first-class unread, typed channel.

**`messages`** (`2026_08_18_151008`)

| Column | Notes |
|---|---|
| `workspace_id`, `conversation_id`, `customer_id`, `user_id` | |
| `direction` | enum `inbound \| outbound \| internal_note` — already correct |
| `message_type` | enum `text \| image \| file \| system` |
| `content` | longText |
| `external_message_id` | unique per workspace |
| `ai_generated` | bool |
| `metadata` | JSON (raw WhatsApp payload, idempotency_key, …) |

Missing: delivery/status (`pending \| sent \| failed \| delivered`), channel metadata as structured field, attachment always via child table. No `MessagePolicy`.

**`conversation_user_states`** (mobile foundation migration)

Per `(conversation_id, user_id)`: `last_read_message_id`, `last_read_at`, `muted_at`, `archived_at`.  
Used by mobile. **Not used by web inbox.**

**`message_attachments`**

Workspace-scoped files (`disk`, `path`, `kind`). Mobile upload API exists.

### 2.2 WhatsApp provider tables

| Table | Role |
|---|---|
| `whats_app_accounts` | WABA: `business_account_id` **globally unique**, status pending/connected/disconnected/error |
| `whats_app_phone_numbers` | `phone_number_id` **globally unique** |
| `whatsapp_outbound_messages` | queue/send/fail log: queued/sending/sent/failed/delivered |
| `whatsapp_templates` | Meta-style templates — **model exists, no writers/readers** |

Access token from Embedded Signup is **exchanged then discarded**. Outbound uses env `WHATSAPP_PERMANENT_TOKEN` (`config/services.php`), not a per-connection secret.

### 2.3 Email Hub (independent product)

| Table | Role |
|---|---|
| `email_accounts` | IMAP/SMTP identity; `password` encrypted cast |
| `email_messages` | inbound/outbound mail; `thread_key`, `message_id`, delivery_status |
| `email_attachments` | **no `workspace_id`** (child of email_messages) |
| `email_contacts` | address book; **no FK to `customers`** |
| `email_campaigns` / logs | campaigns — not conversations |

### 2.4 CRM (do not duplicate)

`customers` is the hub: phone, whatsapp, email, `last_conversation_at`, tags, groups, notes, orders, finance invoices, appointments.

WhatsApp inbound creates customers by phone. Email Hub stores `email_contacts` separately. Same person can exist twice.

### 2.5 Reuse vs new

**Reuse as-is**

- `conversations`, `messages`, `conversation_user_states`, `message_attachments`
- `customers` + existing CRM relations
- `workspace_users` + Spatie roles (`owner`, `admin`, `manager`, `agent`, …)
- WhatsApp account/phone/outbound/webhook stack
- Email Hub tables and UI
- `FeatureAccessService`, `workspace_feature_flags`, `workspace_usage_meters`
- `audit_logs` / `AuditLogService`
- `AIService`, `AiSetting`, `AiLog`
- Realtime event classes

**Do not reuse as the channel abstraction**

- `conversations.channel` enum (too narrow)
- `metadata.channel_source` as the source of truth
- `metadata.unread_count` as unread
- `email_contacts` as CRM identity
- `whatsapp_templates` as canned replies
- `finance_employees` / `appointment_staff` as agents

**New entities (proposed for later phases, not created now)**

| Entity | Why not a random column |
|---|---|
| `channel_connections` | Generic connection registry; WhatsApp/Email rows become provider-specific backing data |
| `customer_channel_identities` | WhatsApp wa_id / email / IG sid mapped to one `customer_id` |
| `communication_canned_replies` | Human templates, not AI, not Meta WA templates |
| Assignment columns **or** `conversation_assignments` | Team + agent history |
| `communication_teams` + members | Optional grouping of **existing** workspace users — not a fourth employee model |
| Message `delivery_status` | pending/sent/failed without pretending success |

---

## 3. Current relations

```
Workspace
  ├── Customer 1──* Conversation 1──* Message 1──* MessageAttachment
  │                    └── * ConversationUserState (per User)
  ├── WhatsAppAccount 1──* WhatsAppPhoneNumber
  │                    └── * WhatsAppTemplate (unused)
  ├── WhatsAppOutboundMessage → Conversation?, Message?, PhoneNumber
  ├── EmailAccount 1──* EmailMessage 1──* EmailAttachment
  ├── EmailContact                    (no Customer FK)
  └── AppointmentRequest.conversation_id (nullable)

Conversation.customer_id → Customer
Message.user_id → User (agent sender)
Message.customer_id → Customer
```

**Not present:** Conversation → Team, Conversation → assigned User, Conversation → ChannelConnection, Customer → Identities, Message → delivery row except WhatsApp outbound sidecar.

**Uniqueness traps**

- `conversations (workspace_id, external_id)` unique → one thread per external id, **not** per channel. WhatsApp uses phone as `external_id`. A future email thread cannot safely share that key space.
- `messages (workspace_id, external_message_id)` unique → OK if IDs are globally unique per provider; collisions across providers are possible if we store raw ids without prefix.
- `whats_app_accounts.business_account_id` unique globally → same WABA cannot belong to two workspaces (already enforced in signup).

---

## 4. Current APIs

### 4.1 Web (Blade)

| Route | Behavior |
|---|---|
| `CRUD conversations` | list/filter/search, create, update status/AI, delete |
| `POST conversations/{id}/messages` | `ConversationService.addMessage` then redirect “تم إرسال الرسالة” |
| `GET channels` | four hardcoded cards |
| `POST channels/whatsapp/connect` | Embedded Signup |
| `emails/*` | full Email Hub |

Web inbox filters: search + channel only. No status / agent / team filter. Opening a conversation **zeros global `metadata.unread_count`**. Reply form lets the user pick `inbound` as direction (data-quality hole).

### 4.2 REST `/api` (`routes/api.php`)

`apiResource conversations` + `messages` index/store/show.  
Same `ConversationService.addMessage`. No WhatsApp send. No assignment. No per-user read.

### 4.3 Mobile `/api/mobile/v1`

Used by Flutter `apps/hasim/lib/features/conversations/`:

| Method | Path |
|---|---|
| GET | `/conversations` (filter, channel, search, cursor) |
| GET | `/conversations/{id}` |
| GET | `/conversations/{id}/messages` |
| POST | `/conversations/{id}/messages` (idempotency_key) |
| POST | `/conversations/{id}/read` |
| POST | `/conversations/{id}/archive` |
| POST | `/conversations/{id}/mute` |
| POST | `/messages/{id}/attachments` |
| GET | `/channels` (same placeholder cards) |
| GET | `/customers/{id}/conversations` |
| POST | `/ai/suggest-reply`, `/ai/summarize-conversation` |
| GET/POST | `/emails/*`, campaigns, contacts (separate product) |

Mobile send path: `ConversationInboxService.sendMessage` → `ConversationService.addMessage` → broadcast events. **Still no channel adapter.**

### 4.4 Webhooks

| Route | Auth |
|---|---|
| `GET/POST /whatsapp-webhook` | verify token + `X-Hub-Signature-256` |
| `POST /webhooks/resend` | Email delivery (Email Hub, not conversations) |

### 4.5 API design gap

Three HTTP surfaces, two service classes, one core `addMessage`. Channel send, assignment, identities, and templates are missing from all of them. Phase 1+ must put domain logic in services and keep controllers thin — **do not add a fourth copy**.

---

## 5. WhatsApp flow

### 5.1 Connect

```
Channels UI Embedded Signup
  → ChannelController.connectWhatsApp
  → WhatsAppEmbeddedSignupService
  → Graph oauth/access_token
  → persist WhatsAppAccount + WhatsAppPhoneNumber
  → access token NOT stored
```

### 5.2 Inbound (working — must not break)

```
POST /whatsapp-webhook
  → signature check (fails closed if no app secret)
  → WhatsAppService.processWebhook
  → resolve phone_number_id → workspace
  → webhook_events firstOrCreate (idempotent)
  → ProcessIncomingWhatsAppMessage
  → storeIncomingMessage:
        Customer firstOrCreate(workspace_id, phone)
        Conversation firstOrCreate(workspace_id, external_id=wa_id)
        Message inbound + external_message_id
        metadata.phone_number_id, unread_count++
  → if conversation.ai_enabled → ProcessAIResponse
```

Webhook currently processes **messages only**. Status webhooks (`sent` / `delivered` / `read`) are ignored. `whatsapp_outbound_messages.delivered_at` is never filled from Meta.

### 5.3 AI outbound (partial)

```
ProcessAIResponse
  → AIService.generateReply (feature + meters)
  → Message::create outbound ai_generated=true   ← persisted first
  → maybeDispatchWhatsAppOutbound
        requires channel==whatsapp AND metadata.phone_number_id AND external_id
  → WhatsAppOutboundService.sendText
        assert feature whatsapp
        consumeUsage whatsapp_messages enforce=true
        row status=queued
        SendWhatsAppMessage job
  → Graph POST /{phone_number_id}/messages with WHATSAPP_PERMANENT_TOKEN
```

If Graph fails, the **conversation `messages` row still looks like a successful reply**. Only the sidecar outbound row is `failed`. This violates the requested pending/failed rule.

### 5.4 Human outbound (the reported gap — confirmed)

```
Web / REST / Mobile
  → ConversationService.addMessage (outbound)
  → DB commit
  → HTTP 201 / flash "تم إرسال الرسالة"
  → WhatsAppOutboundService: never called
```

### 5.5 Token / retry / status

- Retry exists on the job (`tries=3`, backoff 30/120/300) for the sidecar table only.
- No mapping of Graph message id back onto `messages.external_message_id` from the human path (human path never sends).
- AI path may link `message_id` on the outbound row but does not write `external_message_id` on success.

---

## 6. Email flow

### 6.1 Independent Hub (keep)

```
Connect IMAP/SMTP account (encrypted password)
  → SyncEmailInboxJob / ImapSyncService → email_messages inbound
Compose
  → WorkspaceEmailSender → CentralEmailService (Resend)
  → email_messages outbound + EmailLog
Campaigns (mobile)
  → EmailCampaignService  ≠  one conversation per recipient
```

Web `EmailController::sendMessage` does **not** call `consumeUsage(email_sends)`. Mobile send and campaigns do (campaigns with `enforce: false`).

### 6.2 Relation to conversations

**None.** No `conversation_id` on `email_messages`. Inbox filter `channel=email` only matches `conversations.channel=manual` + `metadata.channel_source=email`.

### 6.3 Integration design (Phase 4 — do not execute now)

Keep Email Hub tables and UI. Add an **Email Adapter** later:

```
Inbound EmailMessage (conversational, not campaign)
  → find-or-link CustomerChannelIdentity(email)
  → find-or-create Conversation(channel=email, connection=email_account)
  → Message inbound (reference email_message_id in metadata)

Conversation outbound reply
  → Email Adapter → existing WorkspaceEmailSender
  → persist delivery on Message + keep email_messages row
```

Campaigns stay out of Conversation Core. Matching email → CRM customer must be explicit (address equality), never silent merge of two `customers` rows.

---

## 7. Mobile app dependencies

Flutter app `apps/hasim`:

- `ConversationRepository` talks only to `/api/mobile/v1/conversations*`.
- List filters: `all | unread | archived`, optional `channel`, search.
- Send: `content` + `idempotency_key`. Expects 201 + message resource.
- Read / archive / mute hit dedicated endpoints.
- Channels screen consumes `/channels` boolean `connected` (Instagram/Messenger can show connected from `workspace.settings.channels.*` without any API).
- Email is a **separate** feature (inbox/sent/campaigns/contacts).
- AI suggest/summarize are separate endpoints; suggest refuses `persist=true`.

**Compatibility rules for later phases**

- Do not rename or remove these routes.
- Additive fields on resources are OK.
- Changing send to return `pending/failed` is a **contract change** — must remain backward compatible (still return a message id; add `delivery_status`).
- Mobile `archive` currently also sets `conversations.status = archived` (workspace-wide). That conflicts with per-user archive. Phase 1 must not silently change this without a mobile-aware plan.

---

## 8. CRM dependencies

| Direction | Today |
|---|---|
| Conversation → Customer | `customer_id` FK |
| WhatsApp inbound → Customer | create by phone + set `whatsapp` |
| Customer → Conversations | `Customer::conversations()`, mobile `GET /customers/{id}/conversations` |
| Appointments → Conversation | `appointment_requests.conversation_id` |
| Email contact → Customer | **none** |
| Identity graph | **none** — phone/email/whatsapp are columns on `customers` |

**Do not create a Communication Customer.**  
Add `customer_channel_identities` later:

- `workspace_id`, `customer_id`, `channel`, `identifier` (normalized), `channel_connection_id?`, `verified_at`, `metadata`, unique `(workspace_id, channel, identifier)`.

Matching rules (Phase 1 design, conservative):

1. Exact identifier on same channel + workspace → reuse identity and its customer.
2. WhatsApp inbound: existing `customers.phone` / `whatsapp` match → link identity, do not create a second customer.
3. Email inbound (later): match `customers.email` or existing identity; if only `email_contacts` exists, **do not** auto-promote to Customer without an explicit rule.
4. Never merge two `customers` rows automatically. Offer a traced “link identity” / “suggest merge” action later.

---

## 9. RBAC dependencies

- Spatie teams = workspace. Roles: `owner`, `admin`, `manager`, `agent`, plus appointments/finance roles.
- Permission `conversations.manage` exists on plans/seeders.
- `ConversationPolicy`:
  - `viewAny` / `create`: always true (any authenticated caller that reached the route).
  - `view`: workspace membership.
  - `update` (reply): owner/admin/manager/**agent**.
  - `delete`: owner/admin/manager.
- No Message policy; no ChannelConnection policy.
- Web conversation routes: membership middleware only, **no feature flag**.
- Agents should remain `workspace_users`. Do not add `communication_employees`.

Gap: no “assigned agent only” visibility. Any member who can `view` sees all conversations. Team inbox privacy is a Phase 1 product decision (default: keep workspace-wide view for members; restrict later if needed).

---

## 10. What must change

Ordered by dependency, not by UI appeal.

1. **Treat Conversation as channel-agnostic core.** Stop encoding Instagram/Email as `channel=manual` + metadata. Widen `channel` to a string (or lookup) without dropping existing rows.
2. **Channel Connections** as real records with status `available | connected | disconnected | error | coming_soon`. Stop hardcoded 4-card UI as the source of truth.
3. **Channel Adapter interface** in domain layer. WhatsApp adapter wraps existing outbound/inbound. Email adapter later wraps Email Hub. Placeholders stay `coming_soon`.
4. **Human outbound** must go through Message Service → Adapter. WhatsApp UI reply must call `WhatsAppOutboundService` (or successor). Persist `pending` then `sent/failed`. Do not mark success if Graph fails.
5. **Store per-connection credentials encrypted.** Stop relying solely on `WHATSAPP_PERMANENT_TOKEN` for multi-tenant sending.
6. **Customer channel identities** with auditable matching. Stop creating duplicate customers when identifier already maps.
7. **Assignment** using existing users/roles: unassigned / team / agent, reassign, status, priority.
8. **Unread** unify on `conversation_user_states`. Web must stop mutating a global counter as the truth. Opening a chat must not mark others as read.
9. **Canned replies** as their own entity. Not AI `smart_replies`, not `whatsapp_templates`.
10. **AI as a layer on Core**, not WhatsApp-only. `ProcessAIResponse` should call the same outbound pipeline as humans, for any connected channel that allows AI send, with permission + meter + `ai_generated` + audit.
11. **Wire realtime** from conversation core (not only mobile). Keep provider configurable; default log must not block development, but architecture must not depend on polling.
12. **Enforce meters** on every send path (web email, web WhatsApp, AI, mobile).
13. **Audit** assignment, status, outbound, connection, template, AI auto-send.
14. **Navigation**: Communication Center with Inbox as home — Channels is settings, not the product.
15. **Feature-gate** communication module consistently without taking inbox away from current plans that already include `conversations`.

---

## 11. What must be preserved

- Laravel monolith module (Finance/POS pattern). No new Laravel app. No external microservice.
- All existing `conversations` / `messages` / WhatsApp / Email rows.
- WhatsApp webhook URL, signature, idempotency, inbound job.
- Embedded Signup connect path.
- `WhatsAppOutboundService` + `SendWhatsAppMessage` + usage meter (extend, don’t replace blindly).
- Email Hub (IMAP sync, compose, contacts, campaigns, Resend webhook).
- Mobile conversation routes and Flutter repository contract.
- REST conversation/message endpoints (additive only).
- CRM `Customer` as source of truth.
- `workspace_id` + `WorkspaceScopedModel` + membership.
- Spatie RBAC and `agent` role.
- AI providers, `ai_enabled` per conversation, suggest/summarize APIs.
- Appointments `conversation_id` link.
- Feature flags `whatsapp`, `email`, `ai`, `conversations`, `smart_replies` and meters `whatsapp_messages`, `email_sends`, `ai_usage` / `ai_tokens`.
- Existing tests: `OmnichannelInboxTest`, `WhatsAppWebhookRouteTest`, `WhatsAppOutboundTest`, `MobileConversationApiTest`, `EmailInboxHubTest`. Extend them; don’t delete coverage.

---

## 12. Migrations required (proposed — not applied)

All additive / backward-compatible. No drops in Phase 1.

### Phase 1 (Communication Core)

1. **`conversations`**
   - Change `channel` from enum to `string` (keep current values).
   - Add nullable `channel_connection_id`, `priority`, `assigned_user_id`, `assigned_team_id`.
   - Add new unique `(workspace_id, channel, external_id)` **or** `(workspace_id, channel_connection_id, external_id)` **in addition to** existing unique, then stop writing collisions. Do **not** drop `(workspace_id, external_id)` until data is proven unique under the new key (WhatsApp phones today occupy that unique).
2. **`channel_connections`**
   - `workspace_id`, `channel` (whatsapp/email/web/…), `display_name`, `status`, `provider`, `external_account_id`, `credentials_ref`, `capabilities` JSON, `connected_at`, `last_error`, `source_type/source_id` (polymorphic to existing WhatsAppAccount / EmailAccount).
   - Backfill one connection per existing connected WhatsApp account and each Email account. Instagram/Messenger: **no connected rows**.
3. **`customer_channel_identities`**
   - Backfill from `customers.phone` / `whatsapp` / `email` and from WhatsApp conversation `external_id`. No auto-merge of distinct customers.
4. **Assignment**
   - Prefer columns on `conversations` + `conversation_assignment_events` (or `audit_logs`) for history. Avoid a second employee table.
5. **`communication_teams` + `communication_team_members`**
   - Only if Inbox team queue is required. Members are `users.id` already in `workspace_users`.
6. **`messages`**
   - Add `delivery_status` (`pending|sent|failed|delivered|skipped`) default `sent` for existing rows (historical human “sent” that never left the building should **not** be rewritten to failed).
   - Optionally prefix-safe unique on `external_message_id` later; don’t break current unique.
7. Keep `conversation_user_states`; web starts using it. No second unread table.

### Phase 3 (WhatsApp)

8. Encrypted token store referenced by `channel_connections.credentials_ref` (not plaintext JSON).
9. Optional: webhook status handler updates outbound + `messages.delivery_status`.
10. Do **not** delete `whatsapp_outbound_messages`; it remains the provider outbox.

### Phase 4 (Email)

11. Nullable `conversation_id` / `customer_id` on `email_messages` **or** a mapping table `email_message_conversation_links` (safer, no Email Hub breakage).
12. Optional `email_contacts.customer_id` nullable — linking, not merging.

### Phase 6

13. `communication_canned_replies` (`workspace_id`, name, body, channel nullable, created_by).
14. Leave `whatsapp_templates` for Meta template sends.

**Forbidden in any phase without an explicit destructive plan:** drop email tables, drop WhatsApp tables, auto-merge customers, change webhook path, `migrate:fresh`.

---

## 13. Implementation risks (must acknowledge before Phase 1)

| Risk | Why it matters | Mitigation |
|---|---|---|
| **Email dual-inbox** | Unifying mail into conversations immediately breaks campaigns, IMAP UI, mobile email | Adapter + optional link; Hub stays canonical for mail until Phase 4 |
| **`channel` enum + unique `external_id`** | Blocks real omnichannel uniqueness | Widen channel; add new unique; keep old unique until backfill |
| **Human outbound “success” in DB** | Changing to pending/failed changes UX and mobile contract | New messages: pending→sent/failed. Old rows unchanged |
| **AI reply persisted before Graph send** | Failed AI WhatsApp still shows as sent | Same pending pipeline as humans |
| **Discarded Meta user tokens** | Multi-workspace send cannot work with one env token | Credentials vault in Phase 3; env token remains fallback |
| **Global WABA/phone uniqueness** | Reconnect / agency models | Keep; surface clear errors (already thrown) |
| **Auto-create Customer by phone** | Duplicates when email/IG arrive later | Identities + match rules; no merge |
| **Web unread vs mobile unread** | Fixing web globally-clears unread | Switch web to per-user state; stop writing `metadata.unread_count` as truth (can keep as denormalized hint) |
| **Mobile archive sets conversation.status** | Per-user archive vs workspace status | Split: user archive vs conversation status; additive API field |
| **Placeholder “Connected”** | Settings JSON can mark IG/Messenger connected | Status enum; ignore settings flags for those channels |
| **UI copy claims Email is in inbox** | False | Don’t delete Email Hub; don’t claim unification until adapter ships |
| **Three APIs** | Logic drift | One domain service; controllers stay facades |
| **Feature flag surprise** | Gating conversations routes could lock existing tenants | Gate new pages; keep inbox available if plan has `conversations` |
| **Broadcast default `log`** | Building Echo-only UI would “not work” in default env | Core emits events; UI degrades without WS |
| **No MessagePolicy** | Cross-tenant message show on REST | Authorize via conversation workspace |
| **`email_attachments` without workspace_id** | Isolation depends on parent | Don’t query attachments without joining message/account |
| **Canned vs Meta vs AI** | Easy to conflate three template systems | Three names, three tables/features |
| **Appointments conversation_id** | Core refactor could null FKs | Keep conversation PK stable |
| **Meter gaps** | Web email / web WhatsApp unmetered | Enforce in send services, not UI |

**Conflicts that need a product decision (not guessed in Phase 1):**

1. Should team assignment hide conversations from non-members, or is it routing only?
2. When Email adapter lands, is Email Hub inbox still the primary mail UI?
3. AI auto-send: keep current default `ai_enabled=true` on WhatsApp inbound, or require explicit opt-in?
4. Credential model: platform-level Meta system user vs per-workspace token?

---

## 14. Phased plan

### PHASE 0 — Architecture audit (this document)

No code. Approval gate.

### PHASE 1 — Communication Core

- Additive schema: connections registry (backfill WhatsApp/Email), identities, assignment columns, message delivery_status, teams-as-user-groups if approved.
- Domain services: Conversation, Message, Assignment, Identity matching (conservative), ChannelConnection status.
- Policies aligned with existing roles.
- Unread: web + API use `conversation_user_states`.
- Audit events for assignment/status.
- **No** Instagram/Messenger/Web Chat adapters.
- **No** change to webhook contract.
- Tests: tenant isolation, assignment, unread per user, identity match-without-merge.

### PHASE 2 — Unified Inbox UI

- Navigation: Communication Center → Inbox first.
- Filters: channel, status, agent, team, search.
- Reply, internal note, status, assign, customer panel (CRM read-only).
- Channels page reads `channel_connections` (Coming Soon for unimplemented).
- Do not remove Email Hub from sidebar until Phase 4 decision.

### PHASE 3 — WhatsApp complete

- UI/mobile/REST outbound → adapter → Graph.
- pending/failed/retry, map `external_message_id`, optional status webhook.
- Encrypted token reference; keep webhook.
- Meter enforced on human send.
- Regression tests on webhook + outbound.

### PHASE 4 — Email integration

- Conversational inbound/outbound adapter.
- Identity link by email.
- Campaigns remain outside Conversation.
- Close web `email_sends` meter gap even before full adapter.

### PHASE 5 — Additional channels (each as its own adapter)

- Web Chat: widget, visitor identity, session, realtime — only when specified.
- Instagram / Messenger: real Graph integration or stay Coming Soon.
- SMS / Telegram: architecture stubs, no fake Connected.

### PHASE 6 — AI / Templates / Automation / Analytics

- AI on any reply-capable channel; opt-in auto-send; audit.
- Canned replies CRUD + insert in Inbox.
- Automations later.
- Analytics from conversation/message facts, not UI cards.

---

## Success criteria (after later phases, not now)

A workspace user can:

Customer (CRM) → Conversation → Channel connection → Messages → Team → Agent → Reply on the live channel.

The same customer can hold multiple channel identities. Adding a channel does not redesign Inbox or Conversation Core.

---

## Phase 1 start condition

Phase 1 starts only after approval of this audit, especially:

- Email Hub remains independent until Phase 4.
- No destructive migrations.
- Instagram/Messenger remain Coming Soon.
- Agents = existing workspace users.
- Human WhatsApp send is Phase 3 (core in Phase 1 must make the adapter seam, not ship Graph from UI yet unless explicitly pulled forward).

Recommended: **do not pull WhatsApp UI-send into Phase 1**. Phase 1 builds the seam (`MessageService` → `ChannelAdapter` no-op/log for unsupported channels). Phase 3 wires WhatsApp. Mixing them risks breaking the webhook while the identity/assignment model is still moving.
