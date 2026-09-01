import 'package:flutter_test/flutter_test.dart';
import 'package:hasim_cashier/core/offline/conflict_strategy.dart';
import 'package:hasim_cashier/core/offline/offline_store.dart';
import 'package:hasim_cashier/core/permissions/cashier_permissions.dart';
import 'package:hasim_cashier/core/printing/printer_service.dart';
import 'package:hasim_cashier/core/realtime/pos_event_source.dart';
import 'package:hasim_cashier/features/cart/cart_controller.dart';

void main() {
  test('cart takeaway clears table and labels order type خارجي', () {
    final cart = CartController();
    cart.setChannel(OrderChannel.table);
    cart.setTable(9);
    cart.setChannel(OrderChannel.takeaway);
    expect(cart.state.tableId, isNull);
    expect(cart.state.channel.labelAr, 'خارجي');
  });

  test('permissions gate tables and discount from Laravel map', () {
    const allowed = {
      'tables.manage': true,
      'orders.discount': false,
      'orders.create': true,
    };
    expect(CashierPermissions.canManageTables(allowed), isTrue);
    expect(CashierPermissions.canDiscount(allowed), isFalse);
    expect(CashierPermissions.canCreateOrders(allowed), isTrue);
  });

  test('menu.manage gates catalog and POS settings independently', () {
    expect(CashierPermissions.canManageMenu(const {}), isFalse);
    expect(
      CashierPermissions.canManageMenu({'menu.manage': true}),
      isTrue,
    );
    expect(
      CashierPermissions.canManageMenu({'orders.manage': true}),
      isFalse,
    );
    expect(
      CashierPermissions.canManageMenu({'pos.manage': true}),
      isTrue,
    );
  });

  test('orders.manage / tables.manage / reports.view are independent', () {
    expect(
      CashierPermissions.canCreateOrders({'orders.manage': true}),
      isTrue,
    );
    expect(
      CashierPermissions.canManageTables({'orders.manage': true}),
      isFalse,
    );
    expect(
      CashierPermissions.canViewReports({'reports.view': true}),
      isTrue,
    );
    expect(
      CashierPermissions.canViewReports({'tables.manage': true}),
      isFalse,
    );
  });

  test('reports permission uses session fallback when bootstrap empty', () {
    expect(CashierPermissions.canViewReports(const {}), isFalse);
    expect(
      CashierPermissions.canViewReports({'reports.view': true}),
      isTrue,
    );
    expect(
      CashierPermissions.canViewReports({'orders.manage': true}),
      isTrue,
    );
    final resolved = CashierPermissions.resolve(
      const {},
      {'reports.view': true, 'orders.manage': true},
    );
    expect(CashierPermissions.canViewReports(resolved), isTrue);
  });

  test('hourly sales prefer sales_total over total_sales', () {
    num hourSales(Map<String, dynamic> row) =>
        (row['sales_total'] as num?) ?? (row['total_sales'] as num?) ?? 0;
    expect(hourSales({'sales_total': 42, 'total_sales': 1}), 42);
    expect(hourSales({'total_sales': 7}), 7);
    expect(hourSales({}), 0);
  });

  test('table order mutation mirrors assertOrderMutable rules', () {
    bool canMutate(Map<String, dynamic> order) {
      final status = order['pos_status'] as String?;
      return status != 'cancelled' &&
          status != 'completed' &&
          order['payment_status'] != 'paid' &&
          order['pos_cashier_invoice_id'] == null;
    }

    expect(canMutate({'pos_status': 'new', 'payment_status': 'unpaid'}), isTrue);
    expect(canMutate({'pos_status': 'cancelled'}), isFalse);
    expect(canMutate({'pos_status': 'completed'}), isFalse);
    expect(canMutate({'pos_status': 'new', 'payment_status': 'paid'}), isFalse);
    expect(
      canMutate({'pos_status': 'new', 'pos_cashier_invoice_id': 9}),
      isFalse,
    );
  });

  test('table add-order payload is save-only (no invoice/print flags)', () {
    final payload = <String, dynamic>{
      'order_type': 'table',
      'dining_table_id': 1,
      'client_reference': 'ref-1',
      'notes': null,
      'items': [
        {'pos_menu_item_id': 10, 'quantity': 2},
      ],
    };
    expect(payload.containsKey('create_invoice'), isFalse);
    expect(payload.containsKey('print'), isFalse);
    expect(payload.containsKey('payment_method'), isFalse);
    expect(payload['order_type'], 'table');
  });

  test('conflict strategy keeps pending orders and requires online table ops', () {
    expect(
      ConflictStrategy.forDomain('pending_order'),
      ConflictPolicy.keepLocalPending,
    );
    expect(
      ConflictStrategy.forDomain('table_action'),
      ConflictPolicy.requireOnline,
    );
    expect(
      ConflictStrategy.forDomain('close_table'),
      ConflictPolicy.requireOnline,
    );
    expect(
      ConflictStrategy.forDomain('payment'),
      ConflictPolicy.requireOnline,
    );
    expect(
      ConflictStrategy.forDomain('inventory'),
      ConflictPolicy.serverWins,
    );
  });

  test('escpos builder emits invoice bytes without claiming print success', () {
    final bytes = EscPosReceiptBuilder().buildInvoice({
      'store_name': 'متجر تجريبي',
      'invoice_number': 'INV-1',
      'closed_at': '2026-09-01',
      'subtotal': 100,
      'discount_amount': 5,
      'total_amount': 95,
      'items': [
        {'item_name': 'شاي', 'quantity': 2, 'total_amount': 20},
      ],
    });
    expect(bytes, isNotEmpty);
  });

  test('unconfigured printer gateway never fakes success', () async {
    final gateway = UnconfiguredPrinterGateway();
    final result = await gateway.send(
      EscPosReceiptBuilder().buildTestPage('x'),
      const PrinterProfile(
        id: '1',
        name: 't',
        transport: PrinterTransport.network,
        address: '10.0.0.1',
      ),
    );
    expect(result.success, isFalse);
    expect(result.message, contains('غير'));
  });

  test('pusher source refuses start without credentials', () async {
    final source = PusherPosEventSource();
    expect(source.isConfigured, isFalse);
    expect(() => source.start(), throwsA(isA<StateError>()));
  });

  test('sync status enum covers queue states', () {
    expect(SyncStatus.values.map((e) => e.name), containsAll([
      'pending',
      'syncing',
      'synced',
      'failed',
    ]));
  });
}
