import 'dart:convert';

import 'package:drift/drift.dart';

import '../api/cashier_api.dart';
import '../local_db/app_database.dart';
import '../local_db/local_ids.dart';

/// Tables UI reads/writes through this repository only.
/// Local SQLite is the source for display; remote refresh is an implementation detail.
class TablesRepository {
  TablesRepository(
    this._db, {
    CashierApiClient? api,
  }) : _api = api;

  final AppDatabase _db;
  final CashierApiClient? _api;

  Future<List<Map<String, dynamic>>> listTables(int workspaceId) async {
    if (workspaceId <= 0) return const [];
    final rows = await (_db.select(_db.localTables)
          ..where((t) => t.workspaceId.equals(workspaceId))
          ..orderBy([(t) => OrderingTerm.asc(t.name)]))
        .get();
    return [for (final row in rows) _rowToBoardMap(row)];
  }

  Future<Map<String, dynamic>?> getTable(
    int workspaceId,
    int tableServerId,
  ) async {
    if (workspaceId <= 0 || tableServerId <= 0) return null;
    final row = await (_db.select(_db.localTables)
          ..where(
            (t) =>
                t.workspaceId.equals(workspaceId) &
                t.serverId.equals(tableServerId),
          ))
        .getSingleOrNull();
    if (row == null) return null;
    return _rowToDetailMap(row);
  }

  /// Workspace-scoped local PK so the same server table id can exist in A and B.
  static String tableLocalId(int workspaceId, int serverId) =>
      LocalIds.table(workspaceId, serverId);

  Future<void> replaceBoard(
    int workspaceId,
    List<Map<String, dynamic>> tables,
  ) async {
    if (workspaceId <= 0) return;
    final now = DateTime.now();
    await _db.transaction(() async {
      final existing = await (_db.select(_db.localTables)
            ..where((t) => t.workspaceId.equals(workspaceId)))
          .get();
      final keep = <String>{};
      for (final table in tables) {
        final serverId = (table['id'] as num?)?.toInt();
        if (serverId == null) continue;
        final localId = tableLocalId(workspaceId, serverId);
        keep.add(localId);
        LocalTable? previous;
        for (final e in existing) {
          if (e.localId == localId || e.serverId == serverId) {
            previous = e;
            break;
          }
        }
        // Drop legacy unscoped local_id (table_N) when migrating to w{ws}_table_N.
        if (previous != null && previous.localId != localId) {
          await (_db.delete(_db.localTables)
                ..where((t) => t.localId.equals(previous!.localId)))
              .go();
        }
        // Preserve richer detail payload when board snapshot is thinner.
        final mergedPayload = _mergePayload(previous?.payloadJson, table);
        await _db.into(_db.localTables).insertOnConflictUpdate(
              LocalTablesCompanion.insert(
                localId: localId,
                workspaceId: workspaceId,
                serverId: Value(serverId),
                name: '${table['name'] ?? previous?.name ?? ''}',
                status: Value('${table['status'] ?? previous?.status ?? 'available'}'),
                capacity: Value(
                  (table['capacity'] as num?)?.toInt() ?? previous?.capacity,
                ),
                sessionServerId: Value(
                  (table['session_id'] as num?)?.toInt() ??
                      previous?.sessionServerId,
                ),
                payloadJson: Value(jsonEncode(mergedPayload)),
                updatedAt: now,
              ),
            );
      }
    });
  }

  Future<void> upsertTableDetail(
    int workspaceId,
    int tableServerId,
    Map<String, dynamic> detail,
  ) async {
    if (workspaceId <= 0 || tableServerId <= 0) return;
    final now = DateTime.now();
    final localId = tableLocalId(workspaceId, tableServerId);
    await _db.into(_db.localTables).insertOnConflictUpdate(
          LocalTablesCompanion.insert(
            localId: localId,
            workspaceId: workspaceId,
            serverId: Value(tableServerId),
            name: '${detail['name'] ?? ''}',
            status: Value('${detail['status'] ?? 'available'}'),
            capacity: Value((detail['capacity'] as num?)?.toInt()),
            sessionServerId: Value((detail['session_id'] as num?)?.toInt()),
            payloadJson: Value(jsonEncode({
              ...detail,
              'id': tableServerId,
            })),
            updatedAt: now,
          ),
        );
  }

