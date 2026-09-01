import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/cashier_api.dart';
import 'offline_store.dart';

/// Sync engine:
/// Local Order → Pending → API (client_reference) → Synced | Failed → Retry
class SyncEngine {
  SyncEngine(this._api, this._store);

  final CashierApiClient _api;
  final OfflineStore _store;

  Future<int> flushPendingOrders() async {
    var synced = 0;
    for (final record in _store.pendingOrders()) {
      final localId = record['local_id'] as String?;
      final payload = record['payload'];
      if (localId == null || payload is! Map) continue;
      try {
        final data = await _api.post(
          '/orders',
          data: Map<String, dynamic>.from(payload),
          idempotencyKey: record['client_reference'] as String?,
        );
        await _store.markSynced(localId, serverOrderId: data['id'] as int?);
        synced++;
      } catch (e) {
        await _store.markFailed(localId, e.toString());
      }
    }
    return synced;
  }
}

final syncEngineProvider = Provider<SyncEngine>((ref) {
  return SyncEngine(ref.watch(cashierApiProvider), OfflineStore.instance);
});
