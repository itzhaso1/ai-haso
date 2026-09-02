import 'dart:convert';

import 'package:drift/drift.dart';

import '../api/cashier_api.dart';
import '../local_db/app_database.dart';
import '../local_db/workspace_scope.dart';
import '../repositories/sync_queue_repository.dart';
import 'sync_pull_applier.dart';

/// Sync Engine v2 — push SQLite sync_queue, then pull incremental changes.
/// Primary POS sync path (Hive outbox is legacy migration only).
class SyncEngineV2 {
  SyncEngineV2(
    this._db,
    this._queue, {
    CashierApiClient? api,
    SyncPullApplier? pullApplier,
    Future<Map<String, dynamic>> Function(
      Map<String, dynamic> payload,
      String idempotencyKey,
    )? postOrder,
    Future<Map<String, dynamic>> Function(
      int serverOrderId,
      Map<String, dynamic> payload,
    )? postOrderItems,
    Future<void> Function(int serverOrderId)? deleteOrder,
    Future<Map<String, dynamic>> Function(int since, int limit)? fetchChanges,
    Future<Map<String, dynamic>> Function(
      int tableServerId,
      String idempotencyKey,
    )? postSessionOpen,
    Future<Map<String, dynamic>> Function(
      int tableServerId,
      int sessionServerId,
      Map<String, dynamic> payload,
      String idempotencyKey,
    )? postSessionClose,
    Future<Map<String, dynamic>> Function(
      int orderServerId,
      String idempotencyKey,
    )? postInvoice,
    Future<Map<String, dynamic>> Function(int tableServerId)? getTable,
  })  : _api = api,
        _pullApplier = pullApplier ?? SyncPullApplier(_db),
        _postOrder = postOrder,
        _postOrderItems = postOrderItems,
        _deleteOrder = deleteOrder,
        _fetchChanges = fetchChanges,
        _postSessionOpen = postSessionOpen,
        _postSessionClose = postSessionClose,
        _postInvoice = postInvoice,
        _getTable = getTable;

  final AppDatabase _db;
  final SyncQueueRepository _queue;
  final CashierApiClient? _api;
  final SyncPullApplier _pullApplier;
  final Future<Map<String, dynamic>> Function(
    Map<String, dynamic> payload,
    String idempotencyKey,
  )? _postOrder;
  final Future<Map<String, dynamic>> Function(
    int serverOrderId,
    Map<String, dynamic> payload,
  )? _postOrderItems;
  final Future<void> Function(int serverOrderId)? _deleteOrder;
  final Future<Map<String, dynamic>> Function(int since, int limit)?
      _fetchChanges;
  final Future<Map<String, dynamic>> Function(
    int tableServerId,
    String idempotencyKey,
  )? _postSessionOpen;
  final Future<Map<String, dynamic>> Function(
    int tableServerId,
    int sessionServerId,
    Map<String, dynamic> payload,
    String idempotencyKey,
  )? _postSessionClose;
  final Future<Map<String, dynamic>> Function(
    int orderServerId,
    String idempotencyKey,
  )? _postInvoice;
  final Future<Map<String, dynamic>> Function(int tableServerId)? _getTable;

  var _flushing = false;
  bool get isFlushing => _flushing;

  /// Push pending local ops, then pull server deltas. Pull failure never
  /// drops the local sync_queue or pending orders.
  Future<SyncEngineV2Result> syncBidirectional({
    required int workspaceId,
    String? deviceId,
  }) async {
    final push = await pushPending(workspaceId: workspaceId);
    try {
      final pull = await pullChanges(
        workspaceId: workspaceId,
        deviceId: deviceId,
      );
      return SyncEngineV2Result(
        synced: push.synced,
        failed: push.failed,
        keptPending: push.keptPending,
        authRequired: push.authRequired || pull.authRequired,
        skippedInFlight: push.skippedInFlight,
        pulled: pull.pulled,
        cursor: pull.cursor,
        pullFailed: pull.pullFailed,
      );
    } catch (e) {
      // Keep pending local ops + existing cursor intact.
      return SyncEngineV2Result(
        synced: push.synced,
        failed: push.failed,
        keptPending: push.keptPending,
        authRequired: push.authRequired,
        skippedInFlight: push.skippedInFlight,
        pullFailed: true,
        pullError: e.toString(),
      );
    }
  }

