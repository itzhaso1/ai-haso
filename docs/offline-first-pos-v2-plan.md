# Offline-First POS Architecture v2 — Migration Plan

**Branch:** `cursor/offline-first-pos-v2-757c`  
**Base:** `cursor/cashier-ui-ux-fix-757c` @ `54806df`  
**Status:** Phase 5+6 complete (bidirectional sync + SQLite primary / Hive POS dual-write removed)

## Architecture

```text
Flutter UI → Repositories → SQLite (Drift) → Sync Engine ↔ Laravel (SoT)
```

## Phases

### Phase 1–4 — done (foundation, tables, orders queue, devices + cursor pull)

### Phase 5 — Full bidirectional sync (done)
- Order + customer `pos_sync_changes` emission
- Pull apply + reconcile via `client_reference`
- Conflict detection into `sync_conflicts` (no silent LWW for orders/payments)
- Customers local-first create + sync_queue
- Takeaway offline via SQLite

### Phase 6 — Finalize Local POS Storage (done)
- Removed Hive POS dual-write for orders/tables
- SQLite performance indexes
- Customers panel / table add-order local-first
- Hive retained only for session/bootstrap prefs + one-shot pending migrate

## Conflict strategy

| Entity | Offline? | Rule |
|--------|----------|------|
| Catalog / tables | cache | Server authoritative |
| Orders (pre-invoice) | yes | Detect + keep local pending |
| Customers | yes | Detect + keep local pending |
| Payments / invoices / close | **no** | Online only |

## Operational requirement

Device must complete Initial Sync online once per workspace before offline POS is allowed.
