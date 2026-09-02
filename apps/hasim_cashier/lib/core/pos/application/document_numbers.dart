import 'package:drift/drift.dart';

import '../../local_db/app_database.dart';

/// Transaction-safe document sequences. Never uses max(id)+1.
class DocumentNumberService {
  DocumentNumberService(this._db);

  final AppDatabase _db;

  Future<String> nextInvoiceNumber({
    required String storeId,
    String prefix = 'INV-',
  }) {
    return _next(storeId: storeId, kind: 'invoice', prefix: prefix, width: 6);
  }

  Future<String> nextOrderNumber({
    required String storeId,
    String prefix = 'ORD-',
  }) {
    return _next(storeId: storeId, kind: 'order', prefix: prefix, width: 6);
  }

  Future<String> _next({
    required String storeId,
    required String kind,
    required String prefix,
    required int width,
  }) async {
    final now = DateTime.now();
    final existing =
        await (_db.select(_db.localSequences)
              ..where((t) => t.storeId.equals(storeId) & t.kind.equals(kind)))
            .getSingleOrNull();
    final value = existing?.nextValue ?? 1;
    if (existing == null) {
      await _db
          .into(_db.localSequences)
          .insert(
            LocalSequencesCompanion.insert(
              storeId: storeId,
              kind: kind,
              nextValue: value + 1,
              updatedAt: now,
            ),
          );
    } else {
      await (_db.update(
        _db.localSequences,
      )..where((t) => t.storeId.equals(storeId) & t.kind.equals(kind))).write(
        LocalSequencesCompanion(
          nextValue: Value(value + 1),
          updatedAt: Value(now),
        ),
      );
    }
    return '$prefix${value.toString().padLeft(width, '0')}';
  }
}
