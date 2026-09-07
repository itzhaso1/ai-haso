# Communication Center — Phase 1 Technical Design

**Status:** design only. No migrations and no refactor until this document is approved.  
**Base:** existing Laravel conversations module inside HASEM.  
**Pattern:** same independence as POS and Finance — bounded module, shared tenancy, no new Laravel app, no microservice.

Phase 0 audit: `docs/communication-center-phase-0.md`.

---

## 0. Goal of Phase 1

Build the **Communication Core** so that Inbox, Conversation, Message, Customer Identity, Assignment, and Channel Connections are stable. After Phase 1, a new channel is a new Adapter, not a redesign.

Phase 1 is **not**: Instagram/Messenger/SMS/Telegram/Web Chat providers, Email Hub rewrite, canned-replies UI, automations, analytics dashboards, or a breaking Mobile API change.

### Independence inside HASEM

```
HASEM (Laravel monolith)
├── POS          App\Services\Pos  + routes/pos
├── Finance      App\Services\Finance + routes/finance
├── CRM          App\Models\Customer + App\Services\Customer
└── Communication Center     ← this module
       App\Services\Communication
       App\Models\Communication\*   (new)
       App\Models\Conversation|Message (kept, stable PK)
       routes: /communication/*  + existing aliases
```

CRM remains the owner of **Customer**. Communication owns **channel identities** and **conversations**. Workspace users / Spatie RBAC remain the owner of **agents**.

---

## Architecture diagram

```
                         ┌──────────────────────────────────────┐
                         │     CRM (source of truth)            │
                         │     Customer                         │
                         └─────────────────┬────────────────────┘
                                           │ customer_id
                                           ▼
                         ┌──────────────────────────────────────┐
                         │     Customer Identity                │
                         │     customer_channel_identities      │
                         │     wa / email / ig / messenger /    │
                         │     web / sms / telegram             │
                         └─────────────────┬────────────────────┘
                                           │
                                           ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                     COMMUNICATION CORE                                   │
│                                                                          │
│   Inbox query   Conversations   Messages   Assignment   Teams            │
│   Unread/UserState              Events / Audit                           │
│                                                                          │
│   Conversation ──1:*── Message                                           │
│        │                   │                                             │
│        │                   │ delivery_status pending|sent|failed         │
│        ▼                   ▼                                             │
│   ChannelConnection   MessageService                                     │
└──────────────────────────────────┬───────────────────────────────────────┘
                                   │ ChannelAdapterInterface
                                   ▼
                    ┌──────────────────────────────────┐
                    │     ChannelAdapterManager        │
                    └──────────────────────────────────┘
           ┌───────────────┬─────────────┬─────────────┬──────────────┐
           ▼               ▼             ▼             ▼              ▼
    WhatsAppAdapter  EmailAdapter  WebChatAdapter  InstagramAdapter  MessengerAdapter
    (wrap existing)  (seam only)   (stub)          (stub)            (stub)
           │               │             │             │              │
           ▼               ▼             ▼             ▼              ▼
      Meta Graph     Email Hub      —            —              —
                     IMAP/Resend
           SMS Adapter (stub)      Telegram Adapter (stub)
```

**Inbox** reads Communication Core only. Channel is a filter/source, not a separate inbox.

Email Hub stays the mail product in Phase 1. The Email Adapter is a **seam**: classes and connection rows exist; Hub jobs are not redirected yet.

---

## Locked product rules

| Rule | Phase 1 behavior |
|---|---|
| Conversation is the core entity | All inbox rows are `conversations` |
| Channel is a connector | `channel` + `channel_connection_id` |
| No duplicate Customer system | `customer_id` → CRM; identities are links |
| No automatic customer merge | Match or create identity; never merge two `customers` |
| No new User/Employee model | Agent = `workspace_users.user_id` |
| No fake Connected | Instagram/Messenger/Web Chat/SMS/Telegram = **Coming Soon** |
| Email Hub | Unchanged runtime; adapter interface + backfilled connections |
| WhatsApp | Wrap current services; do not rebuild |
| One domain layer | Web / Mobile / REST call the same Communication services |
| Mobile contract | Additive fields only |
| `migrate:fresh` | Forbidden |
| Old unique `(workspace_id, external_id)` | Kept in Phase 1 (see §13) |

---

## 1. New models

Namespace: `App\Models\Communication\*`. All extend `WorkspaceScopedModel`.

