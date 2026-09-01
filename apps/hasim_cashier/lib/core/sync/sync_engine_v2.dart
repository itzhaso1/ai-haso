import 'dart:convert';

import 'package:drift/drift.dart';

import '../api/cashier_api.dart';
import '../local_db/app_database.dart';
import '../offline/offline_store.dart';
import '../repositories/sync_queue_repository.dart';

/// Sync Engine v2 — push SQLite sync_queue to Laravel with stable idempotency.
/// Coexists with Hive SyncEngine (dual-run) until Phase 6 removes Hive.
class SyncEngineV2 {
  SyncEngineV2(
    this._db,
    this._queue, {
    CashierApiClient? api,
    OfflineStore? offlineStore,
    Future<Map<String, dynamic>> Function(
      Map<String, dynamic> payload,
      String idempotencyKey,
    )? postOrder,
    Future<Map<String, dynamic>> Function(
      int serverOrderId,
      Map<String, dynamic> payload,
    )? postOrderItems,
    Future<void> Function(int serverOrderId)? deleteOrder,
  })  : _api = api,
        _hive = offlineStore ?? OfflineStore.instance,
        _postOrder = postOrder,
        _postOrderItems = postOrderItems,
        _deleteOrder = deleteOrder;

  final AppDatabase _db;
  final SyncQueueRepository _queue;
  final CashierApiClient? _api;
  final OfflineStore _hive;
  final Future<Map<String, dynamic>> Function(
    Map<String, dynamic> payload,
    String idempotencyKey,
  )? _postOrder;
  final Future<Map<String, dynamic>> Function(
    int serverOrderId,
    Map<String, dynamic> payload,
  )? _postOrderItems;
  final Future<void> Function(int serverOrderId)? _deleteOrder;

  var _flushing = false;
  bool get isFlushing => _flushing;

  Future<SyncEngineV2Result> pushPending({required int workspaceId}) async {
    if (_flushing) {
      return const SyncEngineV2Result(skippedInFlight: true);
    }
    if (workspaceId <= 0) {
      return const SyncEngineV2Result();
    }
    _flushing = true;
    var synced = 0;
    var failed = 0;
    var kept = 0;
    try {
      await _queue.recoverStuckSyncing(workspaceId);
      final rows = await _queue.pendingForWorkspace(workspaceId);
      for (final row in rows) {
        if (row.workspaceId != workspaceId) continue;
        if (row.status == 'cancelled' || row.status == 'synced') continue;
        if (row.nextAttemptAt != null &&
            row.nextAttemptAt!.isAfter(DateTime.now())) {
          kept++;
          continue;
        }
        if (row.entityType != 'order') {
          kept++;
          continue;
        }

        await _queue.markSyncing(row.id);
        try {
          if (row.operation == 'create') {
            await _pushCreate(row);
            synced++;
          } else if (row.operation == 'update') {
            await _pushUpdate(row);
            synced++;
          } else if (row.operation == 'delete') {
            await _pushDelete(row);
            synced++;
          } else {
            await _queue.markFailed(
              row.id,
              'عملية مزامنة غير مدعومة: ${row.operation}',
              retryable: false,
            );
            failed++;
          }
        } on ApiException catch (e) {
          if (e.statusCode == 401) {
            await _queue.markFailed(row.id, e.message, retryable: true);
            return SyncEngineV2Result(
              synced: synced,
              failed: failed,
              keptPending: kept + 1,
              authRequired: true,
            );
          }
          if (e.statusCode == 422 || e.statusCode == 403) {
            await _queue.markFailed(row.id, e.message, retryable: false);
            await _markOrderFailed(row.entityId, e.message);
            failed++;
          } else {
            await _queue.markFailed(row.id, e.message, retryable: true);
            await _markOrderPending(row.entityId, e.message);
            kept++;
          }
        } catch (e) {
          await _queue.markFailed(row.id, e.toString(), retryable: true);
          await _markOrderPending(row.entityId, e.toString());
          kept++;
        }
      }
      return SyncEngineV2Result(
        synced: synced,
        failed: failed,
        keptPending: kept,
      );
    } finally {
      _flushing = false;
    }
  }

  Future<void> _pushCreate(SyncQueueItem row) async {
    final payload = _decode(row.payloadJson);
    payload['client_reference'] = row.clientReference;
    final data = await _sendCreate(payload, row.clientReference);
    final serverId = data['id'] is num
        ? (data['id'] as num).toInt()
        : int.tryParse('${data['id']}');
    await _db.transaction(() async {
      await (_db.update(_db.localOrders)
            ..where((t) =>
                t.localId.equals(row.entityId) &
                t.workspaceId.equals(row.workspaceId)))
          .write(
        LocalOrdersCompanion(
          serverId: Value(serverId),
          syncStatus: const Value('synced'),
          lastError: const Value(null),
          syncedAt: Value(DateTime.now()),
          updatedAt: Value(DateTime.now()),
        ),
      );
      await _queue.markSynced(row.id);
    });
    try {
      await _hive.markSynced(row.clientReference, serverOrderId: serverId);
    } catch (_) {}
  }

