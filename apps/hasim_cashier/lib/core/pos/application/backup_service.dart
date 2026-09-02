import 'dart:convert';
import 'dart:io';

import 'package:drift/drift.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

import '../../local_db/app_database.dart';
import '../pos_errors.dart';

const backupFormatVersion = 1;

class BackupService {
  BackupService(this._db);

  final AppDatabase _db;

  Future<List<File>> listBackups() async {
    final dir = await getApplicationDocumentsDirectory();
    final files =
        dir
            .listSync()
            .whereType<File>()
            .where(
              (f) =>
                  f.path.contains('hasim_pos_backup_') &&
                  f.path.endsWith('.json'),
            )
            .toList()
          ..sort((a, b) => b.path.compareTo(a.path));
    return files;
  }

  Future<void> restoreFile(File file, {required bool confirmed}) async {
    final raw = jsonDecode(await file.readAsString());
    if (raw is! Map) {
      throw const DatabaseFailure('ملف النسخة الاحتياطية تالف.');
    }
    await restore(Map<String, dynamic>.from(raw), confirmed: confirmed);
  }

  Future<File> exportBackup({
    required int workspaceId,
    Directory? directory,
  }) async {
    final payload = await _dump(workspaceId);
    final dir = directory ?? await getApplicationDocumentsDirectory();
    final file = File(
      p.join(
        dir.path,
        'hasim_pos_backup_${workspaceId}_${DateTime.now().millisecondsSinceEpoch}.json',
      ),
    );
    await file.writeAsString(
      const JsonEncoder.withIndent('  ').convert(payload),
    );
    return file;
  }

  Future<Map<String, dynamic>> _dump(int workspaceId) async {
    Future<List<Map<String, Object?>>> rows(TableInfo table) async {
      final result = await _db
          .customSelect(
            'SELECT * FROM ${table.actualTableName} WHERE workspace_id = ?',
            variables: [Variable.withInt(workspaceId)],
          )
          .get();
      return [for (final r in result) r.data];
    }

    return {
      'format_version': backupFormatVersion,
      'workspace_id': workspaceId,
      'exported_at': DateTime.now().toIso8601String(),
      'tables': {
        'local_stores': await rows(_db.localStores),
        'local_users': await rows(_db.localUsers),
        'local_categories': await rows(_db.localCategories),
        'local_products': await rows(_db.localProducts),
        'local_customers': await rows(_db.localCustomers),
        'local_tables': await rows(_db.localTables),
        'local_sessions': await rows(_db.localSessions),
        'local_orders': await rows(_db.localOrders),
        'local_order_items': await rows(_db.localOrderItems),
        'local_invoices': await rows(_db.localInvoices),
        'local_payments': await rows(_db.localPayments),
        'local_returns': await rows(_db.localReturns),
        'local_return_items': await rows(_db.localReturnItems),
        'local_stock_movements': await rows(_db.localStockMovements),
        'local_shifts': await rows(_db.localShifts),
        'local_cash_movements': await rows(_db.localCashMovements),
        'local_settings': await rows(_db.localSettings),
        'local_sequences': await _db
            .customSelect('SELECT * FROM local_sequences')
            .get()
            .then((r) => [for (final x in r) x.data]),
      },
    };
  }

  Future<void> restore(
    Map<String, dynamic> payload, {
    required bool confirmed,
  }) async {
    if (!confirmed) {
      throw const DatabaseFailure(
        'يجب تأكيد الاستعادة قبل الكتابة فوق البيانات.',
      );
    }
    final version = payload['format_version'];
    if (version != backupFormatVersion) {
      throw const DatabaseFailure('نسخة النسخة الاحتياطية غير مدعومة.');
    }
    final workspaceId = payload['workspace_id'];
    if (workspaceId is! int) {
      throw const DatabaseFailure('النسخة الاحتياطية تفتقد workspace_id.');
    }
    final tables = payload['tables'];
    if (tables is! Map) {
      throw const DatabaseFailure('ملف النسخة الاحتياطية تالف.');
    }
    await _db.transaction(() async {
      for (final name in const [
        'local_cash_movements',
        'local_return_items',
        'local_returns',
        'local_payments',
        'local_order_items',
        'local_invoices',
        'local_orders',
        'local_stock_movements',
        'local_sessions',
        'local_draft_cart_lines',
        'local_draft_carts',
        'local_shifts',
        'local_customers',
        'local_products',
        'local_categories',
        'local_tables',
        'local_users',
        'local_stores',
        'local_settings',
      ]) {
        await _db.customStatement('DELETE FROM $name WHERE workspace_id = ?', [
          workspaceId,
        ]);
      }
      for (final entry in tables.entries) {
        final table = entry.key.toString();
        final rows = entry.value;
        if (rows is! List) continue;
        for (final raw in rows) {
          if (raw is! Map) continue;
          final cols = raw.keys.map((k) => k.toString()).toList();
          if (cols.isEmpty) continue;
          final placeholders = List.filled(cols.length, '?').join(',');
          await _db.customStatement(
            'INSERT OR REPLACE INTO $table (${cols.join(',')}) VALUES ($placeholders)',
            [for (final c in cols) raw[c]],
          );
        }
      }
    });
  }
}
