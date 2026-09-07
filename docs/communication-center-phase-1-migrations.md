# Communication Center Phase 1 — Migration uniqueness design

This is the locked schema plan for uniqueness. It is **additive** and **not** `migrate:fresh`. The old `unique(workspace_id, external_id)` is **not** the final design.

## Target uniqueness

Conversation identity is scoped to a **Channel Connection**, not to the workspace alone.

Equivalent of:

```
unique(workspace_id, channel_connection_id, external_id)
```

MySQL/SQLite cannot enforce that cleanly when `channel_connection_id` is NULL (NULL ≠ NULL in unique indexes, so two “unconnected” rows could share the same `external_id`).

Portable equivalent used in this phase:

**`identity_key`** (sha256, nullable)

```
if external_id is empty → identity_key = NULL (not indexed as a value)

if channel_connection_id is set
    identity_key = sha256("conn:{channel_connection_id}|{external_id}")

else
    identity_key = sha256("channel:{channel}|{external_id}")
```

**Constraint:** `unique(workspace_id, identity_key)` name `conv_ws_identity_key_uniq`.

This allows:

- Same WhatsApp `wa_id` on **two** phone-number connections → two conversations.
- Same string on WhatsApp vs Email (different connection or different channel) → two conversations.
- Manual/unconnected rows still unique per `(workspace, channel, external_id)`.
- Multiple rows with `external_id = NULL` remain allowed.

`customer_channel_identities` uniqueness (separate graph):

```
unique(workspace_id, channel, identifier)  →  cci_ws_channel_ident_uniq
```

One person may have two WhatsApp numbers (two identity rows). Two customers may **not** silently share the same channel identifier; collisions are logged, never merged.

---

## ChannelConnection as a real entity

Not a UI card. One row = one live provider endpoint.

| Source | Connection grain |
|---|---|
| WhatsApp | **one connection per `whats_app_phone_numbers` row** (the Graph `phone_number_id`) |
| Email Hub | **one connection per `email_accounts` row** (seam only; Hub runtime unchanged) |
| Instagram / Messenger / Web Chat / SMS / Telegram | **no rows** — catalog `coming_soon` |

`conversations.channel_connection_id` is set when the thread comes from an external connection (WhatsApp inbound/outbound). Manual threads may leave it NULL.

---

## Migration order

| # | File | What |
|---|---|---|
| 1 | `2026_09_07_100000_create_communication_center_tables` | `communication_teams`, `communication_team_members`, `channel_connections`, `customer_channel_identities`, `communication_backfill_issues` |
| 2 | `2026_09_07_100100_add_communication_core_columns` | Widen `conversations.channel` to `VARCHAR(32)`; add assignment, `channel_connection_id`, `identity_key`, message `delivery_status`. **Keep** `conversations_workspace_id_external_id_unique`. |
| 3 | `2026_09_07_100200_backfill_communication_center` | Detect issues → write `communication_backfill_issues` → fill connections, channels, connection_ids, identity_keys, identities, delivery_status. No customer merge. No conversation split. |
| 4 | `2026_09_07_100300_add_conversation_identity_key_unique` | Add `conv_ws_identity_key_uniq` **after** backfill. |
| 5 | `2026_09_07_100400_drop_legacy_conversation_external_id_unique` | Verify, then **drop only the old unique index**. No row deletes. |

Step 5 is an index drop, not a data delete. It runs only if verification passes; otherwise `up()` throws and the deploy stops with the old unique still in place (because 5 has not committed).

---

## Backfill strategy

1. **Detect (no writes to business rows yet)**  
   - Duplicate `(workspace_id, channel, normalized identifier)` across **different** `customers` (phone / whatsapp / email columns).  
   - WhatsApp conversations whose `metadata.phone_number_id` does not match a phone in the same workspace.  
   - Workspaces with WhatsApp conversations and **multiple** phone numbers and no `phone_number_id` on the conversation (ambiguous).  
   Write each as `communication_backfill_issues` (`issue_type` + JSON payload). **Do not merge customers. Do not split conversations.**