  Future<void> _pushUpdate(SyncQueueItem row) async {
    final payload = _decode(row.payloadJson);
    final serverId = (payload['server_order_id'] as num?)?.toInt();
    if (serverId == null || serverId <= 0) {
      throw StateError('update requires server_order_id');
    }
    await _sendUpdate(serverId, payload);
    await _db.transaction(() async {
      await (_db.update(_db.localOrders)
            ..where((t) =>
                t.localId.equals(row.entityId) &
                t.workspaceId.equals(row.workspaceId)))
          .write(
        LocalOrdersCompanion(
          syncStatus: const Value('synced'),
          lastError: const Value(null),
          syncedAt: Value(DateTime.now()),
          updatedAt: Value(DateTime.now()),
        ),
      );
      await _queue.markSynced(row.id);
    });
  }

  Future<void> _pushDelete(SyncQueueItem row) async {
    final payload = _decode(row.payloadJson);
    final serverId = (payload['server_order_id'] as num?)?.toInt();
    if (serverId == null || serverId <= 0) {
      throw StateError('delete requires server_order_id');
    }
    await _sendDelete(serverId);
    await _db.transaction(() async {
      await (_db.update(_db.localOrders)
            ..where((t) =>
                t.localId.equals(row.entityId) &
                t.workspaceId.equals(row.workspaceId)))
          .write(
        LocalOrdersCompanion(
          posStatus: const Value('cancelled'),
          syncStatus: const Value('synced'),
          lastError: const Value(null),
          syncedAt: Value(DateTime.now()),
          updatedAt: Value(DateTime.now()),
        ),
      );
      await _queue.markSynced(row.id);
    });
  }

  Future<void> _markOrderFailed(String localId, String error) async {
    await (_db.update(_db.localOrders)..where((t) => t.localId.equals(localId)))
        .write(
      LocalOrdersCompanion(
        syncStatus: const Value('failed'),
        lastError: Value(error),
        updatedAt: Value(DateTime.now()),
      ),
    );
  }

  Future<void> _markOrderPending(String localId, String error) async {
    await (_db.update(_db.localOrders)..where((t) => t.localId.equals(localId)))
        .write(
      LocalOrdersCompanion(
        syncStatus: const Value('pending'),
        lastError: Value(error),
        updatedAt: Value(DateTime.now()),
      ),
    );
  }

  Future<Map<String, dynamic>> _sendCreate(
    Map<String, dynamic> payload,
    String key,
  ) {
    final poster = _postOrder;
    if (poster != null) return poster(payload, key);
    final api = _api;
    if (api == null) {
      throw StateError('SyncEngineV2 requires API or postOrder');
    }
    return api.post('/orders', data: payload, idempotencyKey: key);
  }

  Future<Map<String, dynamic>> _sendUpdate(
    int serverOrderId,
    Map<String, dynamic> payload,
  ) {
    final poster = _postOrderItems;
    if (poster != null) return poster(serverOrderId, payload);
    final api = _api;
    if (api == null) {
      throw StateError('SyncEngineV2 requires API or postOrderItems');
    }
    return api.post('/orders/$serverOrderId/items', data: payload);
  }

  Future<void> _sendDelete(int serverOrderId) async {
    final deleter = _deleteOrder;
    if (deleter != null) {
      await deleter(serverOrderId);
      return;
    }
    final api = _api;
    if (api == null) {
      throw StateError('SyncEngineV2 requires API or deleteOrder');
    }
    await api.delete('/orders/$serverOrderId');
  }

  Map<String, dynamic> _decode(String raw) {
    if (raw.isEmpty) return <String, dynamic>{};
    try {
      final decoded = jsonDecode(raw);
      if (decoded is Map) return Map<String, dynamic>.from(decoded);
    } catch (_) {}
    return <String, dynamic>{};
  }
}

class SyncEngineV2Result {
  const SyncEngineV2Result({
    this.synced = 0,
    this.failed = 0,
    this.keptPending = 0,
    this.authRequired = false,
    this.skippedInFlight = false,
  });

  final int synced;
  final int failed;
  final int keptPending;
  final bool authRequired;
  final bool skippedInFlight;
}
