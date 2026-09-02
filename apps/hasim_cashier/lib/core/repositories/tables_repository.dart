import 'dart:convert';

import 'package:drift/drift.dart';
import 'package:uuid/uuid.dart';

import '../api/cashier_api.dart';
import '../local_db/app_database.dart';
import '../local_db/local_ids.dart';
import 'sync_queue_repository.dart';

/// Tables UI reads/writes through this repository only.
/// Local SQLite is the source for display; remote refresh is an implementation detail.
class TablesRepository {
  TablesRepository(
    this._db,
    this._queue, {
    CashierApiClient? api,
    String Function()? newId,
  })  : _api = api,
        _newId = newId ?? (() => const Uuid().v4());

  final AppDatabase _db;
  final SyncQueueRepository _queue;
  final CashierApiClient? _api;
  final String Function() _newId;

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
        // Preserve offline-open client session until close sync completes.
        final prevMap = previous == null
            ? const <String, dynamic>{}
            : _safeMap(previous.payloadJson);
        final offlineOpen = prevMap['session_client_id'] != null &&
            previous?.status == 'occupied' &&
            (table['status'] == 'available' || table['session_id'] == null);
        final status = offlineOpen
            ? 'occupied'
            : '${table['status'] ?? previous?.status ?? 'available'}';
        final sessionServerId = offlineOpen
            ? previous?.sessionServerId
            : (table['session_id'] as num?)?.toInt() ??
                previous?.sessionServerId;
        if (offlineOpen && prevMap['session_client_id'] != null) {
          mergedPayload['session_client_id'] = prevMap['session_client_id'];
          mergedPayload['session_open'] = true;
          if (prevMap['opened_at'] != null) {
            mergedPayload['opened_at'] = prevMap['opened_at'];
          }
        }
        await _db.into(_db.localTables).insertOnConflictUpdate(
              LocalTablesCompanion.insert(
                localId: localId,
                workspaceId: workspaceId,
                serverId: Value(serverId),
                name: '${table['name'] ?? previous?.name ?? ''}',
                status: Value(status),
                capacity: Value(
                  (table['capacity'] as num?)?.toInt() ?? previous?.capacity,
                ),
                sessionServerId: Value(sessionServerId),
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
    final previous = await (_db.select(_db.localTables)
          ..where((t) => t.localId.equals(localId)))
        .getSingleOrNull();
    final prevMap =
        previous == null ? const <String, dynamic>{} : _safeMap(previous.payloadJson);
    final offlineOpen = prevMap['session_client_id'] != null &&
        previous?.status == 'occupied' &&
        (detail['status'] == 'available' || detail['session_id'] == null);
    final merged = {
      ...detail,
      'id': tableServerId,
      if (offlineOpen) ...{
        'session_client_id': prevMap['session_client_id'],
        'session_open': true,
        if (prevMap['opened_at'] != null) 'opened_at': prevMap['opened_at'],
      },
    };
    await _db.into(_db.localTables).insertOnConflictUpdate(
          LocalTablesCompanion.insert(
            localId: localId,
            workspaceId: workspaceId,
            serverId: Value(tableServerId),
            name: '${detail['name'] ?? previous?.name ?? ''}',
            status: Value(
              offlineOpen
                  ? 'occupied'
                  : '${detail['status'] ?? previous?.status ?? 'available'}',
            ),
            capacity: Value(
              (detail['capacity'] as num?)?.toInt() ?? previous?.capacity,
            ),
            sessionServerId: Value(
              offlineOpen
                  ? previous?.sessionServerId
                  : (detail['session_id'] as num?)?.toInt(),
            ),
            payloadJson: Value(jsonEncode(merged)),
            updatedAt: now,
          ),
        );
  }

  /// Local-first open session — works fully offline; syncs when online.
  Future<Map<String, dynamic>> openSessionLocal({
    required int workspaceId,
    required String deviceId,
    required int tableServerId,
  }) async {
    if (workspaceId <= 0 || tableServerId <= 0) {
      throw ArgumentError('workspaceId and tableServerId required');
    }
    final localId = tableLocalId(workspaceId, tableServerId);
    final existing = await (_db.select(_db.localTables)
          ..where((t) =>
              t.localId.equals(localId) & t.workspaceId.equals(workspaceId)))
        .getSingleOrNull();
    if (existing == null) {
      throw StateError('الطاولة غير متاحة محليًا. أكمل Initial Sync أولًا.');
    }
    final payload = _safeMap(existing.payloadJson);
    final existingClient = '${payload['session_client_id'] ?? ''}';
    if (existing.status == 'occupied' && existingClient.isNotEmpty) {
      return _rowToDetailMap(existing);
    }

    final sessionClientId = _newId();
    final openedAt = DateTime.now().toUtc().toIso8601String();
    final now = DateTime.now();
    final nextPayload = {
      ...payload,
      'id': tableServerId,
      'status': 'occupied',
      'session_open': true,
      'session_client_id': sessionClientId,
      'opened_at': openedAt,
      'session_id': existing.sessionServerId,
    };

    await _db.transaction(() async {
      await (_db.update(_db.localTables)
            ..where((t) =>
                t.localId.equals(localId) &
                t.workspaceId.equals(workspaceId)))
          .write(
        LocalTablesCompanion(
          status: const Value('occupied'),
          payloadJson: Value(jsonEncode(nextPayload)),
          updatedAt: Value(now),
        ),
      );
      await _queue.enqueue(
        workspaceId: workspaceId,
        deviceId: deviceId.trim(),
        entityType: 'table_session',
        entityId: localId,
        operation: 'open',
        payload: {
          'table_server_id': tableServerId,
          'table_local_id': localId,
          'session_client_id': sessionClientId,
        },
        clientReference: sessionClientId,
      );
    });

    final refreshed = await getTable(workspaceId, tableServerId);
    return refreshed ?? nextPayload;
  }

  /// Local-first close + payment + invoice draft. Queues sync; no online required.
  Future<Map<String, dynamic>> closeSessionLocal({
    required int workspaceId,
    required String deviceId,
    required int tableServerId,
    String? paymentMethod,
  }) async {
    if (workspaceId <= 0 || tableServerId <= 0) {
      throw ArgumentError('workspaceId and tableServerId required');
    }
    final localId = tableLocalId(workspaceId, tableServerId);
    final table = await (_db.select(_db.localTables)
          ..where((t) =>
              t.localId.equals(localId) & t.workspaceId.equals(workspaceId)))
        .getSingleOrNull();
    if (table == null) {
      throw StateError('الطاولة غير متاحة محليًا.');
    }
    final payload = _safeMap(table.payloadJson);
    final sessionClientId = '${payload['session_client_id'] ?? _newId()}';
    final closeClientId = _newId();
    final invoiceLocalId = _newId();
    final paymentLocalId = _newId();
    final now = DateTime.now();

    final activeOrders = await (_db.select(_db.localOrders)
          ..where((t) =>
              t.workspaceId.equals(workspaceId) &
              t.tableServerId.equals(tableServerId) &
              t.posStatus.isNotValue('cancelled') &
              t.paymentStatus.isNotValue('paid')))
        .get();

    var subtotal = 0.0;
    var tax = 0.0;
    var discount = 0.0;
    var total = 0.0;
    final invoiceItems = <Map<String, dynamic>>[];
    for (final order in activeOrders) {
      subtotal += order.subtotal;
      tax += order.taxAmount;
      discount += order.discountAmount;
      total += order.totalAmount;
      final items = await (_db.select(_db.localOrderItems)
            ..where((t) =>
                t.orderLocalId.equals(order.localId) &
                t.isRemoved.equals(false)))
          .get();
      for (final item in items) {
        invoiceItems.add({
          'item_name': item.name,
          'quantity': item.quantity,
          'unit_price': item.unitPrice,
          'total_amount': item.totalAmount,
        });
      }
    }
    if (total <= 0) {
      total = (payload['total'] as num?)?.toDouble() ??
          ((payload['subtotal'] as num?)?.toDouble() ?? 0);
      subtotal = (payload['subtotal'] as num?)?.toDouble() ?? total;
      tax = (payload['tax_amount'] as num?)?.toDouble() ?? 0;
      discount = (payload['discount_amount'] as num?)?.toDouble() ?? 0;
    }

    final method = (paymentMethod ?? 'cash').trim();
    final invoiceNumber = 'LOCAL-$invoiceLocalId';
    final invoicePayload = {
      'local_id': invoiceLocalId,
      'invoice_number': invoiceNumber,
      'total_amount': total,
      'subtotal': subtotal,
      'tax_amount': tax,
      'discount_amount': discount,
      'payment_method': method,
      'closed_at': now.toUtc().toIso8601String(),
      'table': {'id': tableServerId, 'name': table.name},
      'items': invoiceItems,
      'sync_status': 'pending',
    };

    final nextTablePayload = {
      ...payload,
      'id': tableServerId,
      'status': 'available',
      'session_open': false,
      'session_id': null,
      'session_client_id': null,
      'orders': const [],
      'subtotal': 0,
      'tax_amount': 0,
      'discount_amount': 0,
      'total': 0,
      'last_local_invoice': invoicePayload,
    };

    await _db.transaction(() async {
      await _db.into(_db.localInvoices).insert(
            LocalInvoicesCompanion.insert(
              localId: invoiceLocalId,
              workspaceId: workspaceId,
              deviceId: deviceId.trim(),
              invoiceNumber: Value(invoiceNumber),
              totalAmount: Value(total),
              syncStatus: const Value('pending'),
              payloadJson: Value(jsonEncode(invoicePayload)),
              createdAt: now,
            ),
          );
      await _db.into(_db.localPayments).insert(
            LocalPaymentsCompanion.insert(
              localId: paymentLocalId,
              workspaceId: workspaceId,
              deviceId: deviceId.trim(),
              invoiceLocalId: Value(invoiceLocalId),
              method: method,
              amount: total,
              syncStatus: const Value('pending'),
              clientReference: closeClientId,
              createdAt: now,
            ),
          );
      for (final order in activeOrders) {
        await (_db.update(_db.localOrders)
              ..where((t) =>
                  t.localId.equals(order.localId) &
                  t.workspaceId.equals(workspaceId)))
            .write(
          LocalOrdersCompanion(
            paymentStatus: const Value('paid'),
            posStatus: const Value('completed'),
            updatedAt: Value(now),
          ),
        );
      }
      await (_db.update(_db.localTables)
            ..where((t) =>
                t.localId.equals(localId) &
                t.workspaceId.equals(workspaceId)))
          .write(
        LocalTablesCompanion(
          status: const Value('available'),
          sessionServerId: const Value(null),
          payloadJson: Value(jsonEncode(nextTablePayload)),
          updatedAt: Value(now),
        ),
      );
      await _queue.enqueue(
        workspaceId: workspaceId,
        deviceId: deviceId.trim(),
        entityType: 'table_session',
        entityId: localId,
        operation: 'close',
        payload: {
          'table_server_id': tableServerId,
          'table_local_id': localId,
          'session_server_id': table.sessionServerId,
          'session_client_id': sessionClientId,
          'payment_method': method,
          'invoice_local_id': invoiceLocalId,
          'payment_local_id': paymentLocalId,
        },
        clientReference: closeClientId,
      );
    });

    return {
      'invoice': invoicePayload,
      'table': await getTable(workspaceId, tableServerId),
    };
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
      'session_client_id': payload['session_client_id'],
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
      'session_client_id': payload['session_client_id'],
      'workspace_id': row.workspaceId,
      'orders': payload['orders'] ?? const [],
    };
  }

  Map<String, dynamic> _mergePayload(
    String? previousJson,
    Map<String, dynamic> boardRow,
  ) {
    final previous =
        previousJson == null ? const <String, dynamic>{} : _safeMap(previousJson);
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
    if (boardRow['session_client_id'] == null &&
        previous['session_client_id'] != null) {
      merged['session_client_id'] = previous['session_client_id'];
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