### 1.1 `ChannelConnection`

Table `channel_connections`.

A **connection** is one live (or historical) account on a provider. It is not a UI card. Count is unbounded except by plan/provider rules.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `workspace_id` | FK | required |
| `channel` | string(32) | `whatsapp`, `email`, `web_chat`, `instagram`, `facebook_messenger`, `sms`, `telegram`, `manual` |
| `display_name` | string | |
| `status` | string(32) | `connected` \| `disconnected` \| `error` \| `coming_soon` |
| `provider` | string(32) | `meta`, `imap_smtp`, `resend`, `internal`, … |
| `external_account_id` | string nullable | WABA id, mailbox address, page id |
| `credentials_ref` | string nullable | vault key — **never raw token/password** |
| `capabilities` | json nullable | `{send_text, send_media, inbound, templates}` |
| `source_type` | string nullable | morph class alias: `whats_app_account`, `email_account` |
| `source_id` | unsigned bigint nullable | existing row id |
| `connected_at` | timestamp nullable | |
| `last_error` | text nullable | |
| `metadata` | json nullable | |
| timestamps + soft deletes | | |

Indexes (short names, MySQL 64-char limit):

- `cc_ws_channel_status_idx` (`workspace_id`, `channel`, `status`)
- `cc_ws_source_idx` (`workspace_id`, `source_type`, `source_id`) unique where source is not null — implemented as unique (`workspace_id`, `source_type`, `source_id`)

No connection rows for Coming Soon channels.

### 1.2 `CustomerChannelIdentity`

Table `customer_channel_identities`.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `workspace_id` | FK | |
| `customer_id` | FK → `customers` | required |
| `channel` | string(32) | |
| `identifier` | string(191) | normalized (digits for phone, lowercase email) |
| `identifier_raw` | string(191) nullable | original provider value |
| `channel_connection_id` | FK nullable | which connection saw this identity |
| `conversation_id` | FK nullable | last/primary conversation hint (not uniqueness) |
| `match_rule` | string(64) | how this row was created — auditability |
| `matched_at` | timestamp | |
| `metadata` | json nullable | |
| timestamps | | |

Unique: `cci_ws_channel_ident_uniq` (`workspace_id`, `channel`, `identifier`).

**No unique on customer+channel** — one customer may have two WhatsApp numbers.

### 1.3 `CommunicationTeam`

Table `communication_teams`.

| Column | Type |
|---|---|
| `id` | bigint |
| `workspace_id` | FK |
| `name` | string |
| `is_default` | boolean default false |
| `metadata` | json nullable |
| timestamps + soft deletes | |

Members: `communication_team_members`

| Column | Type |
|---|---|
| `id` | bigint |
| `workspace_id` | FK |
| `team_id` | FK → `communication_teams` |
| `user_id` | FK → `users` |
| timestamps | |

Unique: (`team_id`, `user_id`).  
Member must already be an active `workspace_users` row. Enforced in service, not a second employee table.

### 1.4 Enums (PHP), not extra tables

