import 'dart:convert';

import 'package:drift/drift.dart';

import '../api/cashier_api.dart';
import '../local_db/app_database.dart';
import '../repositories/sync_queue_repository.dart';

/// Sync Engine v2 skeleton — push sync_queue; pull cursor lands in Phase 4.
/// Coexists with legacy Hive SyncEngine until the order outbox is migrated.
class SyncEngineV2 {
  SyncEngineV2(
    this._db,
    this._queue, {
    CashierApiClient? api,
    Future<Map<String, dynamic>> Function(
      Map<String, dynamic> payload,
      String idempotencyKey,
    )? postOrder,
  })  : _api = api,
        _postOrder = postOrder;

  final AppDatabase _db;
  final SyncQueueRepository _queue;
  final CashierApiClient? _api;
  final Future<Map<String, dynamic>> Function(
    Map<String, dynamic> payload,
    String idempotencyKey,
  )? _postOrder;

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
      final rows = await _queue.pendingForWorkspace(workspaceId);
      for (final row in rows) {
        if (row.workspaceId != workspaceId) continue;
        if (row.nextAttemptAt != null &&
            row.nextAttemptAt!.isAfter(DateTime.now())) {
          kept++;
          continue;
        }
        if (row.entityType != 'order' || row.operation != 'create') {
          // Phase 1: only order-create pushes are wired; other ops stay queued.
          kept++;
          continue;
        }
        await _queue.markSyncing(row.id);
        try {
          final payload = _decode(row.payloadJson);
          payload['client_reference'] = row.clientReference;
          final data = await _send(payload, row.clientReference);
          final serverId = data['id'] is num
              ? (data['id'] as num).toInt()
              : int.tryParse('${data['id']}');
          await _db.transaction(() async {
            await (_db.update(_db.localOrders)
                  ..where((t) => t.localId.equals(row.entityId)))
                .write(
              LocalOrdersCompanion(
                serverId: Value(serverId),
                syncStatus: const Value('synced'),
                syncedAt: Value(DateTime.now()),
                updatedAt: Value(DateTime.now()),
              ),
            );
            await _queue.markSynced(row.id);
          });
          synced++;
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
            failed++;
          } else {
            await _queue.markFailed(row.id, e.message, retryable: true);
            kept++;
          }
        } catch (e) {
          await _queue.markFailed(row.id, e.toString(), retryable: true);
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

  Future<Map<String, dynamic>> _send(
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
