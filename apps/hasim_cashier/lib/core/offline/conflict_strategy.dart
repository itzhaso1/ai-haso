/// Conflict handling strategy for كاشير حاسم offline sync.
///
/// Laravel remains source of truth for inventory, table session state,
/// product availability, and paid invoices.
///
/// | Domain | Offline allowed | Conflict rule |
/// |--------|-----------------|---------------|
/// | Catalog / categories / tables | read cache + server pull | Server authoritative |
/// | Customers | create/update offline | Detect conflict; keep local pending |
/// | Orders (pre-invoice) | create/update/delete offline | Detect conflict; never silent LWW |
/// | Payments / invoices / close | **online only** | Never silently overwrite |
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
        'pending_order' || 'order' || 'customer' =>
          ConflictPolicy.detectAndRecord,
        'table_action' ||
        'refund' ||
        'invoice_edit' ||
        'invoice' ||
        'payment' ||
        'close_table' ||
        'transfer' ||
        'merge' ||
        'split' ||
        'discount' ||
        'open_session' =>
          ConflictPolicy.requireOnline,
        _ => ConflictPolicy.serverWins,
      };
}
