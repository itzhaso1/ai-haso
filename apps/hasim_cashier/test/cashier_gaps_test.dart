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
