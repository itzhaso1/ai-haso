/// Conflict handling strategy for كاشير حاسم offline sync.
///
/// Laravel remains source of truth after sync. Daily POS (orders, sessions,
/// payments, invoices) is local-first; online is optional for sync only.
///
/// | Domain | Offline allowed | Conflict rule |
/// |--------|-----------------|---------------|
/// | Catalog / categories / tables | read cache + server pull | Server authoritative |
/// | Customers | create/update offline | Detect conflict; keep local pending |
/// | Orders | create/update/delete offline | Detect conflict; never silent LWW |
/// | Open/close session + payment + invoice | yes (queued) | Detect conflict; never silent LWW |
/// | Transfer / merge / split / discount / QR / refund / invoice_edit | online | Require reconnect |
///
/// On sync failure: keep local Pending/Failed record, never silent-delete.
/// On sync success with replay (same client_reference): treat as Synced.
library;

enum ConflictPolicy {
  /// Accept server representation; refresh local cache.
  serverWins,

  /// Keep local pending queue entry until explicit retry/success.
  keepLocalPending,

  /// Operation blocked offline — user must reconnect.
  requireOnline,

  /// Record both sides in sync_conflicts; do not discard either silently.
  detectAndRecord,
}

class ConflictStrategy {
  const ConflictStrategy._();

  static ConflictPolicy forDomain(String domain) => switch (domain) {
        'catalog' ||
        'inventory' ||
        'tables' ||
        'product' ||
        'category' ||
        'product_availability' =>
          ConflictPolicy.serverWins,
        'pending_order' ||
        'order' ||
        'customer' ||
        'open_session' ||
        'close_table' ||
        'payment' ||
        'invoice' ||
        'table_session' =>
          ConflictPolicy.detectAndRecord,
        'table_action' ||
        'refund' ||
        'invoice_edit' ||
        'transfer' ||
        'merge' ||
        'split' ||
        'discount' =>
          ConflictPolicy.requireOnline,
        _ => ConflictPolicy.serverWins,
      };
}
