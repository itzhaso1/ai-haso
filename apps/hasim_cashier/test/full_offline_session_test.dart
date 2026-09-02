import 'dart:convert';

import 'package:drift/drift.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hasim_cashier/core/local_db/app_database.dart';
import 'package:hasim_cashier/core/local_db/local_ids.dart';
import 'package:hasim_cashier/core/offline/conflict_strategy.dart';
import 'package:hasim_cashier/core/repositories/orders_repository.dart';
import 'package:hasim_cashier/core/repositories/sync_queue_repository.dart';
import 'package:hasim_cashier/core/repositories/tables_repository.dart';
import 'package:hasim_cashier/core/sync/sync_engine_v2.dart';

void main() {
  late AppDatabase db;
  late SyncQueueRepository queue;
  late TablesRepository tables;
  late OrdersRepository orders;

  setUp(() {
    db = AppDatabase.memory();
    queue = SyncQueueRepository(db);
    tables = TablesRepository(db, queue);
    orders = OrdersRepository(db, queue);
  });

  tearDown(() async {
    await db.close();
  });

  Future<void> seedTable({int workspaceId = 1, int tableId = 10}) async {
    await db.into(db.localTables).insert(
          LocalTablesCompanion.insert(
            localId: LocalIds.table(workspaceId, tableId),
            workspaceId: workspaceId,
            serverId: Value(tableId),
            name: 'T$tableId',
            status: const Value('available'),
            payloadJson: Value(jsonEncode({
              'id': tableId,
              'name': 'T$tableId',
              'status': 'available',
            })),
            updatedAt: DateTime.now(),
          ),
        );
  }

  test('open + close session work fully offline and enqueue sync', () async {
    await seedTable();
    final opened = await tables.openSessionLocal(
      workspaceId: 1,
      deviceId: 'dev-1',
      tableServerId: 10,
    );
    expect(opened['status'], 'occupied');
    expect(opened['session_client_id'], isNotEmpty);

    final pendingOpen = await queue.pendingForWorkspace(1);
    expect(
      pendingOpen.any(
        (r) => r.entityType == 'table_session' && r.operation == 'open',
      ),
      isTrue,
    );

    await orders.createTableOrder(
      workspaceId: 1,
      deviceId: 'dev-1',
      tableId: 10,
      clientReference: 'ord-offline-1',
      items: [
        {
          'pos_menu_item_id': 1,
          'name': 'شاي',
          'quantity': 2,
          'unit_price': 5,
          'total_amount': 10,
        },
      ],
    );

    final closed = await tables.closeSessionLocal(
      workspaceId: 1,
      deviceId: 'dev-1',
      tableServerId: 10,
      paymentMethod: 'cash',
    );
    expect(closed['invoice'], isA<Map>());
    expect(closed['invoice']['payment_method'], 'cash');
    expect(closed['invoice']['total_amount'], 10);

    final table = await tables.getTable(1, 10);
    expect(table?['status'], 'available');

    final pending = await queue.pendingForWorkspace(1);
    expect(
      pending.any(
        (r) => r.entityType == 'table_session' && r.operation == 'close',
      ),
      isTrue,
    );

    final invoices = await db.select(db.localInvoices).get();
    expect(invoices, hasLength(1));
    expect(invoices.single.syncStatus, 'pending');

    final payments = await db.select(db.localPayments).get();
    expect(payments, hasLength(1));
  });

  test('sync engine pushes open then orders then close in priority order', () async {
    await seedTable();
    await tables.openSessionLocal(
      workspaceId: 1,
      deviceId: 'dev-1',
      tableServerId: 10,
    );
    await orders.createTableOrder(
      workspaceId: 1,
      deviceId: 'dev-1',
      tableId: 10,
      clientReference: 'ord-1',
      items: [
        {
          'pos_menu_item_id': 3,
          'name': 'قهوة',
          'quantity': 1,
          'unit_price': 8,
          'total_amount': 8,
        },
      ],
    );
    await tables.closeSessionLocal(
      workspaceId: 1,
      deviceId: 'dev-1',
      tableServerId: 10,
      paymentMethod: 'card',
    );

    final calls = <String>[];
    final engine = SyncEngineV2(
      db,
      queue,
      postOrder: (payload, key) async {
        calls.add('order:$key');
        return {'id': 501, ...payload};
      },
      postSessionOpen: (tableId, key) async {
        calls.add('open:$key');
        return {
          'session_id': 77,
          'table_id': tableId,
          'status': 'open',
        };
      },
      postSessionClose: (tableId, sessionId, payload, key) async {
        calls.add('close:$key');
        expect(sessionId, 77);
        return {
          'invoice': {
            'id': 55,
            'invoice_number': 'INV-55',
            'total_amount': 8,
            'subtotal': 8,
            'discount_amount': 0,
            'currency': 'SAR',
            'payment_method': payload['payment_method'],
          },
        };
      },
    );

    final result = await engine.pushPending(workspaceId: 1);
    expect(result.failed, 0);
    expect(calls.first, startsWith('open:'));
    expect(calls, contains('order:ord-1'));
    expect(calls.last, startsWith('close:'));

    final remaining = await queue.pendingForWorkspace(1);
    expect(remaining, isEmpty);

    final invoices = await db.select(db.localInvoices).get();
    expect(invoices.single.syncStatus, 'synced');
    expect(invoices.single.serverId, 55);
  });

  test('takeaway invoice enqueues offline and syncs after order', () async {
    final order = await orders.createTakeawayOrder(
      workspaceId: 1,
      deviceId: 'dev-1',
      clientReference: 'tw-1',
      items: [
        {
          'pos_menu_item_id': 2,
          'name': 'برجر',
          'quantity': 1,
          'unit_price': 20,
          'total_amount': 20,
        },
      ],
    );
    expect(order['is_local_pending'], isTrue);

    final invoice = await orders.enqueueInvoiceForOrder(
      workspaceId: 1,
      deviceId: 'dev-1',
      orderLocalId: 'tw-1',
      paymentMethod: 'cash',
    );
    expect(invoice['invoice_number'], startsWith('LOCAL-'));
    expect(invoice['total_amount'], 20);

    final pending = await queue.pendingForWorkspace(1);
    expect(pending.any((r) => r.entityType == 'invoice'), isTrue);

    final calls = <String>[];
    final engine = SyncEngineV2(
      db,
      queue,
      postOrder: (payload, key) async {
        calls.add('order');
        return {'id': 900, ...payload};
      },
      postInvoice: (orderServerId, key) async {
        calls.add('invoice:$orderServerId');
        expect(orderServerId, 900);
        return {
          'invoice_id': 66,
          'invoice_number': 'INV-66',
          'total_amount': 20,
          'currency': 'SAR',
        };
      },
    );
    final result = await engine.pushPending(workspaceId: 1);
    expect(result.failed, 0);
    expect(calls, ['order', 'invoice:900']);
    expect(await queue.pendingForWorkspace(1), isEmpty);
  });

  test('conflict policy allows offline POS session/payment/invoice', () {
    expect(
      ConflictStrategy.forDomain('open_session'),
      ConflictPolicy.detectAndRecord,
    );
    expect(
      ConflictStrategy.forDomain('close_table'),
      ConflictPolicy.detectAndRecord,
    );
    expect(
      ConflictStrategy.forDomain('payment'),
      ConflictPolicy.detectAndRecord,
    );
    expect(
      ConflictStrategy.forDomain('invoice'),
      ConflictPolicy.detectAndRecord,
    );
    expect(
      ConflictStrategy.forDomain('transfer'),
      ConflictPolicy.requireOnline,
    );
  });
}