  /// Best-effort remote refresh. UI must not branch on connectivity —
  /// always returns the best local snapshot after attempting update.
  Future<List<Map<String, dynamic>>> loadBoard(int workspaceId) async {
    final local = await listTables(workspaceId);
    final api = _api;
    if (api == null || workspaceId <= 0) return local;
    try {
      final data = await api.get('/tables');
      final list = <Map<String, dynamic>>[];
      if (data['tables'] is List) {
        for (final item in data['tables'] as List) {
          if (item is Map) list.add(Map<String, dynamic>.from(item));
        }
      }
      if (list.isNotEmpty) {
        await replaceBoard(workspaceId, list);
        return listTables(workspaceId);
      }
    } catch (_) {
      // Keep local SQLite as source of truth for UI.
    }
    return local;
  }

  /// Load one table for detail UI: local first, then best-effort remote upsert.
  Future<Map<String, dynamic>?> loadTableDetail(
    int workspaceId,
    int tableServerId,
  ) async {
    final local = await getTable(workspaceId, tableServerId);
    final api = _api;
    if (api == null || workspaceId <= 0) return local;
    try {
      final detail = await api.get('/tables/$tableServerId');
      await upsertTableDetail(workspaceId, tableServerId, detail);
      // Refresh board list snapshot when available.
      try {
        final board = await api.get('/tables');
        if (board['tables'] is List) {
          final list = <Map<String, dynamic>>[];
          for (final item in board['tables'] as List) {
            if (item is Map) list.add(Map<String, dynamic>.from(item));
          }
          if (list.isNotEmpty) {
            await replaceBoard(workspaceId, list);
          }
        }
      } catch (_) {}
      return await getTable(workspaceId, tableServerId) ?? detail;
    } catch (_) {
      return local;
    }
  }

  Map<String, dynamic> _rowToBoardMap(LocalTable row) {
    final payload = _safeMap(row.payloadJson);
    return {
      ...payload,
      'id': row.serverId,
      'local_id': row.localId,
      'name': row.name,
      'status': row.status,
      'capacity': row.capacity,
      'session_id': row.sessionServerId ?? payload['session_id'],
      'workspace_id': row.workspaceId,
    };
  }

  Map<String, dynamic> _rowToDetailMap(LocalTable row) {
    final payload = _safeMap(row.payloadJson);
    return {
      ...payload,
      'id': row.serverId,
      'local_id': row.localId,
      'name': row.name.isNotEmpty ? row.name : '${payload['name'] ?? ''}',
      'status': row.status,
      'capacity': row.capacity ?? payload['capacity'],
      'session_id': row.sessionServerId ?? payload['session_id'],
      'workspace_id': row.workspaceId,
      'orders': payload['orders'] ?? const [],
    };
  }

  Map<String, dynamic> _mergePayload(
    String? previousJson,
    Map<String, dynamic> boardRow,
  ) {
    final previous = previousJson == null ? const <String, dynamic>{} : _safeMap(previousJson);
    final merged = <String, dynamic>{...previous, ...boardRow};
    // Keep detailed orders/totals if board snapshot omits them.
    if (boardRow['orders'] == null && previous['orders'] != null) {
      merged['orders'] = previous['orders'];
    }
    if (boardRow['subtotal'] == null && previous['subtotal'] != null) {
      merged['subtotal'] = previous['subtotal'];
    }
    if (boardRow['tax_amount'] == null && previous['tax_amount'] != null) {
      merged['tax_amount'] = previous['tax_amount'];
    }
    if (boardRow['discount_amount'] == null &&
        previous['discount_amount'] != null) {
      merged['discount_amount'] = previous['discount_amount'];
    }
    if (boardRow['total'] == null && previous['total'] != null) {
      merged['total'] = previous['total'];
    }
    if (boardRow['notes'] == null && previous['notes'] != null) {
      merged['notes'] = previous['notes'];
    }
    return merged;
  }

  Map<String, dynamic> _safeMap(String raw) {
    if (raw.isEmpty) return const {};
    try {
      final decoded = jsonDecode(raw);
      if (decoded is Map) return Map<String, dynamic>.from(decoded);
    } catch (_) {}
    return const {};
  }
}
