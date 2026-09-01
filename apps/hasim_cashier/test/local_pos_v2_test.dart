import 'package:drift/drift.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hasim_cashier/core/local_db/app_database.dart';
import 'package:hasim_cashier/core/local_db/workspace_scope.dart';
import 'package:hasim_cashier/core/repositories/catalog_repository.dart';
import 'package:hasim_cashier/core/repositories/sync_queue_repository.dart';
import 'package:hasim_cashier/core/sync/sync_engine_v2.dart';

void main() {
  late AppDatabase db;

  setUp(() {
    db = AppDatabase.memory();
  });

  tearDown(() async {
    await db.close();
  });

  test('workspace A products never appear under workspace B', () async {
    final now = DateTime.now();
    await db.into(db.localProducts).insert(
          LocalProductsCompanion.insert(
            localId: 'prod_1',
            workspaceId: 1,
            serverId: const Value(10),
            name: 'شاي أ',
            price: const Value(5),
            updatedAt: now,
          ),
        );
    await db.into(db.localProducts).insert(
          LocalProductsCompanion.insert(
            localId: 'prod_2',
            workspaceId: 2,
            serverId: const Value(20),
            name: 'قهوة ب',
            price: const Value(8),
            updatedAt: now,
          ),
        );

    final repo = CatalogRepository(db);
    final a = await repo.products(1);
    final b = await repo.products(2);
    expect(a.single['name'], 'شاي أ');
    expect(b.single['name'], 'قهوة ب');
    expect(await repo.products(3), isEmpty);
  });

  test('offline POS ready only after initial sync flag or local products',
      () async {
    expect(await db.isOfflinePosReady(1), isFalse);
    await db.into(db.localProducts).insert(
          LocalProductsCompanion.insert(
            localId: 'prod_1',
            workspaceId: 1,
            name: 'شاي',
            updatedAt: DateTime.now(),
          ),
        );
    expect(await db.isOfflinePosReady(1), isTrue);
    expect(await db.isOfflinePosReady(2), isFalse);

    await db.markInitialSyncCompleted(2);
    expect(await db.isOfflinePosReady(2), isTrue);
  });

  test('sync queue never drops failed ops and keeps client_reference', () async {
    final queue = SyncQueueRepository(db);
    final id = await queue.enqueue(
      workspaceId: 1,
      deviceId: 'device-1',
      entityType: 'order',
      entityId: 'ABC',
      operation: 'create',
      payload: {
        'order_type': 'table',
        'dining_table_id': 7,
        'client_reference': 'ABC',
        'items': [
          {'pos_menu_item_id': 10, 'quantity': 1},
        ],
      },
      clientReference: 'ABC',
    );
    await queue.markFailed(id, 'timeout', retryable: true);
    final pending = await queue.pendingForWorkspace(1);
    expect(pending, hasLength(1));
    expect(pending.single.clientReference, 'ABC');
    expect(pending.single.status, 'pending');
    expect(pending.single.attempts, 1);

    await queue.markFailed(id, 'validation', retryable: false);
    final failed = await queue.pendingForWorkspace(1);
    expect(failed.single.status, 'failed');
    expect(failed.single.clientReference, 'ABC');
  });

  test('sync engine v2 pushes once with stable idempotency key', () async {
    final now = DateTime.now();
    await db.into(db.localOrders).insert(
          LocalOrdersCompanion.insert(
            localId: 'ABC',
            workspaceId: 1,
            deviceId: 'device-1',
            clientReference: 'ABC',
            orderType: 'table',
            tableServerId: const Value(7),
            createdAt: now,
            updatedAt: now,
          ),
        );
    final queue = SyncQueueRepository(db);
    await queue.enqueue(
      workspaceId: 1,
      deviceId: 'device-1',
      entityType: 'order',
      entityId: 'ABC',
      operation: 'create',
      payload: {
        'order_type': 'table',
        'dining_table_id': 7,
        'client_reference': 'ABC',
        'items': [
          {'pos_menu_item_id': 10, 'quantity': 2},
        ],
      },
      clientReference: 'ABC',
    );

    final keys = <String>[];
    final engine = SyncEngineV2(
      db,
      queue,
      postOrder: (payload, key) async {
        keys.add(key);
        expect(payload['client_reference'], 'ABC');
        return {'id': 99};
      },
    );
    final first = await engine.pushPending(workspaceId: 1);
    expect(first.synced, 1);
    expect(keys, ['ABC']);
    final second = await engine.pushPending(workspaceId: 1);
    expect(second.synced, 0);
    expect(keys, ['ABC']);

    final order = await (db.select(db.localOrders)
          ..where((t) => t.localId.equals('ABC')))
        .getSingle();
    expect(order.serverId, 99);
    expect(order.syncStatus, 'synced');
    expect(order.clientReference, 'ABC');
  });

  test('foreign workspace queue is not pushed', () async {
    final queue = SyncQueueRepository(db);
    await queue.enqueue(
      workspaceId: 1,
      deviceId: 'device-1',
      entityType: 'order',
      entityId: 'WA',
      operation: 'create',
      payload: {'client_reference': 'WA'},
      clientReference: 'WA',
    );
    var posts = 0;
    final engine = SyncEngineV2(
      db,
      queue,
      postOrder: (payload, key) async {
        posts++;
        return {'id': 1};
      },
    );
    final result = await engine.pushPending(workspaceId: 2);
    expect(result.synced, 0);
    expect(posts, 0);
  });

  test('workspace scope rejects invalid workspace id', () {
    expect(
      () => const WorkspaceScope(0).assertValid(),
      throwsA(isA<StateError>()),
    );
  });
}
