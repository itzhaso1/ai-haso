import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/cashier_api.dart';
import '../local_db/local_db_providers.dart';
import '../offline/offline_store.dart';
import '../offline/sync_engine.dart';
import 'sync_engine_v2.dart';

/// Flushes Hive outbox + SQLite sync_queue together (Phase 3 dual-run).
class PosSyncCoordinator {
  PosSyncCoordinator({
    required SyncEngine hiveEngine,
    required SyncEngineV2 sqliteEngine,
  })  : _hive = hiveEngine,
        _sqlite = sqliteEngine;

  final SyncEngine _hive;
  final SyncEngineV2 _sqlite;

  Future<SyncFlushResult> flushPendingOrders({int? workspaceId}) async {
    final hive = await _hive.flushPendingOrders(workspaceId: workspaceId);
    if (workspaceId == null || workspaceId <= 0) {
      return hive;
    }
    final sqlite = await _sqlite.pushPending(workspaceId: workspaceId);
    return SyncFlushResult(
      synced: hive.synced + sqlite.synced,
      failed: hive.failed + sqlite.failed,
      keptPending: hive.keptPending + sqlite.keptPending,
      authRequired: hive.authRequired || sqlite.authRequired,
      skippedInFlight: hive.skippedInFlight || sqlite.skippedInFlight,
    );
  }

  Future<bool> retryOne(String localId, {int? workspaceId}) async {
    final hiveOk =
        await _hive.retryOne(localId, workspaceId: workspaceId);
    if (workspaceId == null || workspaceId <= 0) return hiveOk;
    final sqlite = await _sqlite.pushPending(workspaceId: workspaceId);
    return hiveOk || sqlite.synced > 0;
  }
}

final syncEngineV2Provider = Provider<SyncEngineV2>((ref) {
  return SyncEngineV2(
    ref.watch(appDatabaseProvider),
    ref.watch(syncQueueRepositoryProvider),
    api: ref.watch(cashierApiProvider),
    offlineStore: OfflineStore.instance,
  );
});

final posSyncCoordinatorProvider = Provider<PosSyncCoordinator>((ref) {
  return PosSyncCoordinator(
    hiveEngine: ref.watch(syncEngineProvider),
    sqliteEngine: ref.watch(syncEngineV2Provider),
  );
});