2. **Create connections**  
   - Each WhatsApp phone number → `channel_connections` (`channel=whatsapp`, `provider=meta`, `source_type=whats_app_phone_number`).  
   - Each email account → `channel=email`, `provider=imap_smtp`, `source_type=email_account`.  
   Idempotent on `(workspace_id, source_type, source_id)`.

3. **Conversations.channel**  
   If `metadata.channel_source` is a known channel, copy it onto `channel` (instagram, facebook_messenger, email, …). Else keep `whatsapp|web|manual`.

4. **channel_connection_id**  
   WhatsApp: match `metadata.phone_number_id` → phone → connection.  
   If missing and the workspace has exactly one WhatsApp phone connection, attach it.  
   If missing and several phones: log `ambiguous_connection`, leave NULL (channel-scoped identity_key).  
   Email-labelled conversations: leave NULL (Hub not wired).

5. **identity_key** for every conversation with `external_id`.

6. **Identities**  
   From customer phone/whatsapp/email columns (`match_rule=customer_column_backfill`).  
   From WhatsApp conversation `external_id` (`match_rule=conversation_backfill`).  
   Unique collision → issue row, skip insert.

7. **messages.delivery_status**  
   `internal_note` → `n_a`; others → `sent`. Historical outbound is **not** rewritten to `failed`.

8. **Verify**  
   - Count conversations with `external_id` and NULL `identity_key` = 0.  
   - Duplicate `(workspace_id, identity_key)` where key is not null = 0.  
   - Connection sources 1:1 with WhatsApp phones and email accounts.

9. **Drop old unique** only after 8.

---

## Collision handling

| Case | Action |
|---|---|
| Two customers, same phone | Issue `identity_collision`. Identity kept/created for the customer already on the WhatsApp conversation if any; otherwise first customer by id. No merge. |
| One conversation already representing two logical connections (old unique forced a single thread) | Issue `legacy_shared_thread`. **Do not split.** Future inbound on a **second** connection may open a new conversation (after old unique is dropped). |
| Ambiguous WhatsApp phone | Issue `ambiguous_connection`. `channel_connection_id` stays NULL until an inbound webhook with `phone_number_id` attaches it. |
| identity_key duplicate during backfill | Impossible while old `(workspace_id, external_id)` unique holds, unless two rows share external_id (they cannot). After drop, application uses identity_key. |

---

## Rollback

Each migration `down()`:

1. Drop `conv_ws_identity_key_uniq` if present (re-add old unique if missing).  
2. Drop new columns from `conversations` / `messages` (assignment, connection, identity_key, delivery_*).  
3. Drop new tables.

`down()` does **not** delete `conversations`, `messages`, WhatsApp, or Email Hub rows. Channel values widened to string stay valid if column drop is reversed to string not enum.

Production rollback = restore DB snapshot or run `migrate:rollback` on this batch only. Never `migrate:fresh`.

---

## Compatibility

**Current data:** Existing WhatsApp threads keep the same `id`. `external_id` (wa_id) unchanged. Old unique remains until step 5.

**Mobile:** Same conversation IDs and routes. Additive `channel_connection_id`, `identity_key` not required in JSON. `channel` may become `instagram` instead of `manual` after backfill — Flutter already sends `channel` as a filter string; listing without filter is unchanged.

**WhatsApp webhook:** Still `GET/POST /whatsapp-webhook`. Lookup becomes:

1. Resolve phone → `ChannelConnection`.  
2. Find conversation by `identity_key` (connection + wa_id).  
3. Fallback: `workspace_id + external_id` (legacy single thread). If found and `channel_connection_id` is null, attach this connection. If found with a **different** connection, create a new conversation (allowed only after step 5).  
4. Create customer/identity via `IdentityService` (no merge).

---

## Destructive operations (forbidden)

- `migrate:fresh`
- Dropping conversation/message/WhatsApp/email tables
- Deleting or merging customer rows
- Dropping the old unique **before** backfill + new unique + verification
