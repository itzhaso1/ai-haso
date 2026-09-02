import 'package:drift/drift.dart';
import 'package:uuid/uuid.dart';

import '../../local_db/app_database.dart';
import '../pos_errors.dart';
import 'stock_engine.dart';

class ReturnLineInput {
  const ReturnLineInput({
    required this.orderItemLocalId,
    required this.quantity,
  });

  final String orderItemLocalId;
  final int quantity;
}

class ReturnService {
  ReturnService(this._db, this._stock, {String Function()? newId})
    : _newId = newId ?? (() => const Uuid().v4());

  final AppDatabase _db;
  final StockEngine _stock;
  final String Function() _newId;

  Future<String> execute({
    required int workspaceId,
    required String orderLocalId,
    required List<ReturnLineInput> lines,
    required bool allowNegativeStock,
    String? reason,
    String? createdByUserId,
    String? shiftLocalId,
    String? deviceId,
  }) {
    if (lines.isEmpty) throw const InvalidReturnQuantity();
    return _db.transaction(() async {
      final order =
          await (_db.select(_db.localOrders)..where(
                (t) =>
                    t.localId.equals(orderLocalId) &
                    t.workspaceId.equals(workspaceId),
              ))
              .getSingleOrNull();
      if (order == null) {
        throw const DatabaseFailure('الطلب غير موجود.');
      }
      final invoice =
          await (_db.select(_db.localInvoices)..where(
                (t) =>
                    t.workspaceId.equals(workspaceId) &
                    t.orderLocalId.equals(orderLocalId),
              ))
              .getSingleOrNull();
      final items =
          await (_db.select(_db.localOrderItems)..where(
                (t) =>
                    t.orderLocalId.equals(orderLocalId) &
                    t.isRemoved.equals(false),
              ))
              .get();
      final byId = {for (final i in items) i.localId: i};

      final previous = await (_db.select(
        _db.localReturnItems,
      )..where((t) => t.workspaceId.equals(workspaceId))).get();
      final already = <String, int>{};
      for (final p in previous) {
        final oid = p.orderItemLocalId;
        if (oid == null) continue;
        already[oid] = (already[oid] ?? 0) + p.quantity;
      }

      var refundTotal = 0.0;
      final now = DateTime.now();
      final returnId = _newId();
      await _db
          .into(_db.localReturns)
          .insert(
            LocalReturnsCompanion.insert(
              localId: returnId,
              workspaceId: workspaceId,
              invoiceLocalId: Value(invoice?.localId),
              orderLocalId: Value(orderLocalId),
              reason: Value(reason),
              refundAmount: const Value(0),
              createdByUserId: Value(createdByUserId),
              shiftLocalId: Value(shiftLocalId),
              createdAt: now,
            ),
          );

      for (final line in lines) {
        final item = byId[line.orderItemLocalId];
        if (item == null) throw const InvalidReturnQuantity();
        final used = already[item.localId] ?? 0;
        if (line.quantity <= 0 || line.quantity > item.quantity - used) {
          throw const InvalidReturnQuantity();
        }
        final unit = item.quantity == 0
            ? 0.0
            : item.totalAmount / item.quantity;
        final amount = (unit * line.quantity * 100).round() / 100.0;
        refundTotal += amount;
        await _db
            .into(_db.localReturnItems)
            .insert(
              LocalReturnItemsCompanion.insert(
                localId: _newId(),
                returnLocalId: returnId,
                workspaceId: workspaceId,
                orderItemLocalId: Value(item.localId),
                productLocalId: Value(item.productLocalId),
                productNameSnapshot: item.name,
                quantity: line.quantity,
                refundAmount: amount,
              ),
            );
        if (item.productLocalId != null) {
          await _stock.apply(
            workspaceId: workspaceId,
            productLocalId: item.productLocalId!,
            type: 'return',
            quantity: line.quantity,
            allowNegative: true,
            referenceType: 'return',
            referenceId: returnId,
            userId: createdByUserId,
            deviceId: deviceId,
          );
        }
      }

      await (_db.update(_db.localReturns)
            ..where((t) => t.localId.equals(returnId)))
          .write(LocalReturnsCompanion(refundAmount: Value(refundTotal)));

      if (shiftLocalId != null && refundTotal > 0) {
        await _db
            .into(_db.localCashMovements)
            .insert(
              LocalCashMovementsCompanion.insert(
                localId: _newId(),
                workspaceId: workspaceId,
                shiftLocalId: shiftLocalId,
                type: 'refund',
                amount: refundTotal,
                reason: Value(reason ?? 'مرتجع'),
                referenceId: Value(returnId),
                createdByUserId: Value(createdByUserId),
                createdAt: now,
              ),
            );
      }
      return returnId;
    });
  }
}