`App\Enums\Communication\`:

- `ChannelType`
- `ChannelConnectionStatus`
- `MessageDeliveryStatus`: `pending`, `sent`, `failed`, `received`, `delivered`, `n_a`
- `ConversationPriority`: `low`, `normal`, `high`, `urgent`
- `AssignmentState`: `unassigned`, `team`, `agent`

Canned replies, automations, analytics facts: **out of Phase 1**.

---

## 2. Models to reuse (do not replace)

| Model | Change in Phase 1 |
|---|---|
| `Conversation` | additive columns + relations |
| `Message` | additive `delivery_status` |
| `ConversationUserState` | **source of truth** for unread/mute/user-archive |
| `MessageAttachment` | unchanged |
| `Customer` | add `channelIdentities()` relation only |
| `User` / `workspace_users` | agents |
| `WhatsAppAccount`, `WhatsAppPhoneNumber`, `WhatsAppOutboundMessage` | backing store for WhatsApp adapter |
| `EmailAccount`, `EmailMessage` | backing store for Email adapter (Hub untouched) |
| `AuditLog` | communication actions |
| `WebhookEvent` | WhatsApp inbound idempotency |

Do **not** move `Conversation` / `Message` into a new namespace. Appointments (`conversation_id`), WhatsApp outbound, and Flutter IDs depend on stable classes and PKs.

`App\Services\Conversation\ConversationService` becomes a **deprecated facade** that delegates to `App\Services\Communication\MessageService` / `ConversationWriter`. No fourth logic path.

`App\Services\Mobile\ConversationInboxService` delegates list/send/read to Communication services. Mobile controllers stay as HTTP adapters.

---

## 3. Database changes (additive)

One migration file (or a small ordered set), resumable with `hasColumn` / `hasTable` guards.

### 3.1 Alter `conversations`

Add:

- `channel_connection_id` nullable FK → `channel_connections` (`nullOnDelete`)
- `priority` string(16) default `normal`
- `assigned_team_id` nullable FK → `communication_teams` (`nullOnDelete`)
- `assigned_user_id` nullable FK → `users` (`nullOnDelete`)
- `assigned_at` nullable timestamp

Change:

- `channel`: enum → `string(32)` **non-destructive**. MySQL: `MODIFY channel VARCHAR(32) NOT NULL DEFAULT 'manual'`. Existing values `whatsapp|web|manual` remain valid.

Indexes:

- `conv_ws_assigned_idx` (`workspace_id`, `assigned_user_id`, `status`)
- `conv_ws_team_idx` (`workspace_id`, `assigned_team_id`, `status`)
- `conv_ws_conn_idx` (`workspace_id`, `channel_connection_id`, `last_message_at`)
- `conv_ws_channel_ext_idx` **non-unique** (`workspace_id`, `channel`, `external_id`)

**Keep** existing unique `conversations_workspace_id_external_id_unique` in Phase 1.

### 3.2 Alter `messages`

Add:

- `delivery_status` string(16) nullable
- `delivery_error` text nullable
- `delivered_at` timestamp nullable
- `channel_connection_id` nullable FK (denormalized for adapter retries)

Backfill existing rows: `delivery_status = 'sent'` for outbound/inbound already stored; `n_a` for `internal_note`. Do **not** rewrite historical outbound to `failed`.

Index: `msg_ws_delivery_idx` (`workspace_id`, `delivery_status`).

Keep unique `(workspace_id, external_message_id)`.

### 3.3 New tables

Listed in §1.

### 3.4 Not in Phase 1

- Drop of `conversations` unique on `external_id`
- Columns on `email_messages` (`conversation_id`) — mapping table can wait for Phase 4; Email Adapter interface does not need the column yet
- Token vault table (Phase 3 can add `communication_credentials`)
- `communication_canned_replies`

---

## 4. Relations

```
Workspace
  ├── Customer
  │     └── hasMany CustomerChannelIdentity
  ├── ChannelConnection
  │     ├── belongsTo morph source (WhatsAppAccount | EmailAccount)
  │     └── hasMany Conversation
  ├── CommunicationTeam
  │     └── belongsToMany User (via communication_team_members)
  └── Conversation
        ├── belongsTo Customer
        ├── belongsTo ChannelConnection (nullable)
        ├── belongsTo CommunicationTeam assignedTeam (nullable)
        ├── belongsTo User assignedUser (nullable)
        ├── hasMany Message
        ├── hasMany ConversationUserState
        └── hasMany CustomerChannelIdentity (hint)

Message
  ├── belongsTo Conversation, Customer, User
  ├── belongsTo ChannelConnection (nullable)
  └── hasMany MessageAttachment

CustomerChannelIdentity
  ├── belongsTo Customer
  ├── belongsTo ChannelConnection (nullable)
  └── belongsTo Conversation (nullable)
