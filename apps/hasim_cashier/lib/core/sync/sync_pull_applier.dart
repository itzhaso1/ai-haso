import 'dart:convert';

import 'package:drift/drift.dart';

import '../local_db/app_database.dart';
import '../local_db/local_ids.dart';
import '../local_db/workspace_scope.dart';

/// Applies incremental Laravel sync changes inside one SQLite transaction.
/// Cursor advances only after the whole batch commits successfully.
class SyncPullApplier {
  SyncPullApplier(this._db);

  final AppDatabase _db;

  /// Returns the cursor that should be persisted after a successful apply.
  Future<int> applyBatch({
    required int workspaceId,
    required int fromCursor,
    required int responseCursor,
    required List<Map<String, dynamic>> changes,
    String? deviceId,
  }) async {
    if (workspaceId <= 0) {
      throw ArgumentError('workspaceId required');
    }

    var appliedThrough = fromCursor;
    await _db.transaction(() async {
      for (final change in changes) {
        final version = (change['version'] as num?)?.toInt();
        if (version == null || version <= fromCursor) {
          continue;
        }
        final entity = '${change['entity'] ?? ''}';
        final operation = '${change['operation'] ?? ''}';
        final data = change['data'] is Map
            ? Map<String, dynamic>.from(change['data'] as Map)
            : <String, dynamic>{};
        final entityId = (change['id'] as num?)?.toInt() ??
            (data['id'] as num?)?.toInt();

        switch (entity) {
          case 'product':
            await _applyProduct(workspaceId, operation, entityId, data);
          case 'category':
            await _applyCategory(workspaceId, operation, entityId, data);
          case 'table':
            await _applyTable(workspaceId, operation, entityId, data);
          default:
            // Forward-compatible: ignore unknown entities without failing sync.
            break;
        }
        appliedThrough = version;
      }

      final cursorToStore =
          changes.isEmpty ? responseCursor : appliedThrough;
      if (cursorToStore < fromCursor) {
        throw StateError('refusing to move cursor backwards');
      }
      await _db.writeCursor(
        workspaceId,
        '$cursorToStore',
        deviceId: deviceId,
      );
    });

    final stored = await _db.readCursor(workspaceId);
    return int.tryParse(stored ?? '') ?? fromCursor;
  }

  Future<void> _applyProduct(
    int workspaceId,
    String operation,
    int? serverId,
    Map<String, dynamic> data,
  ) async {
    if (serverId == null || serverId <= 0) return;
    final localId = LocalIds.product(workspaceId, serverId);
    if (operation == 'delete') {
      await (_db.update(_db.localProducts)
            ..where((t) =>
                t.workspaceId.equals(workspaceId) &
                (t.localId.equals(localId) | t.serverId.equals(serverId))))
          .write(
        LocalProductsCompanion(
          isDeleted: const Value(true),
          isActive: const Value(false),
          updatedAt: Value(DateTime.now()),
        ),
      );
      return;
    }

    final catServerId = (data['pos_item_category_id'] as num?)?.toInt();
    final now = DateTime.now();
    await _db.into(_db.localProducts).insertOnConflictUpdate(
          LocalProductsCompanion.insert(
            localId: localId,
            workspaceId: workspaceId,
            serverId: Value(serverId),
            categoryLocalId: Value(
              catServerId == null
                  ? null
                  : LocalIds.category(workspaceId, catServerId),
            ),
            categoryServerId: Value(catServerId),
            name: '${data['name'] ?? ''}',
            sku: Value(data['sku'] as String?),
            barcode: Value(data['barcode'] as String?),
            itemType: Value(data['item_type'] as String?),
            price: Value((data['price'] as num?)?.toDouble() ?? 0),
            isActive: Value(data['is_active'] != false),
            isDeleted: const Value(false),
            payloadJson: Value(jsonEncode({...data, 'id': serverId})),
            updatedAt: now,
            serverVersion: Value((data['version'] as num?)?.toInt()),
          ),
        );
  }

  Future<void> _applyCategory(
    int workspaceId,
    String operation,
    int? serverId,
    Map<String, dynamic> data,
  ) async {
    if (serverId == null || serverId <= 0) return;
    final localId = LocalIds.category(workspaceId, serverId);
    if (operation == 'delete') {
      await (_db.update(_db.localCategories)
            ..where((t) =>
                t.workspaceId.equals(workspaceId) &
                (t.localId.equals(localId) | t.serverId.equals(serverId))))
          .write(
        LocalCategoriesCompanion(
          isDeleted: const Value(true),
          isActive: const Value(false),
          updatedAt: Value(DateTime.now()),
        ),
      );
      return;
    }

    await _db.into(_db.localCategories).insertOnConflictUpdate(
          LocalCategoriesCompanion.insert(
            localId: localId,
            workspaceId: workspaceId,
            serverId: Value(serverId),
            name: '${data['name'] ?? ''}',
            sortOrder: Value((data['sort_order'] as num?)?.toInt() ?? 0),
            isActive: Value(data['is_active'] != false),
            isDeleted: const Value(false),
            updatedAt: DateTime.now(),
          ),
        );
  }

  Future<void> _applyTable(
    int workspaceId,
    String operation,
    int? serverId,
    Map<String, dynamic> data,
  ) async {
    if (serverId == null || serverId <= 0) return;
    final localId = LocalIds.table(workspaceId, serverId);
    if (operation == 'delete') {
      await (_db.delete(_db.localTables)
            ..where((t) =>
                t.workspaceId.equals(workspaceId) &
                (t.localId.equals(localId) | t.serverId.equals(serverId))))
          .go();
      return;
    }

    await _db.into(_db.localTables).insertOnConflictUpdate(
          LocalTablesCompanion.insert(
            localId: localId,
            workspaceId: workspaceId,
            serverId: Value(serverId),
            name: '${data['name'] ?? ''}',
            status: Value('${data['status'] ?? 'available'}'),
            capacity: Value((data['capacity'] as num?)?.toInt()),
            sessionServerId: Value((data['session_id'] as num?)?.toInt()),
            payloadJson: Value(jsonEncode({...data, 'id': serverId})),
            updatedAt: DateTime.now(),
          ),
        );
  }
}
