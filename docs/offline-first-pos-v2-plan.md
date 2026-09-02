# Offline-First POS Architecture v2 — Migration Plan

**Branch:** `cursor/offline-first-pos-v2-757c`  
**Base:** `cursor/cashier-ui-ux-fix-757c` @ `54806df`  
**Status:** Full offline POS (sessions / payments / invoices local-first; online optional sync)

## Architecture

```text
Flutter UI → Repositories → SQLite (Drift) → Sync Engine ↔ Laravel (SoT)
```

Online is **optional** after Initial Sync: used only to push/pull with Laravel.

## Phases

### Phase 1–6 — done (foundation → SQLite primary)

### Full offline expansion — done
- Open Session / Close (+ payment_method) / Invoice are local-first + `sync_queue`
- SyncEngineV2 pushes `table_session` open/close and takeaway `invoice`
- Close waits for table orders to sync before pushing close
- Advanced ops (transfer / merge / split / discount / note / cancel) are local-first + queued
- QR regenerate / refund / invoice_edit / admin catalog remain online
- Network is optional after Initial Sync — used only to push sync_queue and refresh reports

## Conflict strategy

| Entity | Offline? | Rule |
|--------|----------|------|
| Catalog / tables | cache | Server authoritative |
| Orders | yes | Detect + keep local pending |
| Customers | yes | Detect + keep local pending |
| Open/close session + payment + invoice | yes | Detect + queue; never silent LWW |
| Transfer / merge / split / discount / QR | no | Require reconnect |

## Operational requirement

Device must complete Initial Sync online once per workspace before offline POS is allowed.