```

WhatsApp / Email Eloquent graphs stay as they are. Connections point at them via `source_type` + `source_id`, not by merging tables.

---

## 5. Services (single domain layer)

All under `App\Services\Communication\`.

| Service | Responsibility |
|---|---|
| `InboxQueryService` | Unified list: channel, status, assignee, team, search, unread. Used by Web + Mobile |
| `ConversationWriter` | create/update status/`ai_enabled`; never talks to Graph/IMAP |
| `MessageService` | **only** writer of `messages` (inbound, outbound, internal_note) |
| `AssignmentService` | unassigned / team / agent / reassign / priority |
| `IdentityService` | find-or-link identity; never merge customers |
| `ChannelConnectionService` | catalog + connections; status truth; backfill helpers |
| `CommunicationTeamService` | teams CRUD + membership (workspace users only) |
| `UnreadService` | read/mute/user-archive via `conversation_user_states` |
| `CommunicationAuditor` | wraps `AuditLogService` with `communication.*` actions |
| `ChannelAdapterManager` | resolve adapter by `ChannelType` |

Existing:

- `ConversationService::addMessage` → delegates to `MessageService`
- `ConversationInboxService` → `InboxQueryService` + `MessageService` + `UnreadService`
- `WhatsAppService::storeIncomingMessage` → `IdentityService` + `ConversationWriter` + `MessageService` (inbound)
- `ProcessAIResponse` → `MessageService` (outbound, `ai_generated=true`)
- Web/REST/Mobile message controllers → `MessageService` only

No business rules in controllers beyond auth, validation, HTTP mapping.

### 5.1 `MessageService` write contract

```
recordInbound(conversation, payload) → Message  delivery=received
recordInternalNote(conversation, actor, content) → Message  delivery=n_a
recordOutbound(conversation, actor, payload) → Message
    1. persist delivery=pending inside a DB transaction
    2. resolve ChannelAdapter from conversation.channel + connection
    3. if adapter cannot send (coming_soon / manual / email-not-wired):
         keep pending OR set n_a for manual notes-like channels
         (see §5.2)
    4. if adapter.send() succeeds: delivery=sent, store external_message_id
    5. if adapter.send() fails: delivery=failed, delivery_error set
       Message row remains; it is never implied successful
    6. UnreadService: inbound increments per-other-user unread by
       leaving their last_read_message_id unchanged
    7. do not zero a global metadata.unread_count as truth
    8. emit MessageCreated + ConversationUpdated
    9. FeatureAccessService consume/assert happens inside the adapter
       (whatsapp_messages / email_sends), not in the Blade view
```

Failed send **must not** be `delivery_status=sent`. HTTP for agent send: still 201 with the message resource including `delivery_status=failed` (additive). If entitlement blocks (`UsageLimitExceededException`), no message row (or row `failed` with error `limit_reached` — prefer **no consume + no Graph**; persist `failed` only after a provider attempt, or use 429/402 without a fake sent bubble). **Decision:** entitlement failure → **no message row**, exception to controller (existing AI job already skips). Provider failure after persist pending → `failed`.

### 5.2 Human WhatsApp send: recommended Phase 1 stance

The seam `MessageService → Adapter → Provider` only stays honest if **every writer** uses it.

**Default for approval: Option A**

- WhatsAppAdapter.send() wraps `WhatsAppOutboundService` + `SendWhatsAppMessage` (current working AI path).
- Web / Mobile / REST outbound is switched to `MessageService`.
- Therefore **agent WhatsApp send starts working in Phase 1** as a consequence of one pipeline, not as a WhatsApp rebuild.
- Phase 3 remaining: status webhooks (`delivered`), per-connection tokens, retry UI, Meta templates.

**Option B (only if explicitly required):** adapters exist, but Web/Mobile keep old `addMessage` (DB-only). That preserves a second write path and leaves agent send broken. Rejected unless you override Option A.

Email outbound from Inbox stays **not wired** (Email Hub compose remains the send UI). EmailAdapter.send() returns `not_wired`; Conversation reply on `channel=email` in Phase 1 is `failed` with error `email_adapter_not_wired` **or** we refuse 422 `channel_outbound_unavailable`. Prefer **422** so we do not create a pile of failed bubbles. WhatsApp is the only live outbound adapter in Phase 1.

---

## 6. Adapter interfaces

Mirror `PaymentGatewayInterface` + manager.

```php
namespace App\Services\Communication\Channels;

interface ChannelAdapterInterface
{
    public function channel(): string;

    /** Catalog capability, independent of a connection row. */
    public function catalogStatus(): string; // connected is NOT valid here; available|coming_soon

    /**
     * @return array{send_text:bool,send_media:bool,inbound:bool,templates:bool}
     */
    public function capabilities(?ChannelConnection $connection): array;

    public function send(OutboundMessageIntent $intent): ChannelSendResult;
}

