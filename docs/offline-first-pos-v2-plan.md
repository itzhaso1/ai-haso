# Offline-First POS Architecture v2 — Migration Plan

**Branch:** `cursor/offline-first-pos-v2-757c`  
**Base:** `cursor/cashier-ui-ux-fix-757c` @ `54806df`  
**Status:** Phase 1 in progress

## Audit summary (current = Online POS + durable outbox)

| Area | Today |
|------|--------|
| Architecture | UI → Riverpod → Dio API; Hive is cache + pending-order outbox |
| Local storage | Hive boxes `cashier_catalog` + `cashier_pending_orders` |
| Offline orders | Table create/edit/delete pending with stable UUID idempotency (`54806df`) |
| Sync | Push-only `POST /orders`; no pull cursor / device registry |
| Financial close | Online-only Scenario A (correct; keep) |
| Workspace isolation | Pending + catalog/tables scoped; bootstrap/session/kitchen global |

## Target architecture

```text
Flutter UI → Repositories → SQLite (Drift) → Sync Engine ↔ Laravel (SoT)
```

Laravel stays Source of Truth. UI must not depend on API for daily POS reads/writes after Initial Sync.

## Non-negotiables

- Do not rebuild from scratch; migrate gradually.
- Keep Hive until SQLite path is proven; then migrate pending outbox.
- Do not invent prices/products offline without Initial Sync.
- Close table / payment / invoice / transfer / merge / split remain **online-only**.
- `local_id == client_reference == Idempotency-Key` never rotates.
- Strict `workspace_id` (+ `device_id`) on every local row.
- No `if (offline)` in every screen — repositories hide transport.

## Phases

### Phase 1 — Foundation (this PR slice)
- Add Drift/SQLite schema: products, categories, tables, orders, order_items, customers, settings, permissions, devices, sync_queue, sync_metadata, sync_conflicts.
- Device identity (`device_id` UUID) in secure storage.
- Workspace-scoped DB access helpers.
- Initial Sync using **existing** full snapshot APIs (`/catalog/*`, `/tables`, `/bootstrap`).
- Gate: POS not “offline-ready” until Initial Sync succeeded once for that workspace (or local rows already present).
- Catalog/Tables repositories: **read Local DB first**; refresh from API when online.
- Keep existing Hive pending-order path working (dual-run).
- Focused unit tests: schema isolation, initial sync readiness, device id.

### Phase 2 — Local-first catalog/tables UI
- Wire Tables board + Add Order sheet to repositories/streams.
- Remove direct catalog Hive reads from feature screens where repository covers them.
- Background pull refresh on reconnect (still full snapshot until Phase 4).

### Phase 3 — Orders local-first + Sync Queue
- Create/edit/delete orders as SQLite transactions + `sync_queue` rows.
- Migrate Hive `cashier_pending_orders` → `sync_queue` / `orders` (preserve keys).
- SyncEngine v2 push from queue; reuse Laravel idempotency.
- Deprecate Hive order outbox after migration.

### Phase 4 — Backend incremental sync (minimal Laravel)
- `POST /devices/register`
- `GET /sync/changes?since=cursor` (catalog/tables/settings + tombstones)
- Optional write `If-Match` / `expected_updated_at` → 409
- Do **not** rewrite PosOrderService close/invoice rules.

### Phase 5 — Bidirectional + conflicts + customers
- Pull apply + conflict table + strategies (server-authoritative for catalog).
- Customers local cache; kitchen/reports later.
- Logout / workspace switch: hard scope switch; no cross-tenant reads.

### Phase 6 — Hardening
- Remove Hive POS caches (keep secure storage / prefs for tokens & printer).
- Performance + kill-during-transaction tests.
- Device E2E checklist (not claimed verified from CI alone).

## Conflict strategy (locked)

| Entity | Offline mutate? | Conflict |
|--------|-----------------|----------|
| Catalog / tables structure / settings | Admin online preferred; cache local | Server authoritative |
| Pending orders / order items (pre-invoice) | Yes | Idempotent create; last local edit before push |
| Payments / invoices / close / transfer / merge / split / refund | **No** | Require online |
| Stock | No local mutation | Server rejects |

## Operational requirement

Device must complete Initial Sync online once per workspace before offline POS is allowed.