  /// After full Initial Sync snapshot, anchor cursor to server head without
  /// re-applying historical create events.
  Future<int> anchorCursorToServerHead({
    required int workspaceId,
    String? deviceId,
  }) async {
    final data = await _loadChanges(since: 0, limit: 0);
    final serverCursor = (data['server_cursor'] as num?)?.toInt() ??
        (data['cursor'] as num?)?.toInt() ??
        0;
    await _db.writeCursor(workspaceId, '$serverCursor', deviceId: deviceId);
    return serverCursor;
  }

  Future<SyncEngineV2Result> pullChanges({
    required int workspaceId,
    String? deviceId,
    int pageLimit = 200,
  }) async {
    if (workspaceId <= 0) return const SyncEngineV2Result();
    final rawCursor = await _db.readCursor(workspaceId);
    var since = int.tryParse(rawCursor ?? '') ?? 0;
    var pulled = 0;
    var cursor = since;
    var hasMore = true;
    while (hasMore) {
      final data = await _loadChanges(since: since, limit: pageLimit);
      final changes = <Map<String, dynamic>>[];
      if (data['changes'] is List) {
        for (final item in data['changes'] as List) {
          if (item is Map) changes.add(Map<String, dynamic>.from(item));
        }
      }
      final responseCursor = (data['cursor'] as num?)?.toInt() ?? since;
      cursor = await _pullApplier.applyBatch(
        workspaceId: workspaceId,
        fromCursor: since,
        responseCursor: responseCursor,
        changes: changes,
        deviceId: deviceId,
      );
      pulled += changes.length;
      hasMore = data['has_more'] == true;
      since = cursor;
      if (changes.isEmpty) break;
    }
    return SyncEngineV2Result(pulled: pulled, cursor: cursor);
  }

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
      final ordered = [...rows]..sort((a, b) {
          final pa = _pushPriority(a);
          final pb = _pushPriority(b);
          if (pa != pb) return pa.compareTo(pb);
          return a.createdAt.compareTo(b.createdAt);
        });
      for (final row in ordered) {
        if (row.workspaceId != workspaceId) continue;
        if (row.status == 'cancelled' || row.status == 'synced') continue;
        if (row.nextAttemptAt != null &&
            row.nextAttemptAt!.isAfter(DateTime.now())) {
          kept++;
          continue;
        }
        final supported = row.entityType == 'order' ||
            row.entityType == 'customer' ||
            row.entityType == 'table_session' ||
            row.entityType == 'invoice';
        if (!supported) {
          kept++;
          continue;
        }

        // Close / takeaway invoice wait until dependent orders are pushed.
        if (row.entityType == 'table_session' && row.operation == 'close') {
          final payload = _decode(row.payloadJson);
          final tableId = (payload['table_server_id'] as num?)?.toInt();
          if (tableId != null &&
              await _hasUnsyncedOrdersForTable(workspaceId, tableId)) {
            kept++;
            continue;
          }
        }
        if (row.entityType == 'invoice' && row.operation == 'create') {
          final payload = _decode(row.payloadJson);
          final orderLocalId = '${payload['order_local_id'] ?? ''}';
          if (orderLocalId.isNotEmpty &&
              await _orderNeedsServerId(workspaceId, orderLocalId)) {
            kept++;
            continue;
          }
        }

        await _queue.markSyncing(row.id);
        try {
          if (row.entityType == 'customer') {
            await _pushCustomer(row);
            synced++;
          } else if (row.entityType == 'table_session' &&
              row.operation == 'open') {
            await _pushSessionOpen(row);
            synced++;
          } else if (row.entityType == 'table_session' &&
              row.operation == 'close') {
            await _pushSessionClose(row);
            synced++;
          } else if (row.entityType == 'invoice') {
            await _pushInvoice(row);
            synced++;
          } else if (row.operation == 'create') {
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
            if (row.entityType == 'order') {
              await _markOrderFailed(row.entityId, e.message);
            } else if (row.entityType == 'customer') {
              await _markCustomerFailed(row.entityId, e.message);
            }
            failed++;
          } else {
            await _queue.markFailed(row.id, e.message, retryable: true);
            if (row.entityType == 'order') {
              await _markOrderPending(row.entityId, e.message);
            } else if (row.entityType == 'customer') {
              await _markCustomerPending(row.entityId, e.message);
            }
            kept++;
          }
        } catch (e) {
          await _queue.markFailed(row.id, e.toString(), retryable: true);
          if (row.entityType == 'order') {
            await _markOrderPending(row.entityId, e.toString());
          } else if (row.entityType == 'customer') {
            await _markCustomerPending(row.entityId, e.toString());
          }
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

  int _pushPriority(SyncQueueItem row) {
    if (row.entityType == 'customer') return 0;
    if (row.entityType == 'table_session' && row.operation == 'open') return 1;
    if (row.entityType == 'order') return 2;
    if (row.entityType == 'invoice') return 3;
    if (row.entityType == 'table_session' && row.operation == 'close') return 4;
    return 9;
  }

  Future<bool> _hasUnsyncedOrdersForTable(int workspaceId, int tableId) async {
    final rows = await (_db.select(_db.localOrders)
          ..where((t) =>
              t.workspaceId.equals(workspaceId) &
              t.tableServerId.equals(tableId) &
              t.posStatus.isNotValue('cancelled') &
              (t.syncStatus.equals('pending') |
                  t.syncStatus.equals('syncing') |
                  t.syncStatus.equals('failed'))))
        .get();
    return rows.isNotEmpty;
  }

  Future<bool> _orderNeedsServerId(int workspaceId, String orderLocalId) async {
    final order = await (_db.select(_db.localOrders)
          ..where((t) =>
              t.workspaceId.equals(workspaceId) &
              t.localId.equals(orderLocalId)))
        .getSingleOrNull();
    if (order == null) return true;
    return order.serverId == null || order.serverId! <= 0;
  }

  Future<void> _pushSessionOpen(SyncQueueItem row) async {
    final payload = _decode(row.payloadJson);
    final tableId = (payload['table_server_id'] as num?)?.toInt();
    if (tableId == null || tableId <= 0) {
      throw StateError('table_session open requires table_server_id');
    }
    final Map<String, dynamic> data;
    final opener = _postSessionOpen;
    if (opener != null) {
      data = await opener(tableId, row.clientReference);
    } else {
      final api = _api;
      if (api == null) {
        throw StateError('SyncEngineV2 requires API for session open');
      }
      data = await api.post(
        '/tables/$tableId/sessions/open',
        idempotencyKey: row.clientReference,
      );
    }
    final sessionId = (data['session_id'] as num?)?.toInt();
    final now = DateTime.now();
    await _db.transaction(() async {
      final table = await (_db.select(_db.localTables)
            ..where((t) =>
                t.localId.equals(row.entityId) &
                t.workspaceId.equals(row.workspaceId)))
          .getSingleOrNull();
      if (table != null) {
        final prev = _decode(table.payloadJson);
        final next = {
          ...prev,
          'session_id': sessionId,
          'session_open': true,
          'status': 'occupied',
          if (data['opened_at'] != null) 'opened_at': data['opened_at'],
        };
        await (_db.update(_db.localTables)
              ..where((t) => t.localId.equals(table.localId)))
            .write(
          LocalTablesCompanion(
            sessionServerId: Value(sessionId),
            status: const Value('occupied'),
            payloadJson: Value(jsonEncode(next)),
            updatedAt: Value(now),
          ),
        );
      }
      await _queue.markSynced(row.id);
    });
  }

  Future<void> _pushSessionClose(SyncQueueItem row) async {
    final payload = _decode(row.payloadJson);
    final tableId = (payload['table_server_id'] as num?)?.toInt();
    if (tableId == null || tableId <= 0) {
      throw StateError('table_session close requires table_server_id');
    }

    var sessionId = (payload['session_server_id'] as num?)?.toInt();
    if (sessionId == null || sessionId <= 0) {
      final table = await (_db.select(_db.localTables)
            ..where((t) =>
                t.localId.equals(row.entityId) &
                t.workspaceId.equals(row.workspaceId)))
          .getSingleOrNull();
      sessionId = table?.sessionServerId;
    }
    if (sessionId == null || sessionId <= 0) {
      final getter = _getTable;
      if (getter != null) {
        final remote = await getter(tableId);
        sessionId = (remote['session_id'] as num?)?.toInt();
      } else {
        final api = _api;
        if (api != null) {
          final remote = await api.get('/tables/$tableId');
          sessionId = (remote['session_id'] as num?)?.toInt();
        }
      }
    }
    if (sessionId == null || sessionId <= 0) {
      // Session may already be closed server-side (e.g. auto-open + empty).
      await _markInvoiceSyncedFromClose(row, null);
      await _queue.markSynced(row.id);
      return;
    }

    final closeBody = <String, dynamic>{
      if (payload['payment_method'] != null)
        'payment_method': payload['payment_method'],
    };
    final Map<String, dynamic> data;
    final closer = _postSessionClose;
    if (closer != null) {
      data = await closer(tableId, sessionId, closeBody, row.clientReference);
    } else {
      final api = _api;
      if (api == null) {
        throw StateError('SyncEngineV2 requires API for session close');
      }
      data = await api.post(
        '/tables/$tableId/sessions/$sessionId/close',
        data: closeBody,
        idempotencyKey: row.clientReference,
      );
    }
    final invoice = data['invoice'] is Map
        ? Map<String, dynamic>.from(data['invoice'] as Map)
        : null;
    await _markInvoiceSyncedFromClose(row, invoice);
    await _queue.markSynced(row.id);
  }

  Future<void> _markInvoiceSyncedFromClose(
    SyncQueueItem row,
    Map<String, dynamic>? invoice,
  ) async {
    final payload = _decode(row.payloadJson);
    final invoiceLocalId = '${payload['invoice_local_id'] ?? ''}';
    final paymentLocalId = '${payload['payment_local_id'] ?? ''}';
    final now = DateTime.now();
    if (invoiceLocalId.isNotEmpty) {
      final existing = await (_db.select(_db.localInvoices)
            ..where((t) => t.localId.equals(invoiceLocalId)))
          .getSingleOrNull();
      final prev = existing == null
          ? <String, dynamic>{}
          : _decode(existing.payloadJson);
      final merged = {
        ...prev,
        if (invoice != null) ...invoice,
        'sync_status': 'synced',
      };
      await (_db.update(_db.localInvoices)
            ..where((t) => t.localId.equals(invoiceLocalId)))
          .write(
        LocalInvoicesCompanion(
          serverId: Value((invoice?['id'] as num?)?.toInt()),
          invoiceNumber: Value(
            invoice?['invoice_number']?.toString() ?? existing?.invoiceNumber,
          ),
          totalAmount: Value(
            (invoice?['total_amount'] as num?)?.toDouble() ??
                existing?.totalAmount ??
                0,
          ),
          syncStatus: const Value('synced'),
          payloadJson: Value(jsonEncode(merged)),
        ),
      );
    }
    if (paymentLocalId.isNotEmpty) {
      await (_db.update(_db.localPayments)
            ..where((t) => t.localId.equals(paymentLocalId)))
          .write(
        const LocalPaymentsCompanion(syncStatus: Value('synced')),
      );
    }
    // Ensure table is available after successful close sync.
    await (_db.update(_db.localTables)
          ..where((t) =>
              t.localId.equals(row.entityId) &
              t.workspaceId.equals(row.workspaceId)))
        .write(
      LocalTablesCompanion(
        status: const Value('available'),
        sessionServerId: const Value(null),
        updatedAt: Value(now),
      ),
    );
  }

  Future<void> _pushInvoice(SyncQueueItem row) async {
    final payload = _decode(row.payloadJson);
    var orderServerId = (payload['order_server_id'] as num?)?.toInt();
    final orderLocalId = '${payload['order_local_id'] ?? ''}';
    if ((orderServerId == null || orderServerId <= 0) &&
        orderLocalId.isNotEmpty) {
      final order = await (_db.select(_db.localOrders)
            ..where((t) =>
                t.workspaceId.equals(row.workspaceId) &
                t.localId.equals(orderLocalId)))
          .getSingleOrNull();
      orderServerId = order?.serverId;
    }
    if (orderServerId == null || orderServerId <= 0) {
      throw StateError('invoice create requires synced order_server_id');
    }
    final Map<String, dynamic> data;
    final poster = _postInvoice;
    if (poster != null) {
      data = await poster(orderServerId, row.clientReference);
    } else {
      final api = _api;
      if (api == null) {
        throw StateError('SyncEngineV2 requires API for invoice push');
      }
      data = await api.post(
        '/orders/$orderServerId/invoice',
        idempotencyKey: row.clientReference,
      );
    }
    final invoiceLocalId = row.entityId;
    final existing = await (_db.select(_db.localInvoices)
          ..where((t) => t.localId.equals(invoiceLocalId)))
        .getSingleOrNull();
    final prev =
        existing == null ? <String, dynamic>{} : _decode(existing.payloadJson);
    final merged = {
      ...prev,
      'id': data['invoice_id'],
      'invoice_number': data['invoice_number'],
      'total_amount': data['total_amount'],
      'currency': data['currency'],
      'sync_status': 'synced',
    };
    await _db.transaction(() async {
      await (_db.update(_db.localInvoices)
            ..where((t) => t.localId.equals(invoiceLocalId)))
          .write(
        LocalInvoicesCompanion(
          serverId: Value((data['invoice_id'] as num?)?.toInt()),
          invoiceNumber: Value(data['invoice_number']?.toString()),
          totalAmount: Value(
            (data['total_amount'] as num?)?.toDouble() ??
                existing?.totalAmount ??
                0,
          ),
          syncStatus: const Value('synced'),
          payloadJson: Value(jsonEncode(merged)),
        ),
      );
      await (_db.update(_db.localPayments)
            ..where((t) => t.invoiceLocalId.equals(invoiceLocalId)))
          .write(
        const LocalPaymentsCompanion(syncStatus: Value('synced')),
      );
      await _queue.markSynced(row.id);
    });
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
  }

  Future<void> _pushCustomer(SyncQueueItem row) async {
    final payload = _decode(row.payloadJson);
    payload['client_reference'] = row.clientReference;
    final api = _api;
    if (api == null) {
      throw StateError('SyncEngineV2 requires API for customer push');
    }
    final data = await api.post(
      '/customers',
      data: payload,
      idempotencyKey: row.clientReference,
    );
    final serverId = data['id'] is num
        ? (data['id'] as num).toInt()
        : int.tryParse('${data['id']}');
    await _db.transaction(() async {
      await (_db.update(_db.localCustomers)
            ..where((t) =>
                t.localId.equals(row.entityId) &
                t.workspaceId.equals(row.workspaceId)))
          .write(
        LocalCustomersCompanion(
          serverId: Value(serverId),
          syncStatus: const Value('synced'),
          name: Value('${data['name'] ?? payload['name'] ?? ''}'),
          phone: Value(data['phone'] as String? ?? payload['phone'] as String?),
          updatedAt: Value(DateTime.now()),
        ),
      );
      await _queue.markSynced(row.id);
    });
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

  Future<void> _markCustomerFailed(String localId, String error) async {
    await (_db.update(_db.localCustomers)
          ..where((t) => t.localId.equals(localId)))
        .write(
      LocalCustomersCompanion(
        syncStatus: const Value('failed'),
        updatedAt: Value(DateTime.now()),
      ),
    );
  }

  Future<void> _markCustomerPending(String localId, String error) async {
    await (_db.update(_db.localCustomers)
          ..where((t) => t.localId.equals(localId)))
        .write(
      LocalCustomersCompanion(
        syncStatus: const Value('pending'),
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

  Future<Map<String, dynamic>> _loadChanges({
    required int since,
    required int limit,
  }) {
    final fetcher = _fetchChanges;
    if (fetcher != null) return fetcher(since, limit);
    final api = _api;
    if (api == null) {
      throw StateError('SyncEngineV2 requires API or fetchChanges');
    }
    return api.get('/sync/changes', query: {
      'since': since,
      'limit': limit,
    });
  }
}

class SyncEngineV2Result {
  const SyncEngineV2Result({
    this.synced = 0,
    this.failed = 0,
    this.keptPending = 0,
    this.authRequired = false,
    this.skippedInFlight = false,
    this.pulled = 0,
    this.cursor,
    this.pullFailed = false,
    this.pullError,
  });

  final int synced;
  final int failed;
  final int keptPending;
  final bool authRequired;
  final bool skippedInFlight;
  final int pulled;
  final int? cursor;
  final bool pullFailed;
  final String? pullError;
}