final class ChannelSendResult
{
    public function __construct(
        public readonly string $status, // sent|failed|not_wired|unsupported
        public readonly ?string $externalMessageId = null,
        public readonly ?string $error = null,
        public readonly array $providerResponse = [],
    ) {}
}
```

`OutboundMessageIntent`: workspace, connection, conversation, message (pending row), recipient identifier, body, actor.

### Adapter map (Phase 1)

| Channel | Class | catalogStatus | send() |
|---|---|---|---|
| `whatsapp` | `WhatsAppAdapter` | `available` | wraps existing outbound |
| `email` | `EmailAdapter` | `available` | `not_wired` (Hub unchanged) |
| `web_chat` | `ComingSoonAdapter` | `coming_soon` | `unsupported` |
| `instagram` | `ComingSoonAdapter` | `coming_soon` | `unsupported` |
| `facebook_messenger` | `ComingSoonAdapter` | `coming_soon` | `unsupported` |
| `sms` | `ComingSoonAdapter` | `coming_soon` | `unsupported` |
| `telegram` | `ComingSoonAdapter` | `coming_soon` | `unsupported` |
| `manual` | `InternalAdapter` | n/a | `n_a` (no provider) |

`ComingSoonAdapter` is one class parameterized by channel. **No fake connected.**

WhatsApp inbound stays on `/whatsapp-webhook` → existing job → Core (`IdentityService` + `MessageService`). Adapter may expose `normalizeInbound()` later; Phase 1 does not rewrite webhook verification.

Email Adapter methods reserved for Phase 4, implemented as no-ops that log `communication.email.adapter.noop`:

- `ingestInbound(EmailMessage $row)` — not called from `ImapSyncService` yet
- `send(OutboundMessageIntent)` — returns `not_wired`

Campaigns never call Communication Core.

---

## 7. APIs

### 7.1 Compatibility (must keep)

| Surface | Routes | Change |
|---|---|---|
| Web | `workspace.conversations.*`, `conversations.messages.store` | same URLs; controllers call new services |
| REST | `/api/conversations`, `/api/messages` | same; additive JSON fields |
| Mobile | `/api/mobile/v1/conversations*` | **same paths and request bodies** |
| WhatsApp webhook | `/whatsapp-webhook` | unchanged |
| Email Hub | `workspace.emails.*`, mobile `/emails*` | unchanged |

### 7.2 Additive Mobile / REST fields (non-breaking)

Conversation resource (optional keys Flutter may ignore):

- `priority`
- `assigned_user_id`, `assigned_team_id`
- `channel_connection_id`
- `display_channel` (same as `channel` after backfill)

Message resource:

- `delivery_status` (`pending` \| `sent` \| `failed` \| …)
- `delivery_error` nullable

Existing keys (`id`, `channel`, `status`, `unread_count`, `muted`, `archived`, `content`, `direction`, `ai_generated`) stay.

Unread calculation for Mobile **must** keep using per-user state so `unread_count` remains correct.

### 7.3 New Web module routes (aliases, not replacements)

```
/communication                  → redirect Inbox
/communication/inbox            → Inbox (current conversations index)
/communication/channels         → alias of workspace.channels.index
/communication/teams            → teams CRUD (Phase 1, simple)
```

Sidebar group **Communication Center** with Inbox first. Old `المحادثات` / `Channels` links keep working.

### 7.4 New JSON endpoints (optional in Phase 1, used by Web)

All go through the same services:

- `POST /communication/conversations/{id}/assign`
- `POST /communication/conversations/{id}/read`
- `GET  /communication/teams`

Mobile assign/read already has `/read`. Assignment for mobile can wait; if added: `POST /conversations/{id}/assign` **additive**.

Do **not** remove `/channels` placeholder shape; change `connected` / `status` to match real connections (`coming_soon` for IG/Messenger, never `connected` from `workspace.settings.channels`).

---

## 8. Events

Keep:

- `App\Events\Realtime\MessageCreated` (`message.created`)
- `App\Events\Realtime\ConversationUpdated`

Dispatch from **MessageService / AssignmentService / UnreadService**, including Web (today only Mobile dispatches). Broadcasting driver stays configurable (`BROADCAST_CONNECTION=log` default). Core must not require Echo.

Add (ShouldBroadcast, same private channels):

- `ConversationAssigned`
- `ChannelConnectionStatusChanged` (workspace.conversations channel)

Audit (not broadcast), via `CommunicationAuditor`:

| action | when |
|---|---|
| `communication.message.outbound` | after send attempt (meta: delivery_status) |
| `communication.assignment.changed` | assign/reassign/unassign |
| `communication.conversation.status` | open/closed/archived |
| `communication.connection.upserted` | backfill/connect |
| `communication.identity.linked` | identity created/linked |
| `communication.team.changed` | team CRUD / members |
| `communication.ai.outbound` | AI generated send |

---

## 9. Policies

Keep `ConversationPolicy` as the gate for conversations/messages.

Tighten:

- `viewAny` / `create`: active workspace member (not `return true` in isolation — membership is already middleware; policy documents it).
- `view`: member (workspace-wide inbox in Phase 1 — assignment is routing, **not** a visibility ACL). Document as product decision: team assignment does **not** hide conversations from other members in Phase 1.
- `update` (reply, assign, status): `owner|admin|manager|agent`
- `delete`: `owner|admin|manager`

New:

- `ChannelConnectionPolicy`: view members; manage `owner|admin|manager`
- `CommunicationTeamPolicy`: view members with `update` on conversations; manage `owner|admin|manager`

`Message` authorization: always via parent Conversation (no separate MessagePolicy unless REST `messages.show` is used — then `view` on conversation). Fix REST `MessageController@show` to authorize conversation.

Feature flags:

- Module pages: plan feature `conversations` (alias `communication_center` in `config/plans.php` feature_aliases only — **do not** introduce a new flag that locks current tenants).
- WhatsApp connect/send: existing `whatsapp` + meter `whatsapp_messages` inside adapter.
- Email Hub: existing `email` + meter `email_sends` stays on Hub services.
- Do not feature-gate webhook.

---

## 10. Customer Identity architecture

```
Inbound identifier (e.g. WhatsApp wa_id)
        │
        ▼
