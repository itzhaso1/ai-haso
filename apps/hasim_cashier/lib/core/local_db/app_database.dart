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
  int get schemaVersion => 2;

  @override
  MigrationStrategy get migration => MigrationStrategy(
        onCreate: (m) async {
          await m.createAll();
        },
        onUpgrade: (m, from, to) async {
          // v2: catalog/table local_ids become workspace-scoped via Initial Sync
          // rewrite and OrdersRepository writers — no destructive DDL required.
        },
      );

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
