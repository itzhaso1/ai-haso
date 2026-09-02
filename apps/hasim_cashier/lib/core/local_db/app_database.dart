import 'dart:io';

import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

import 'tables.dart';

part 'app_database.g.dart';

@DriftDatabase(
  tables: [
    LocalDevices,
    LocalCategories,
    LocalProducts,
    LocalTables,
    LocalCustomers,
    LocalOrders,
    LocalOrderItems,
    LocalStockMovements,
    LocalPayments,
    LocalInvoices,
    LocalSettings,
    LocalPermissions,
    SyncQueueItems,
    SyncConflicts,
    SyncMetadata,
  ],
)
class AppDatabase extends _$AppDatabase {
  AppDatabase(super.e);

  @override
  int get schemaVersion => 4;

  @override
  MigrationStrategy get migration => MigrationStrategy(
        onCreate: (m) async {
          await m.createAll();
          await _createPerfIndexes();
        },
        onUpgrade: (m, from, to) async {
          if (from < 3) {
            await _createPerfIndexes();
          }
          if (from < 4) {
            await m.createTable(localStockMovements);
            await m.addColumn(localProducts, localProducts.stock);
            await m.addColumn(syncQueueItems, syncQueueItems.operationUuid);
            await m.addColumn(syncQueueItems, syncQueueItems.syncedAt);
            await _createPerfIndexes();
          }
        },
      );

  Future<void> _createPerfIndexes() async {
    await customStatement(
      'CREATE INDEX IF NOT EXISTS idx_local_orders_ws_sync '
      'ON local_orders (workspace_id, sync_status)',
    );
    await customStatement(
      'CREATE INDEX IF NOT EXISTS idx_local_orders_ws_client_ref '
      'ON local_orders (workspace_id, client_reference)',
    );
    await customStatement(
      'CREATE INDEX IF NOT EXISTS idx_local_order_items_order '
      'ON local_order_items (workspace_id, order_local_id)',
    );
    await customStatement(
      'CREATE INDEX IF NOT EXISTS idx_local_products_ws '
      'ON local_products (workspace_id, is_deleted, is_active)',
    );
    await customStatement(
      'CREATE INDEX IF NOT EXISTS idx_sync_queue_ws_status '
      'ON sync_queue_items (workspace_id, status, next_attempt_at)',
    );
    await customStatement(
      'CREATE INDEX IF NOT EXISTS idx_local_customers_ws '
      'ON local_customers (workspace_id, sync_status)',
    );
    await customStatement(
      'CREATE INDEX IF NOT EXISTS idx_local_orders_ws_table '
      'ON local_orders (workspace_id, table_server_id)',
    );
    await customStatement(
      'CREATE INDEX IF NOT EXISTS idx_local_tables_ws_server '
      'ON local_tables (workspace_id, server_id)',
    );
    await customStatement(
      'CREATE INDEX IF NOT EXISTS idx_sync_conflicts_ws_status '
      'ON sync_conflicts (workspace_id, status)',
    );
    await customStatement(
      'CREATE INDEX IF NOT EXISTS idx_local_stock_movements_ws '
      'ON local_stock_movements (workspace_id, created_at)',
    );
    await customStatement(
      'CREATE UNIQUE INDEX IF NOT EXISTS idx_sync_queue_operation_uuid '
      'ON sync_queue_items (workspace_id, operation_uuid) '
      'WHERE operation_uuid IS NOT NULL',
    );
  }

  /// Production/native opener — one SQLite file per app install.
  static AppDatabase open() {
    return AppDatabase(LazyDatabase(() async {
      final dir = await getApplicationDocumentsDirectory();
      final file = File(p.join(dir.path, 'hasim_cashier_pos_v2.sqlite'));
      return NativeDatabase.createInBackground(file);
    }));
  }

  /// In-memory DB for unit tests.
  static AppDatabase memory() => AppDatabase(NativeDatabase.memory());

  /// File-backed DB for restart / durability tests.
  static AppDatabase file(File file) => AppDatabase(NativeDatabase(file));
}