IdentityService.resolve(workspace, channel, identifier, connection?)
        │
        ├─ 1. Exact identity row (workspace+channel+normalized identifier)
        │      → return that customer_id          match_rule=identity_exact
        │
        ├─ 2. Channel-specific lookup on Customer columns
        │      WhatsApp/SMS: customers.phone OR customers.whatsapp
        │      Email: customers.email
        │      → create identity pointing at that customer
        │        match_rule=customer_column
        │
        ├─ 3. Else create Customer (minimal name) + identity
        │      match_rule=created_from_channel
        │      (same as today’s WhatsApp firstOrCreate by phone)
        │
        └─ NEVER: merge two customers, NEVER match across channels
           (email address == whatsapp phone string is not a match)
```

Normalization:

- WhatsApp/phone: digits only, keep country code if present; do not invent +966.
- Email: `Str::lower(trim)`.
- Others: trim, store raw in `identifier_raw`.

Backfill (§13) creates identities from existing conversations + customer columns. If two customers share the same phone, **do not merge**; identity unique would collide — keep the identity on the customer already linked to the WhatsApp conversation; log `communication.identity.collision` for the other customer (manual later).

Email contacts (`email_contacts`) are **not** auto-promoted to Customer in Phase 1.

---

## 11. Assignment architecture

```
Conversation
  assigned_team_id  → CommunicationTeam | null
  assigned_user_id  → User (workspace member) | null
  priority          → low|normal|high|urgent
  status            → open|closed|archived (existing)
