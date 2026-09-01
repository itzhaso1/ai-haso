import 'dart:convert';

import 'package:drift/drift.dart';

import '../local_db/app_database.dart';

/// Durable outbox for local→server operations. Never delete on failure
/// of a push attempt; only cancel when the local entity itself is deleted
/// before it ever reached the server.
class SyncQueueRepository {
  SyncQueueRepository(this._db);

  final AppDatabase _db;

  Future<int> enqueue({
    required int workspaceId,
    required String deviceId,
    required String entityType,
    required String entityId,
    required String operation,
    required Map<String, dynamic> payload,
    required String clientReference,
  }) async {
    if (workspaceId <= 0) {
      throw ArgumentError('workspaceId required');
    }
    if (clientReference.trim().isEmpty) {
      throw ArgumentError('clientReference required');
    }
    final now = DateTime.now();
    return _db.into(_db.syncQueueItems).insert(
          SyncQueueItemsCompanion.insert(
            workspaceId: workspaceId,
            deviceId: deviceId,
            entityType: entityType,
            entityId: entityId,
            operation: operation,
            payloadJson: jsonEncode(payload),
            clientReference: clientReference.trim(),
            status: const Value('pending'),
            createdAt: now,
            updatedAt: now,
          ),
        );
  }

  Future<List<SyncQueueItem>> pendingForWorkspace(int workspaceId) {
    return (_db.select(_db.syncQueueItems)
          ..where((t) =>
              t.workspaceId.equals(workspaceId) &
              (t.status.equals('pending') |
                  t.status.equals('failed') |
                  t.status.equals('syncing')))
          ..orderBy([(t) => OrderingTerm.asc(t.createdAt)]))
        .get();
  }

  Future<int> pendingCount(int workspaceId) async {
    final rows = await pendingForWorkspace(workspaceId);
    return rows
        .where((r) => r.status == 'pending' || r.status == 'failed')
        .length;
  }

  Future<SyncQueueItem?> findOpenOp({
    required int workspaceId,
    required String entityType,
    required String entityId,
    required String operation,
  }) {
    return (_db.select(_db.syncQueueItems)
          ..where((t) =>
              t.workspaceId.equals(workspaceId) &
              t.entityType.equals(entityType) &
              t.entityId.equals(entityId) &
              t.operation.equals(operation) &
              (t.status.equals('pending') |
                  t.status.equals('failed') |
                  t.status.equals('syncing')))
          ..orderBy([(t) => OrderingTerm.desc(t.createdAt)])
          ..limit(1))
        .getSingleOrNull();
  }

  /// Refresh create payload before first successful push (same client_reference).
  Future<bool> updateOpenPayload({
    required int workspaceId,
    required String entityType,
    required String entityId,
    required String operation,
    required Map<String, dynamic> payload,
  }) async {
    final row = await findOpenOp(
      workspaceId: workspaceId,
      entityType: entityType,
      entityId: entityId,
      operation: operation,
    );
    if (row == null) return false;
    if (row.status == 'syncing') return false;
    await (_db.update(_db.syncQueueItems)..where((t) => t.id.equals(row.id)))
        .write(
      SyncQueueItemsCompanion(
        payloadJson: Value(jsonEncode(payload)),
        status: const Value('pending'),
        lastError: const Value(null),
        updatedAt: Value(DateTime.now()),
      ),
    );
    return true;
  }

  /// Cancel a not-yet-synced op when the local entity is deleted offline.
  Future<bool> cancelOpenOp({
    required int workspaceId,
    required String entityType,
    required String entityId,
    required String operation,
  }) async {
    final row = await findOpenOp(
      workspaceId: workspaceId,
      entityType: entityType,
      entityId: entityId,
      operation: operation,
    );
    if (row == null) return false;
    if (row.status == 'syncing') return false;
    await (_db.update(_db.syncQueueItems)..where((t) => t.id.equals(row.id)))
        .write(
      SyncQueueItemsCompanion(
        status: const Value('cancelled'),
        updatedAt: Value(DateTime.now()),
      ),
    );
    return true;
  }

  Future<void> markSyncing(int id) async {
    await (_db.update(_db.syncQueueItems)..where((t) => t.id.equals(id))).write(
      SyncQueueItemsCompanion(
        status: const Value('syncing'),
        updatedAt: Value(DateTime.now()),
      ),
    );
  }

  Future<void> markSynced(int id) async {
    await (_db.update(_db.syncQueueItems)..where((t) => t.id.equals(id))).write(
      SyncQueueItemsCompanion(
        status: const Value('synced'),
        lastError: const Value(null),
        updatedAt: Value(DateTime.now()),
      ),
    );
  }

  Future<void> markFailed(int id, String error, {bool retryable = true}) async {
    final row = await (_db.select(_db.syncQueueItems)
          ..where((t) => t.id.equals(id)))
        .getSingleOrNull();
    final attempts = (row?.attempts ?? 0) + 1;
    final delaySeconds = (1 << (attempts - 1)).clamp(1, 300);
    await (_db.update(_db.syncQueueItems)..where((t) => t.id.equals(id))).write(
      SyncQueueItemsCompanion(
        status: Value(retryable ? 'pending' : 'failed'),
        attempts: Value(attempts),
        lastError: Value(error),
        nextAttemptAt: Value(
          DateTime.now().add(Duration(seconds: delaySeconds)),
        ),
        updatedAt: Value(DateTime.now()),
      ),
    );
  }

  Future<void> recoverStuckSyncing(int workspaceId) async {
    final stuck = await (_db.select(_db.syncQueueItems)
          ..where((t) =>
              t.workspaceId.equals(workspaceId) & t.status.equals('syncing')))
        .get();
    final now = DateTime.now();
    for (final row in stuck) {
      await (_db.update(_db.syncQueueItems)..where((t) => t.id.equals(row.id)))
          .write(
        SyncQueueItemsCompanion(
          status: const Value('pending'),
          updatedAt: Value(now),
        ),
      );
    }
  }
}
