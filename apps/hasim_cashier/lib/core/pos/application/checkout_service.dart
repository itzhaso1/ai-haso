import 'dart:convert';

import 'package:drift/drift.dart';
import 'package:uuid/uuid.dart';

import '../../local_db/app_database.dart';
import '../../repositories/sync_queue_repository.dart';
import '../domain/pricing_service.dart';
import '../pos_errors.dart';
import 'document_numbers.dart';
import 'stock_engine.dart';

class PaymentTender {
  const PaymentTender({
    required this.method,
    required this.amount,
    this.tendered,
  });

  final String method;
  final double amount;
  final double? tendered;
}

class CheckoutCommand {
  const CheckoutCommand({
    required this.workspaceId,
    required this.deviceId,
    required this.storeId,
    required this.clientReference,
    required this.orderType,
    required this.lines,
    required this.payments,
    this.tableLocalId,
    this.tableServerId,
    this.sessionLocalId,
    this.customerLocalId,
    this.notes,
    this.orderDiscountAmount = 0,
    this.orderDiscountPercent = 0,
    this.taxRate = 0,
    this.createdByUserId,
    this.shiftLocalId,
    this.allowNegativeStock = false,
    this.connected = false,
    this.invoicePrefix = 'INV-',
  });

  final int workspaceId;
  final String deviceId;
  final String storeId;
  final String clientReference;
  final String orderType;
  final List<PricedLine> lines;
  final List<PaymentTender> payments;
  final String? tableLocalId;
  final int? tableServerId;
  final String? sessionLocalId;
  final String? customerLocalId;
  final String? notes;
  final double orderDiscountAmount;
  final double orderDiscountPercent;
  final double taxRate;
  final String? createdByUserId;
  final String? shiftLocalId;
  final bool allowNegativeStock;
  final bool connected;
  final String invoicePrefix;
}

class CheckoutResult {
  const CheckoutResult({
    required this.orderLocalId,
    required this.invoiceLocalId,
    required this.invoiceNumber,
    required this.total,
    required this.changeDue,
  });

  final String orderLocalId;
  final String invoiceLocalId;
  final String invoiceNumber;
  final double total;
  final double changeDue;
}

/// Completes a sale in one SQLite transaction. Never calls the network.
class CheckoutService {
  CheckoutService(
    this._db,
    this._stock,
    this._numbers,
    this._queue, {
    this.pricing = const PricingService(),
    String Function()? newId,
  }) : _newId = newId ?? (() => const Uuid().v4());

  final AppDatabase _db;
  final StockEngine _stock;
  final DocumentNumberService _numbers;
  final SyncQueueRepository _queue;
  final PricingService pricing;
  final String Function() _newId;