```

States:

| State | team | user |
|---|---|---|
| Unassigned | null | null |
| Team queue | set | null |
| Assigned agent | optional | set |
| Reassign | update + audit old/new |

Rules:

- Agent must be active `workspace_users`.
- If team set and user set, user **should** be a member of that team (warning in service; **hard-validate** in Phase 1 to avoid orphan assignments).
- Unassign: both null.
- `assigned_at` updates on any assignment change.
- Inbox filters: `unassigned`, `assigned_to_me`, `team_id`, `user_id`.

Not in Phase 1: round-robin auto-assign, SLA, assignment as visibility ACL.

---

## 12. Unread architecture

**Source of truth:** `conversation_user_states`.

| Field | Meaning |
|---|---|
| `last_read_message_id` / `last_read_at` | this user caught up |
| `muted_at` | no noise; unread can still exist |
| `archived_at` | **this user’s** archive |

Unread for user U = count of conversations where:

- not user-archived
- exists inbound (or any non-self) message with `id > last_read_message_id` (or last_read is null)

Phase 1 unread counts **inbound only** (current mobile behavior). Outbound/internal_note do not mark others unread.

### Web change

Opening Inbox **must not** write `metadata.unread_count = 0` as the read mechanism. Call `UnreadService.markRead(conversation, actor)`.

`metadata.unread_count` may remain as a **denormalized hint** updated as “inbound since last global activity” but UI and APIs must not treat it as per-user truth. Prefer stop updating it; leave old values.

### Mobile archive conflict

Today `ConversationInboxService::archive()` also sets `conversations.status = archived` (workspace-wide).

Phase 1 split:

- `archived_at` on user state = user archive (existing mobile `archived` boolean)
- `conversations.status = archived` only via explicit status update (policy)

**Compatibility:** Mobile `POST .../archive` continues to set **user** `archived_at`. Stop changing `conversations.status` so other agents do not lose the thread. This is a behavior fix, not a request-shape break. Document in mobile-api notes. Add a test that user A archive does not hide the conversation from user B’s non-archived list.

Mute remains per-user (already).

---

## 13. Migration / backfill strategy

Order:

1. Create `communication_teams`, `communication_team_members`, `channel_connections`, `customer_channel_identities`.
2. Alter `conversations` / `messages` (add columns, widen `channel`).
3. Backfill data in the same migration (chunked) **or** an Artisan command invoked by migration.

### 13.1 Channel connections

- Each `whats_app_accounts` row → `channel_connections` (`channel=whatsapp`, `provider=meta`, `external_account_id=business_account_id`, `status` mapped from account status, `source_type=whats_app_account`).
- Each `email_accounts` row → connection (`channel=email`, `provider=imap_smtp`, `external_account_id=email`, `status=connected` if not deleted). **Does not mean Email is in Unified Inbox yet.**
- No rows for Instagram/Messenger/Web/SMS/Telegram.

### 13.2 Conversations.channel

```
if channel in (whatsapp, web, manual):
    if metadata.channel_source in known channels:
        set channel = normalized(channel_source)   # instagram, facebook_messenger, email, …
    else keep channel
