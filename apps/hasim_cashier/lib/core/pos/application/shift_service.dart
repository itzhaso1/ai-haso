import 'package:drift/drift.dart';
import 'package:uuid/uuid.dart';

import '../../local_db/app_database.dart';
import '../pos_errors.dart';

class ShiftService {
  ShiftService(this._db, {String Function()? newId})
    : _newId = newId ?? (() => const Uuid().v4());

  final AppDatabase _db;
  final String Function() _newId;

  Future<LocalShift?> currentOpen(int workspaceId) {
    return (_db.select(_db.localShifts)
          ..where(
            (t) => t.workspaceId.equals(workspaceId) & t.status.equals('open'),
          )
          ..orderBy([(t) => OrderingTerm.desc(t.openedAt)])
          ..limit(1))
        .getSingleOrNull();
  }

  Future<String> open({
    required int workspaceId,
    required String userId,
    required double openingCash,
  }) {
    return _db.transaction(() async {
      final open = await currentOpen(workspaceId);
      if (open != null) return open.localId;
      final id = _newId();
      final now = DateTime.now();
      await _db
          .into(_db.localShifts)
          .insert(
            LocalShiftsCompanion.insert(
              localId: id,
              workspaceId: workspaceId,
              userId: userId,
              openedAt: now,
              openingCash: Value(openingCash),
            ),
          );
      await _db
          .into(_db.localCashMovements)
          .insert(
            LocalCashMovementsCompanion.insert(
              localId: _newId(),
              workspaceId: workspaceId,
              shiftLocalId: id,
              type: 'opening',
              amount: openingCash,
              reason: const Value('افتتاح الصندوق'),
              createdByUserId: Value(userId),
              createdAt: now,
            ),
          );
      return id;
    });
  }

  Future<Map<String, double>> expectedBreakdown(String shiftId) async {
    final rows = await (_db.select(
      _db.localCashMovements,
    )..where((t) => t.shiftLocalId.equals(shiftId))).get();
    var opening = 0.0;
    var sales = 0.0;
    var cashIn = 0.0;
    var refunds = 0.0;
    var cashOut = 0.0;
    var expenses = 0.0;
    for (final row in rows) {
      switch (row.type) {
        case 'opening':
          opening += row.amount;
        case 'sale':
          sales += row.amount;
        case 'cash_in':
          cashIn += row.amount;
        case 'refund':
          refunds += row.amount;
        case 'cash_out':
          cashOut += row.amount;
        case 'expense':
          expenses += row.amount;
      }
    }
    final expected = opening + sales + cashIn - refunds - cashOut - expenses;
    return {
      'opening': opening,
      'sales': sales,
      'cash_in': cashIn,
      'refunds': refunds,
      'cash_out': cashOut,
      'expenses': expenses,
      'expected': (expected * 100).round() / 100.0,
    };
  }

  Future<void> addMovement({
    required int workspaceId,
    required String shiftId,
    required String type,
    required double amount,
    String? reason,
    String? userId,
  }) async {
    final open = await currentOpen(workspaceId);
    if (open == null || open.localId != shiftId) throw const ShiftNotOpen();
    await _db
        .into(_db.localCashMovements)
        .insert(
          LocalCashMovementsCompanion.insert(
            localId: _newId(),
            workspaceId: workspaceId,
            shiftLocalId: shiftId,
            type: type,
            amount: amount,
            reason: Value(reason),
            createdByUserId: Value(userId),
            createdAt: DateTime.now(),
          ),
        );
  }

  Future<Map<String, dynamic>> close({
    required int workspaceId,
    required String shiftId,
    required double actualCash,
  }) {
    return _db.transaction(() async {
      final shift =
          await (_db.select(_db.localShifts)..where(
                (t) =>
                    t.localId.equals(shiftId) &
                    t.workspaceId.equals(workspaceId),
              ))
              .getSingleOrNull();
      if (shift == null || shift.status != 'open') throw const ShiftNotOpen();
      final breakdown = await expectedBreakdown(shiftId);
      final expected = breakdown['expected'] ?? 0;
      final diff = ((actualCash - expected) * 100).round() / 100.0;
      final now = DateTime.now();
      await _db
          .into(_db.localCashMovements)
          .insert(
            LocalCashMovementsCompanion.insert(
              localId: _newId(),
              workspaceId: workspaceId,
              shiftLocalId: shiftId,
              type: 'closing',
              amount: actualCash,
              reason: const Value('إغلاق الصندوق'),
              createdByUserId: Value(shift.userId),
              createdAt: now,
            ),
          );
      await (_db.update(
        _db.localShifts,
      )..where((t) => t.localId.equals(shiftId))).write(
        LocalShiftsCompanion(
          status: const Value('closed'),
          closedAt: Value(now),
          closingCash: Value(actualCash),
          expectedCash: Value(expected),
          actualCash: Value(actualCash),
          difference: Value(diff),
        ),
      );
      return {
        ...breakdown,
        'actual': actualCash,
        'difference': diff,
        'shift_id': shiftId,
      };
    });
  }
}