  Future<CheckoutResult> execute(CheckoutCommand cmd) {
    if (cmd.lines.isEmpty) {
      throw const EmptyCart();
    }
    return _db.transaction(() async {
      final existing =
          await (_db.select(_db.localOrders)..where(
                (t) =>
                    t.workspaceId.equals(cmd.workspaceId) &
                    t.clientReference.equals(cmd.clientReference),
              ))
              .getSingleOrNull();
      if (existing != null && existing.paymentStatus == 'paid') {
        final invoice =
            await (_db.select(_db.localInvoices)..where(
                  (t) =>
                      t.workspaceId.equals(cmd.workspaceId) &
                      t.orderLocalId.equals(existing.localId),
                ))
                .getSingleOrNull();
        return CheckoutResult(
          orderLocalId: existing.localId,
          invoiceLocalId: invoice?.localId ?? existing.localId,
          invoiceNumber:
              invoice?.localInvoiceNumber ??
              invoice?.invoiceNumber ??
              existing.orderNumber ??
              existing.localId,
          total: existing.totalAmount,
          changeDue: 0,
        );
      }

      final quote = pricing.quote(
        lines: cmd.lines,
        orderDiscountAmount: cmd.orderDiscountAmount,
        orderDiscountPercent: cmd.orderDiscountPercent,
        fallbackTaxRate: cmd.taxRate,
      );

      var paid = 0.0;
      var changeDue = 0.0;
      var hasCredit = false;
      for (final p in cmd.payments) {
        if (p.amount < 0) throw const PaymentMismatch();
        if (p.method == 'credit') hasCredit = true;
        paid = Money.round(paid + p.amount);
        if (p.method == 'cash' && p.tendered != null) {
          if (p.tendered! + 0.0001 < p.amount) throw const PaymentMismatch();
          changeDue = Money.round(changeDue + (p.tendered! - p.amount));
        }
      }
      if (!hasCredit && paid + 0.009 < quote.total) {
        throw const PaymentMismatch();
      }

      final now = DateTime.now();
      final orderId = cmd.clientReference;
      final invoiceId = _newId();
      final orderNumber = await _numbers.nextOrderNumber(storeId: cmd.storeId);
      final invoiceNumber = await _numbers.nextInvoiceNumber(
        storeId: cmd.storeId,
        prefix: cmd.invoicePrefix,
      );

      await _db
          .into(_db.localOrders)
          .insert(
            LocalOrdersCompanion.insert(
              localId: orderId,
              workspaceId: cmd.workspaceId,
              deviceId: cmd.deviceId,
              clientReference: cmd.clientReference,
              orderNumber: Value(orderNumber),
              orderType: cmd.orderType,
              tableServerId: Value(cmd.tableServerId),
              tableLocalId: Value(cmd.tableLocalId),
              sessionLocalId: Value(cmd.sessionLocalId),
              customerLocalId: Value(cmd.customerLocalId),
              createdByUserId: Value(cmd.createdByUserId),
              notes: Value(cmd.notes),
              subtotal: Value(quote.subtotal),
              taxAmount: Value(quote.taxAmount),
              discountAmount: Value(
                Money.round(quote.itemDiscountTotal + quote.orderDiscount),
              ),
              discountPercent: Value(cmd.orderDiscountPercent),
              totalAmount: Value(quote.total),
              posStatus: const Value('new'),
              paymentStatus: const Value('paid'),
              fulfillmentStatus: const Value('unfulfilled'),
              syncStatus: Value(cmd.connected ? 'pending' : 'local'),
              createdAt: now,
              updatedAt: now,
              completedAt: Value(now),
            ),
          );

      for (final line in quote.lineResults) {
        await _db
            .into(_db.localOrderItems)
            .insert(
              LocalOrderItemsCompanion.insert(
                localId: _newId(),
                workspaceId: cmd.workspaceId,
                orderLocalId: orderId,
                productServerId: Value(line.line.productServerId),
                productLocalId: Value(line.line.productLocalId),
                name: line.line.name,
                skuSnapshot: Value(line.line.sku),
                barcodeSnapshot: Value(line.line.barcode),
                quantity: line.line.quantity,
                unitPrice: line.line.unitPrice,
                costSnapshot: Value(line.line.cost),
                discountAmount: Value(line.discountAmount),
                taxRate: Value(
                  line.line.taxRate > 0 ? line.line.taxRate : cmd.taxRate,
                ),
                taxAmount: Value(line.taxAmount),
                totalAmount: line.total,
                createdAt: Value(now),
                updatedAt: now,
              ),
            );
        await _stock.apply(
          workspaceId: cmd.workspaceId,
          productLocalId: line.line.productLocalId,
          type: 'sale',
          quantity: line.line.quantity,
          allowNegative: cmd.allowNegativeStock,
          referenceType: 'order',
          referenceId: orderId,
          userId: cmd.createdByUserId,
          deviceId: cmd.deviceId,
        );
      }

      final invoicePayload = {
        'local_id': invoiceId,
        'invoice_number': invoiceNumber,
        'order_local_id': orderId,
        'subtotal': quote.subtotal,
        'discount_amount': Money.round(
          quote.itemDiscountTotal + quote.orderDiscount,
        ),
        'tax_amount': quote.taxAmount,
        'total_amount': quote.total,
        'payment_method': cmd.payments.map((p) => p.method).join('+'),
        'closed_at': now.toUtc().toIso8601String(),
        'items': [
          for (final line in quote.lineResults)
            {
              'item_name': line.line.name,
              'quantity': line.line.quantity,
              'unit_price': line.line.unitPrice,
              'tax_amount': line.taxAmount,
              'total_amount': line.total,
            },
        ],
      };

      await _db
          .into(_db.localInvoices)
          .insert(
            LocalInvoicesCompanion.insert(
              localId: invoiceId,
              workspaceId: cmd.workspaceId,
              deviceId: cmd.deviceId,
              invoiceNumber: Value(invoiceNumber),
              localInvoiceNumber: Value(invoiceNumber),
              orderLocalId: Value(orderId),
              status: const Value('closed'),
              subtotal: Value(quote.subtotal),
              discountAmount: Value(
                Money.round(quote.itemDiscountTotal + quote.orderDiscount),
              ),
              taxAmount: Value(quote.taxAmount),
              totalAmount: Value(quote.total),
              createdByUserId: Value(cmd.createdByUserId),
              syncStatus: Value(cmd.connected ? 'pending' : 'local'),
              payloadJson: Value(jsonEncode(invoicePayload)),
              createdAt: now,
            ),
          );

      for (final p in cmd.payments) {
        await _db
            .into(_db.localPayments)
            .insert(
              LocalPaymentsCompanion.insert(
                localId: _newId(),
                workspaceId: cmd.workspaceId,
                deviceId: cmd.deviceId,
                orderLocalId: Value(orderId),
                invoiceLocalId: Value(invoiceId),
                method: p.method,
                amount: p.amount,
                tendered: Value(p.tendered),
                changeDue: Value(
                  p.method == 'cash' && p.tendered != null
                      ? Money.round(p.tendered! - p.amount)
                      : 0,
                ),
                shiftLocalId: Value(cmd.shiftLocalId),
                syncStatus: Value(cmd.connected ? 'pending' : 'local'),
                clientReference: '${cmd.clientReference}:${p.method}',
                createdAt: now,
              ),
            );
        if (p.method == 'cash' && cmd.shiftLocalId != null) {
          await _db
              .into(_db.localCashMovements)
              .insert(
                LocalCashMovementsCompanion.insert(
                  localId: _newId(),
                  workspaceId: cmd.workspaceId,
                  shiftLocalId: cmd.shiftLocalId!,
                  type: 'sale',
                  amount: p.amount,
                  reason: const Value('بيع نقدي'),
                  referenceId: Value(invoiceId),
                  createdByUserId: Value(cmd.createdByUserId),
                  createdAt: now,
                ),
              );
        }
      }

      if (cmd.connected) {
        await _queue.enqueue(
          workspaceId: cmd.workspaceId,
          deviceId: cmd.deviceId,
          entityType: 'order',
          entityId: orderId,
          operation: 'create',
          payload: {
            'client_reference': cmd.clientReference,
            'order_type': cmd.orderType,
            if (cmd.tableServerId != null) 'dining_table_id': cmd.tableServerId,
            'items': [
              for (final line in cmd.lines)
                {
                  'pos_menu_item_id': line.productServerId,
                  'quantity': line.quantity,
                },
            ],
          },
          clientReference: cmd.clientReference,
        );
      }

      return CheckoutResult(
        orderLocalId: orderId,
        invoiceLocalId: invoiceId,
        invoiceNumber: invoiceNumber,
        total: quote.total,
        changeDue: changeDue,
      );
    });
  }
}
