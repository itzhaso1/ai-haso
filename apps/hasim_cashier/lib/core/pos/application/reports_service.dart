import 'package:drift/drift.dart';

import '../../local_db/app_database.dart';
import '../domain/pricing_service.dart';

class LocalReportsService {
  LocalReportsService(this._db);

  final AppDatabase _db;

  (DateTime, DateTime) _dayRange(DateTime date) {
    final start = DateTime(date.year, date.month, date.day);
    return (start, start.add(const Duration(days: 1)));
  }

  Future<Map<String, dynamic>> daily({
    required int workspaceId,
    required DateTime date,
  }) async {
    final (from, to) = _dayRange(date);
    final invoices =
        await (_db.select(_db.localInvoices)..where(
              (t) =>
                  t.workspaceId.equals(workspaceId) &
                  t.createdAt.isBiggerOrEqualValue(from) &
                  t.createdAt.isSmallerThanValue(to),
            ))
            .get();
    final orders =
        await (_db.select(_db.localOrders)..where(
              (t) =>
                  t.workspaceId.equals(workspaceId) &
                  t.posStatus.isNotValue('cancelled') &
                  t.createdAt.isBiggerOrEqualValue(from) &
                  t.createdAt.isSmallerThanValue(to),
            ))
            .get();
    final payments =
        await (_db.select(_db.localPayments)..where(
              (t) =>
                  t.workspaceId.equals(workspaceId) &
                  t.createdAt.isBiggerOrEqualValue(from) &
                  t.createdAt.isSmallerThanValue(to),
            ))
            .get();
    final returns =
        await (_db.select(_db.localReturns)..where(
              (t) =>
                  t.workspaceId.equals(workspaceId) &
                  t.createdAt.isBiggerOrEqualValue(from) &
                  t.createdAt.isSmallerThanValue(to),
            ))
            .get();

    var subtotalCents = 0;
    var discountCents = 0;
    var taxCents = 0;
    var grossCents = 0;
    for (final inv in invoices) {
      subtotalCents += inv.subtotal;
      discountCents += inv.discountAmount;
      taxCents += inv.taxAmount;
      grossCents += inv.totalAmount;
    }
    if (grossCents <= 0) {
      for (final o in orders.where((o) => o.paymentStatus == 'paid')) {
        subtotalCents += o.subtotal;
        discountCents += o.discountAmount;
        taxCents += o.taxAmount;
        grossCents += o.totalAmount;
      }
    }

    final byMethod = <String, int>{};
    final byMethodCount = <String, int>{};
    for (final p in payments) {
      byMethod[p.method] = (byMethod[p.method] ?? 0) + p.amount;
      byMethodCount[p.method] = (byMethodCount[p.method] ?? 0) + 1;
    }

    // Join instead of `IN (thousands of ids)` — SQLite variable limits
    // would fail around 10k paid orders on some devices.
    final items = await (_db.select(_db.localOrderItems).join([
          innerJoin(
            _db.localOrders,
            _db.localOrders.localId.equalsExp(
              _db.localOrderItems.orderLocalId,
            ),
          ),
        ])
          ..where(
            _db.localOrderItems.workspaceId.equals(workspaceId) &
                _db.localOrderItems.isRemoved.equals(false) &
                _db.localOrders.workspaceId.equals(workspaceId) &
                _db.localOrders.posStatus.isNotValue('cancelled') &
                _db.localOrders.paymentStatus.equals('paid') &
                _db.localOrders.createdAt.isBiggerOrEqualValue(from) &
                _db.localOrders.createdAt.isSmallerThanValue(to),
          ))
        .map((row) => row.readTable(_db.localOrderItems))
        .get();
    final itemQty = <String, int>{};
    final itemRev = <String, int>{};
    var cogsCents = 0;
    for (final i in items) {
      itemQty[i.name] = (itemQty[i.name] ?? 0) + i.quantity;
      itemRev[i.name] = (itemRev[i.name] ?? 0) + i.totalAmount;
      cogsCents += i.costSnapshot * i.quantity;
    }
    final top = itemQty.entries.toList()
      ..sort((a, b) => b.value.compareTo(a.value));

    var returnCents = 0;
    for (final r in returns) {
      returnCents += r.refundAmount;
    }
    final netCents = grossCents - returnCents;

    return {
      'date':
          '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}',
      'source': 'local_sqlite',
      'summary': {
        'invoice_sales_total': Money.fromCents(grossCents),
        'invoices_total': Money.fromCents(grossCents),
        'invoices_count': invoices.length,
        'orders_count': orders.length,
        'subtotal': Money.fromCents(subtotalCents),
        'discount_total': Money.fromCents(discountCents),
        'tax_total': Money.fromCents(taxCents),
        'grand_total': Money.fromCents(netCents),
        'gross_sales': Money.fromCents(grossCents),
        'net_sales': Money.fromCents(netCents),
        'gross_profit': Money.fromCents(netCents - cogsCents),
        'return_count': returns.length,
        'return_amount': Money.fromCents(returnCents),
      },
      'payment_methods': [
        for (final e in byMethod.entries)
          {
            'method': e.key,
            'total': Money.fromCents(e.value),
            'count': byMethodCount[e.key] ?? 0,
          },
      ],
      'top_items': [
        for (final e in top.take(20))
          {
            'product_name': e.key,
            'quantity': e.value,
            'sales': Money.fromCents(itemRev[e.key] ?? 0),
          },
      ],
      'invoices': [
        for (final inv in invoices)
          {
            'id': inv.localId,
            'local_id': inv.localId,
            'invoice_number': inv.localInvoiceNumber ?? inv.invoiceNumber,
            'total_amount': Money.fromCents(inv.totalAmount),
            'tax_amount': Money.fromCents(inv.taxAmount),
            'discount_amount': Money.fromCents(inv.discountAmount),
            'created_at': inv.createdAt.toIso8601String(),
          },
      ],
    };
  }

  Future<Map<String, dynamic>> stockSnapshot(int workspaceId) async {
    final products =
        await (_db.select(_db.localProducts)..where(
              (t) =>
                  t.workspaceId.equals(workspaceId) & t.isDeleted.equals(false),
            ))
            .get();
    return {
      'products': [
        for (final p in products)
          {
            'local_id': p.localId,
            'name': p.name,
            'stock': p.stock,
            'track_stock': p.trackStock,
          },
      ],
    };
  }
}
