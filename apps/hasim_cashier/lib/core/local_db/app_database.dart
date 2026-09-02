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
    LocalStores,
    LocalUsers,
    LocalSequences,
    LocalSessions,
    LocalDraftCarts,
    LocalDraftCartLines,
    LocalReturns,
    LocalReturnItems,
    LocalShifts,
    LocalCashMovements,
  ],
)
class AppDatabase extends _$AppDatabase {
  AppDatabase(super.e);

  @override
  int get schemaVersion => 5;

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
      if (from < 5) {
        await m.createTable(localStores);
        await m.createTable(localUsers);
        await m.createTable(localSequences);
        await m.createTable(localSessions);
        await m.createTable(localDraftCarts);
        await m.createTable(localDraftCartLines);
        await m.createTable(localReturns);
        await m.createTable(localReturnItems);
        await m.createTable(localShifts);
        await m.createTable(localCashMovements);
        await _addColumnIfMissing(
          m,
          localCategories,
          localCategories.createdAt,
        );
        await _addColumnIfMissing(m, localProducts, localProducts.cost);
        await _addColumnIfMissing(m, localProducts, localProducts.taxRate);
        await _addColumnIfMissing(m, localProducts, localProducts.trackStock);
        await _addColumnIfMissing(m, localProducts, localProducts.imagePath);
        await _addColumnIfMissing(m, localProducts, localProducts.createdAt);
        await _addColumnIfMissing(m, localTables, localTables.tableNumber);
        await _addColumnIfMissing(m, localTables, localTables.createdAt);
        await _addColumnIfMissing(m, localCustomers, localCustomers.email);
        await _addColumnIfMissing(m, localCustomers, localCustomers.notes);
        await _addColumnIfMissing(m, localCustomers, localCustomers.createdAt);
        await _addColumnIfMissing(m, localOrders, localOrders.orderNumber);
        await _addColumnIfMissing(m, localOrders, localOrders.sessionLocalId);
        await _addColumnIfMissing(m, localOrders, localOrders.customerLocalId);
        await _addColumnIfMissing(m, localOrders, localOrders.createdByUserId);
        await _addColumnIfMissing(
          m,
          localOrders,
          localOrders.fulfillmentStatus,
        );
        await _addColumnIfMissing(m, localOrders, localOrders.discountPercent);
        await _addColumnIfMissing(m, localOrders, localOrders.completedAt);
        await _addColumnIfMissing(
          m,
          localOrderItems,
          localOrderItems.skuSnapshot,
        );
        await _addColumnIfMissing(
          m,
          localOrderItems,
          localOrderItems.barcodeSnapshot,
        );
        await _addColumnIfMissing(
          m,
          localOrderItems,
          localOrderItems.costSnapshot,
        );
        await _addColumnIfMissing(m, localOrderItems, localOrderItems.taxRate);
        await _addColumnIfMissing(
          m,
          localOrderItems,
          localOrderItems.taxAmount,
        );
        await _addColumnIfMissing(m, localOrderItems, localOrderItems.notes);
        await _addColumnIfMissing(
          m,
          localOrderItems,
          localOrderItems.createdAt,
        );
        await _addColumnIfMissing(
          m,
          localStockMovements,
          localStockMovements.beforeQuantity,
        );
        await _addColumnIfMissing(
          m,
          localStockMovements,
          localStockMovements.afterQuantity,
        );
        await _addColumnIfMissing(
          m,
          localStockMovements,
          localStockMovements.userId,
        );
        await _addColumnIfMissing(m, localPayments, localPayments.tendered);
        await _addColumnIfMissing(m, localPayments, localPayments.changeDue);
        await _addColumnIfMissing(m, localPayments, localPayments.shiftLocalId);
        await _addColumnIfMissing(
          m,
          localInvoices,
          localInvoices.localInvoiceNumber,
        );
        await _addColumnIfMissing(
          m,
          localInvoices,
          localInvoices.serverInvoiceNumber,
        );
        await _addColumnIfMissing(m, localInvoices, localInvoices.orderLocalId);
        await _addColumnIfMissing(m, localInvoices, localInvoices.status);
        await _addColumnIfMissing(m, localInvoices, localInvoices.subtotal);
        await _addColumnIfMissing(
          m,
          localInvoices,
          localInvoices.discountAmount,
        );
        await _addColumnIfMissing(m, localInvoices, localInvoices.taxAmount);
        await _addColumnIfMissing(
          m,
          localInvoices,
          localInvoices.createdByUserId,
        );
        await _createPerfIndexes();
      }
    },
  );

  Future<void> _addColumnIfMissing(
    Migrator m,
    TableInfo table,
    GeneratedColumn column,
  ) async {
    try {
      await m.addColumn(table, column);
    } catch (_) {
      // Column already exists on some migrated databases.
    }
  }

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
    await customStatement(
      'CREATE UNIQUE INDEX IF NOT EXISTS idx_local_stores_workspace '
      'ON local_stores (workspace_id)',
    );
    await customStatement(
      'CREATE UNIQUE INDEX IF NOT EXISTS idx_local_users_ws_username '
      'ON local_users (workspace_id, username)',
    );
    await customStatement(
      'CREATE UNIQUE INDEX IF NOT EXISTS idx_local_invoices_local_number '
      'ON local_invoices (workspace_id, local_invoice_number) '
      'WHERE local_invoice_number IS NOT NULL',
    );
    await customStatement(
      'CREATE INDEX IF NOT EXISTS idx_local_sessions_ws_table '
      'ON local_sessions (workspace_id, table_local_id, status)',
    );
    await customStatement(
      'CREATE INDEX IF NOT EXISTS idx_local_shifts_ws_status '
      'ON local_shifts (workspace_id, status)',
    );
    await customStatement(
      'CREATE INDEX IF NOT EXISTS idx_local_draft_lines_cart '
      'ON local_draft_cart_lines (cart_local_id)',
    );
  }

  /// Production/native opener — one SQLite file per app install.
  static AppDatabase open() {
    return AppDatabase(
      LazyDatabase(() async {
        final dir = await getApplicationDocumentsDirectory();
        final file = File(p.join(dir.path, 'hasim_cashier_pos_v2.sqlite'));
        return NativeDatabase.createInBackground(file);
      }),
    );
  }

  /// In-memory DB for unit tests.
  static AppDatabase memory() => AppDatabase(NativeDatabase.memory());

  /// File-backed DB for restart / durability tests.
  static AppDatabase file(File file) => AppDatabase(NativeDatabase(file));
}
