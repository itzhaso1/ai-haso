import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/cashier_api.dart';
import '../device/device_identity.dart';
import '../local_db/app_database.dart';
import '../local_db/initial_sync_service.dart';
import '../local_db/workspace_scope.dart';
import '../repositories/catalog_repository.dart';
import '../repositories/sync_queue_repository.dart';
import '../repositories/tables_repository.dart';

final appDatabaseProvider = Provider<AppDatabase>((ref) {
  final db = AppDatabase.open();
  ref.onDispose(db.close);
  return db;
});

final deviceIdentityProvider = Provider<DeviceIdentity>((ref) {
  return DeviceIdentity(ref.watch(secureStorageProvider));
});

final deviceIdProvider = FutureProvider<String>((ref) async {
  return ref.watch(deviceIdentityProvider).getOrCreateDeviceId();
});

final catalogRepositoryProvider = Provider<CatalogRepository>((ref) {
  return CatalogRepository(ref.watch(appDatabaseProvider));
});

final tablesRepositoryProvider = Provider<TablesRepository>((ref) {
  return TablesRepository(
    ref.watch(appDatabaseProvider),
    api: ref.watch(cashierApiProvider),
  );
});

final syncQueueRepositoryProvider = Provider<SyncQueueRepository>((ref) {
  return SyncQueueRepository(ref.watch(appDatabaseProvider));
});

final initialSyncServiceProvider = Provider<InitialSyncService>((ref) {
  final deviceId = ref.watch(deviceIdProvider).valueOrNull;
  return InitialSyncService(
    ref.watch(appDatabaseProvider),
    ref.watch(cashierApiProvider),
    deviceId: deviceId,
  );
});

/// True when this workspace completed Initial Sync (or already has local products).
final localPosReadyProvider = FutureProvider.family<bool, int?>((ref, workspaceId) async {
  if (workspaceId == null || workspaceId <= 0) return false;
  final db = ref.watch(appDatabaseProvider);
  return db.isOfflinePosReady(workspaceId);
});