```

`manual` stays `manual` when source is empty/manual.

### 13.3 channel_connection_id on conversations

WhatsApp: match `metadata.phone_number_id` → `whats_app_phone_numbers` → account → connection. If missing, workspace’s latest connected WhatsApp connection (nullable if none).

Email-labelled conversations: leave `channel_connection_id` null in Phase 1 (no conversational link yet).

### 13.4 Identities

- For each customer with phone/whatsapp/email: identity rows (`match_rule=customer_column_backfill`).
- For each WhatsApp conversation `external_id`: identity `channel=whatsapp`, `identifier=normalized(external_id)`, `customer_id` from conversation, `match_rule=conversation_backfill`.
- Skip identity insert on unique collision; log and continue.

### 13.5 Unique constraint on `external_id`

**Phase 1 keeps** `(workspace_id, external_id)` unique.

Application create path for WhatsApp inbound stays compatible (`firstOrCreate` workspace+external_id). New conversations on other channels use distinct `external_id` namespaces (email address, ig id). Collision with a phone-shaped email is theoretically possible; IdentityService uses **channel** so customers stay separate; Conversation unique might block a second row with the same string.

Follow-up (not Phase 1): after a report query shows no duplicate `(workspace_id, channel, external_id)` groups that need splitting, replace unique with `(workspace_id, channel, external_id)` or `(workspace_id, channel_connection_id, external_id)`. That drop is a **separate approved migration**.

### 13.6 Rollback

`down()` drops new tables and columns only. No data rewrite on down. Do not delete WhatsApp/Email/Conversation rows.

---

## 14. Mobile compatibility strategy

| Topic | Strategy |
|---|---|
| URLs | unchanged |
| Auth / workspace header | unchanged |
| List filters `filter, channel, search, cursor` | unchanged; extra query params ignored if unknown |
| Send body `content, message_type, idempotency_key` | unchanged |
| Response `success`, `data`, cursor meta | unchanged |
| New keys | additive |
| `unread_count` | still per-user; implementation switches fully to UnreadService |
| Archive | per-user only (see §12) — **behavior fix**; contract same |
| `/channels` | `status=coming_soon` for IG/Messenger; `connected=false`; do not read `workspace.settings.channels.*.connected` |
| Idempotency | keep `metadata.idempotency_key` lookup |
| Flutter | no required app release for Phase 1 |

Tests that must stay green: `MobileConversationApiTest` (list/send/read/idempotent/tenant isolation). Add cases: per-user unread, archive isolation, `delivery_status` present, coming-soon channels.

---

## 15. Workspace isolation strategy

| Layer | Mechanism |
|---|---|
| Schema | every new table has `workspace_id` FK |
| Eloquent | `WorkspaceScopedModel` global scope + create guard |
| HTTP | `workspace.resolve` + `workspace.member` |
| Route binding | scoped models resolve in current workspace → 404 cross-tenant |
| Jobs/webhooks | WhatsApp phone_number_id → workspace; `withoutGlobalScopes` then set ids explicitly (existing pattern) |
| Identities | unique includes `workspace_id` |
| Adapters | connection loaded with workspace check before send |
| Files | `message_attachments.workspace_id` already |

Phase 1 tests:

1. User A cannot GET conversation B (existing).
2. User A cannot GET/POST identity, connection, team, message of workspace B.
3. WhatsApp inbound for phone_number_id of workspace A never writes workspace B (existing webhook tests + identity assertion).
4. Assignment cannot set `assigned_user_id` to a user who is not a member of **this** workspace.

---

## Feature / plan enforcement

- Reuse `FeatureAccessService`, `workspace_feature_flags`, `workspace_usage_meters`.
- Alias `communication_center` → `conversations` in `feature_aliases` only.
- WhatsAppAdapter calls `consumeUsage(..., 'whatsapp_messages', 1, enforce: true)` (already in `WhatsAppOutboundService` — do not double-consume).
- Email Hub meters stay in Hub services.
- No new UI-only limits.

Clear outcomes to map in MessageService/controller:

- `allowed` → persist + adapter
- `blocked` (no feature) → 403 `FeatureNotAvailableException`
- `limit reached` → 402/429 `UsageLimitExceededException`, no sent row
- `failed` → message `delivery_status=failed`

---

## Navigation (Phase 1 surface)

Communication Center (sidebar module, like Finance):

| Item | Phase 1 |
|---|---|
| Inbox | yes (existing view, new query/unread) |
| Conversations | same as Inbox (no second list) |
| Contacts / Identity | **no new CRM**; optional read-only identities later |
| Channels | yes — catalog + real connections, honest status |
| Teams & Agents | yes — teams CRUD; agents = workspace users picker |
| Assignments | yes — actions on Inbox thread |
| Templates | link hidden or “soon” — no table |
| AI | existing `ai-settings` |
| Automations | not built |
| Analytics | not built |
| Settings | channel connections + defaults |

Email Hub remains a sibling under communication **until Phase 4**, labelled as Email Hub (not “Email Inbox” of Communication Center).

---

## Phase 1 test plan

- Unique/backfill: channel widened; identities unique; no dropped conversations.
- Unread: two agents, inbound, only opener is marked read.
- Assignment: unassigned → team → agent → reassign → unassign + audit row.
- Isolation: cross-workspace 404 on new tables.
- Channels API/page: WhatsApp connected iff account+number; IG/Messenger `coming_soon` even if settings JSON says connected.
- WhatsApp webhook still 202 and stores inbound + identity.
- Mobile contract tests.
- Message outbound (Option A): fake Graph success → `sent` + outbound row; fake 400 → `failed`, not `sent`.
- Entitlement: whatsapp meter hard_block → no `sent` message.

---

## Explicitly out of Phase 1

- Instagram / Messenger / SMS / Telegram / Web Chat providers
- Email Hub rewrite; IMAP/campaign changes; `email_messages.conversation_id`
- Deleting old tables or old APIs
- Breaking Flutter request/response required fields
- CRM / POS / Finance rebuild
- Dropping `conversations(workspace_id, external_id)` unique
- Per-workspace Meta token vault (Phase 3)
- Canned replies, automations, analytics
- Assignment-based visibility ACL

---

## Approval checklist

Please confirm before implementation:

1. **Option A:** one `MessageService` pipeline; agent WhatsApp send uses WhatsAppAdapter in Phase 1.
2. Assignment is **routing**, not inbox hiding.
3. Mobile archive becomes **per-user only** (stops setting `conversations.status`).
4. Keep DB unique on `(workspace_id, external_id)` until a later approved migration.
5. Email Adapter is interface + connection backfill only; Hub runtime unchanged.
6. Instagram/Messenger stay Coming Soon with no connection rows.

After approval, Phase 1 implementation starts with additive migrations and tests first, then services, then wiring controllers, then honest Channels UI.
