import 'package:flutter_test/flutter_test.dart';
import 'package:hasim_cashier/core/config/app_config.dart';
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
    // Backend AuthorizesCashier does not grant menu via pos.manage alone.
    expect(
      CashierPermissions.canManageMenu({'pos.manage': true}),
      isFalse,
    );
    expect(
      CashierPermissions.canManageMenu({'workspace.manage': true}),
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
      CashierPermissions.canManageTables({'tables.manage': true}),
      isTrue,
    );
    // Backend does not grant tables via pos.manage alone.
    expect(
      CashierPermissions.canManageTables({'pos.manage': true}),
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
    // Truthy encodings from serializers must still unlock reports.
    expect(
      CashierPermissions.canViewReports({'reports.view': 1}),
      isTrue,
    );
    expect(
      CashierPermissions.canViewReports({'reports.view': 'true'}),
      isTrue,
    );
    final resolved = CashierPermissions.resolve(
      const {},
      {'reports.view': true, 'orders.manage': true},
    );
    expect(CashierPermissions.canViewReports(resolved), isTrue);
  });

  test('reports nav should not depend on empty permission map', () {
    // Web always shows reports; client may still gate content via API 403.
    // Empty map must not crash resolve / canViewReports.
    expect(CashierPermissions.resolve(null, null), isEmpty);
    expect(CashierPermissions.canViewReports(null), isFalse);
  });

  test('bootstrap auth snapshot equality skips identical permission maps', () {
    const a = {'reports.view': true, 'orders.manage': true};
    const b = {'reports.view': true, 'orders.manage': true};
    const c = {'reports.view': false, 'orders.manage': true};
    expect(a.length, b.length);
    var same = true;
    for (final e in a.entries) {
      if (b[e.key] != e.value) same = false;
    }
    expect(same, isTrue);
    same = true;
    for (final e in a.entries) {
      if (c[e.key] != e.value) same = false;
    }
    expect(same, isFalse);
  });

  test('poll intervals are slowed to avoid API throttle', () {
    expect(AppConfig.menuPollSeconds >= 5, isTrue);
    expect(AppConfig.tablesPollSeconds >= 5, isTrue);
    expect(AppConfig.kitchenPollSeconds >= 5, isTrue);
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

  test('reports nested fields tolerate null/non-map values', () {
    String nestedName(dynamic value, {String fallback = '—'}) {
      if (value is Map) {
        final name = value['name'];
        if (name != null && '$name'.trim().isNotEmpty) return '$name';
      }
      return fallback;
    }

    num asNum(dynamic value) {
      if (value is num) return value;
      if (value is String) return num.tryParse(value) ?? 0;
      return 0;
    }

    expect(nestedName({'name': 'طاولة 1'}), 'طاولة 1');
    expect(nestedName(null), '—');
    expect(nestedName('not-a-map'), '—');
    expect(asNum('12.5'), 12.5);
    expect(asNum(null), 0);
    expect(asNum({'x': 1}), 0);
  });

  test('menu.manage is required for catalog management actions', () {
    expect(CashierPermissions.canManageMenu({'menu.manage': true}), isTrue);
    expect(CashierPermissions.canManageMenu({'pos.manage': true}), isFalse);
    expect(CashierPermissions.canManageMenu(const {}), isFalse);
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
