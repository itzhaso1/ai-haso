import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/cashier_api.dart';
import 'offline_store.dart';

/// Sync engine:
/// Local Order → Pending → Syncing → API (client_reference) → Synced | Failed → Retry
///
/// Never drops a failed/pending order silently.
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
      if (record['status'] == SyncStatus.syncing.name) continue;

      await _store.markSyncing(localId);
      try {
        final data = await _api.post(
          '/orders',
          data: Map<String, dynamic>.from(payload),
          idempotencyKey: record['client_reference'] as String?,
        );
        final serverId = data['id'] is num
            ? (data['id'] as num).toInt()
            : int.tryParse('${data['id']}');
        await _store.markSynced(localId, serverOrderId: serverId);
        synced++;
      } catch (e) {
        await _store.markFailed(localId, e.toString());
      }
    }
    await _store.pruneSynced();
    return synced;
  }

  Future<bool> retryOne(String localId) async {
    await _store.retry(localId);
    final before = _store.pendingCount();
    await flushPendingOrders();
    return _store.pendingCount() < before ||
        _store
            .allOrderRecords()
            .any((e) => e['local_id'] == localId && e['status'] == 'synced');
  }
}

final syncEngineProvider = Provider<SyncEngine>((ref) {
  return SyncEngine(ref.watch(cashierApiProvider), OfflineStore.instance);
});
