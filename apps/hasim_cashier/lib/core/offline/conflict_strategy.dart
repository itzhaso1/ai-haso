/// Conflict handling strategy for كاشير حاسم offline sync.
///
/// Laravel remains source of truth for inventory, table session state,
/// product availability, and paid invoices.
///
/// | Domain | Offline allowed | Conflict rule |
/// |--------|-----------------|---------------|
/// | Catalog cache | read-only cache | Server wins on next online bootstrap/catalog fetch |
/// | Cart (local) | yes | Local until checkout; never invents stock |
/// | Create order | yes (Pending Sync) | `client_reference` + Idempotency-Key → server dedupe |
/// | Table open/close/transfer/merge/split | **no** (online only) | Must hit Laravel; show error if offline |
/// | Inventory / availability | no local mutation | Server rejects oversell; Flutter shows API error |
/// | Invoice edit / refund | online only | Server policy wins |
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
}

class ConflictStrategy {
  const ConflictStrategy._();

  static ConflictPolicy forDomain(String domain) => switch (domain) {
        'catalog' || 'inventory' || 'tables' || 'product_availability' =>
          ConflictPolicy.serverWins,
        'pending_order' => ConflictPolicy.keepLocalPending,
        'table_action' ||
        'refund' ||
        'invoice_edit' ||
        'payment' ||
        'close_table' ||
        'transfer' ||
        'merge' ||
        'split' ||
        'discount' =>
          ConflictPolicy.requireOnline,
        _ => ConflictPolicy.serverWins,
      };
}
