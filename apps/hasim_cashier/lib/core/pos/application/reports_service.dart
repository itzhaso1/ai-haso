import 'package:drift/drift.dart';

import '../../local_db/app_database.dart';

class LocalReportsService {
  LocalReportsService(this._db);

  final AppDatabase _db;

  bool _sameDay(DateTime a, DateTime b) {
    final la = a.toLocal();
    final lb = b.toLocal();
    return la.year == lb.year && la.month == lb.month && la.day == lb.day;
  }

  Future<Map<String, dynamic>> daily({
    required int workspaceId,
    required DateTime date,
  }) async {
    final invoices = await (_db.select(
      _db.localInvoices,
    )..where((t) => t.workspaceId.equals(workspaceId))).get();
    final dayInvoices = [
      for (final i in invoices)
        if (_sameDay(i.createdAt, date)) i,
    ];
    final orders = await (_db.select(
      _db.localOrders,
    )..where((t) => t.workspaceId.equals(workspaceId))).get();
    final dayOrders = [
      for (final o in orders)
        if (_sameDay(o.createdAt, date) && o.posStatus != 'cancelled') o,
    ];
    final payments = await (_db.select(
      _db.localPayments,
    )..where((t) => t.workspaceId.equals(workspaceId))).get();
    final dayPayments = [
      for (final p in payments)
        if (_sameDay(p.createdAt, date)) p,
    ];
    final returns = await (_db.select(
      _db.localReturns,
    )..where((t) => t.workspaceId.equals(workspaceId))).get();
    final dayReturns = [
      for (final r in returns)
        if (_sameDay(r.createdAt, date)) r,
    ];

    var subtotal = 0.0;
    var discount = 0.0;
    var tax = 0.0;
    var grand = 0.0;
    for (final inv in dayInvoices) {
      subtotal += inv.subtotal;
      discount += inv.discountAmount;
      tax += inv.taxAmount;
      grand += inv.totalAmount;
    }
    if (grand <= 0) {
      for (final o in dayOrders.where((o) => o.paymentStatus == 'paid')) {
        subtotal += o.subtotal;
        discount += o.discountAmount;
        tax += o.taxAmount;
        grand += o.totalAmount;
      }
    }

    final byMethod = <String, double>{};
    final byMethodCount = <String, int>{};
    for (final p in dayPayments) {
      byMethod[p.method] = (byMethod[p.method] ?? 0) + p.amount;
      byMethodCount[p.method] = (byMethodCount[p.method] ?? 0) + 1;
    }

    final itemQty = <String, int>{};
    final itemRev = <String, double>{};
    var cogs = 0.0;
    for (final o in dayOrders.where((o) => o.paymentStatus == 'paid')) {
      final items =
          await (_db.select(_db.localOrderItems)..where(
                (t) =>
                    t.orderLocalId.equals(o.localId) &
                    t.isRemoved.equals(false),
              ))
              .get();
      for (final i in items) {
        itemQty[i.name] = (itemQty[i.name] ?? 0) + i.quantity;
        itemRev[i.name] = (itemRev[i.name] ?? 0) + i.totalAmount;
        cogs += i.costSnapshot * i.quantity;
      }
    }
    final top = itemQty.entries.toList()
      ..sort((a, b) => b.value.compareTo(a.value));

    final returnAmount = dayReturns.fold<double>(
      0,
      (s, r) => s + r.refundAmount,
    );

    return {
      'date':
          '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}',
      'source': 'local_sqlite',
      'summary': {
        'invoice_sales_total': grand,
        'invoices_total': grand,
        'invoices_count': dayInvoices.length,
        'orders_count': dayOrders.length,
        'subtotal': subtotal,
        'discount_total': discount,
        'tax_total': tax,
        'grand_total': grand,
        'gross_profit': grand - cogs - returnAmount,
        'return_count': dayReturns.length,
        'return_amount': returnAmount,
      },
      'payment_methods': [
        for (final e in byMethod.entries)
          {
            'method': e.key,
            'total': e.value,
            'count': byMethodCount[e.key] ?? 0,
          },
      ],
      'top_items': [
        for (final e in top.take(20))
          {
            'product_name': e.key,
            'quantity': e.value,
            'sales': itemRev[e.key] ?? 0,
          },
      ],
      'invoices': [
        for (final inv in dayInvoices)
          {
            'id': inv.localId,
            'local_id': inv.localId,
            'invoice_number': inv.localInvoiceNumber ?? inv.invoiceNumber,
            'total_amount': inv.totalAmount,
            'tax_amount': inv.taxAmount,
            'discount_amount': inv.discountAmount,
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
