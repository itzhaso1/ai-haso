import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:hasim_cashier/core/local_db/app_database.dart';
import 'package:hasim_cashier/core/local_db/workspace_scope.dart';
import 'package:hasim_cashier/core/pos/application/catalog_admin_service.dart';
import 'package:hasim_cashier/core/pos/application/checkout_service.dart';
import 'package:hasim_cashier/core/pos/application/document_numbers.dart';
import 'package:hasim_cashier/core/pos/application/draft_cart_store.dart';
import 'package:hasim_cashier/core/pos/application/hive_legacy_migration.dart';
import 'package:hasim_cashier/core/pos/application/local_auth_service.dart';
import 'package:hasim_cashier/core/pos/application/reports_service.dart';
import 'package:hasim_cashier/core/pos/application/return_service.dart';
import 'package:hasim_cashier/core/pos/application/shift_service.dart';
import 'package:hasim_cashier/core/pos/application/stock_engine.dart';
import 'package:hasim_cashier/core/pos/domain/pricing_service.dart';
import 'package:hasim_cashier/core/pos/pos_errors.dart';
import 'package:hasim_cashier/core/pos/pos_mode.dart';
import 'package:hasim_cashier/core/printing/printer_service.dart';
import 'package:hasim_cashier/core/repositories/orders_repository.dart';
import 'package:hasim_cashier/core/repositories/sync_queue_repository.dart';
import 'package:hasim_cashier/features/cart/cart_controller.dart';

const adminPerms = LocalAuthService.adminPermissions;

void main() {
  late AppDatabase db;
  late CatalogAdminService catalog;
  late CheckoutService checkout;
  late StockEngine stock;
  late ShiftService shifts;
  late ReturnService returns;
  late LocalAuthService auth;

  setUp(() {
    db = AppDatabase.memory();
    catalog = CatalogAdminService(db);
    stock = StockEngine(db);
    checkout = CheckoutService(
      db,
      stock,
      DocumentNumberService(db),
      SyncQueueRepository(db),
    );
    shifts = ShiftService(db);
    returns = ReturnService(db, stock);
    auth = LocalAuthService(db);
  });

  tearDown(() async {
    await db.close();
  });

  Future<({String storeId, String productId, String shiftId})> seed() async {
    final created = await auth.bootstrapStore(
      storeName: 'متجر',
      adminName: 'مدير',
      username: 'admin',
      pin: '1234',
    );
    final productId = await catalog.createProduct(
      workspaceId: PosMode.standaloneWorkspaceId,
      name: 'شاي',
      price: 10.03,
      cost: 4,
      stock: 10,
      trackStock: true,
      permissions: adminPerms,
    );
    final shiftId = await shifts.open(
      workspaceId: PosMode.standaloneWorkspaceId,
      userId: created.user.localId,
      openingCash: 100,
      permissions: adminPerms,
    );
    return (
      storeId: created.store.localId,
      productId: productId,
      shiftId: shiftId,
    );
  }

  test('duplicate return with same client reference is idempotent', () async {
    final s = await seed();
    await checkout.execute(
      CheckoutCommand(
        workspaceId: PosMode.standaloneWorkspaceId,
        deviceId: 'dev-1',
        storeId: s.storeId,
        permissions: adminPerms,
        clientReference: 'sale-ret-dup',
        orderType: 'takeaway',
        lines: [
          PricedLine(
            productLocalId: s.productId,
            name: 'شاي',
            quantity: 2,
            unitPrice: 10,
          ),
        ],
        payments: const [
          PaymentTender(method: 'cash', amount: 20, tendered: 20),
        ],
        shiftLocalId: s.shiftId,
      ),
    );
    final item = (await (db.select(db.localOrderItems)
          ..where((t) => t.orderLocalId.equals('sale-ret-dup')))
        .get())
        .single;
    Future<String> refund() => returns.execute(
      workspaceId: PosMode.standaloneWorkspaceId,
      orderLocalId: 'sale-ret-dup',
      lines: [ReturnLineInput(orderItemLocalId: item.localId, quantity: 1)],
      allowNegativeStock: false,
      shiftLocalId: s.shiftId,
      clientReference: 'ret-same',
      permissions: adminPerms,
    );
    final first = await refund();
    final second = await refund();
    expect(second, first);
    expect(await db.select(db.localReturns).get(), hasLength(1));
    final product = await (db.select(db.localProducts)
          ..where((t) => t.localId.equals(s.productId)))
        .getSingle();
    expect(product.stock, 9);
  });

  test('return without open shift is rejected and does not restock', () async {
    final s = await seed();
    await checkout.execute(
      CheckoutCommand(
        workspaceId: PosMode.standaloneWorkspaceId,
        deviceId: 'dev-1',
        storeId: s.storeId,
        permissions: adminPerms,
        clientReference: 'sale-noshift',
        orderType: 'takeaway',
        lines: [
          PricedLine(
            productLocalId: s.productId,
            name: 'شاي',
            quantity: 1,
            unitPrice: 10,
          ),
        ],
        payments: const [
          PaymentTender(method: 'cash', amount: 10, tendered: 10),
        ],
        shiftLocalId: s.shiftId,
      ),
    );
    final item = (await db.select(db.localOrderItems).get()).last;
    expect(
      () => returns.execute(
        workspaceId: PosMode.standaloneWorkspaceId,
        orderLocalId: 'sale-noshift',
        lines: [ReturnLineInput(orderItemLocalId: item.localId, quantity: 1)],
        allowNegativeStock: false,
        permissions: adminPerms,
      ),
      throwsA(isA<ShiftNotOpen>()),
    );
    final product = await (db.select(db.localProducts)
          ..where((t) => t.localId.equals(s.productId)))
        .getSingle();
    expect(product.stock, 9);
  });

  test('partial return remainder cents sum to original line total', () async {
    final s = await seed();
    await checkout.execute(
      CheckoutCommand(
        workspaceId: PosMode.standaloneWorkspaceId,
        deviceId: 'dev-1',
        storeId: s.storeId,
        permissions: adminPerms,
        clientReference: 'sale-odd',
        orderType: 'takeaway',
        lines: [
          PricedLine(
            productLocalId: s.productId,
            name: 'شاي',
            quantity: 3,
            unitPrice: 10.01,
          ),
        ],
        payments: const [
          PaymentTender(method: 'cash', amount: 30.03, tendered: 30.03),
        ],
        shiftLocalId: s.shiftId,
      ),
    );
    final item = (await (db.select(db.localOrderItems)
          ..where((t) => t.orderLocalId.equals('sale-odd')))
        .get())
        .single;
    expect(item.totalAmount, 3003);
    await returns.execute(
      workspaceId: PosMode.standaloneWorkspaceId,
      orderLocalId: 'sale-odd',
      lines: [ReturnLineInput(orderItemLocalId: item.localId, quantity: 1)],
      allowNegativeStock: false,
      shiftLocalId: s.shiftId,
      clientReference: 'ret-1',
      permissions: adminPerms,
    );
    await returns.execute(
      workspaceId: PosMode.standaloneWorkspaceId,
      orderLocalId: 'sale-odd',
      lines: [ReturnLineInput(orderItemLocalId: item.localId, quantity: 2)],
      allowNegativeStock: false,
      shiftLocalId: s.shiftId,
      clientReference: 'ret-2',
      permissions: adminPerms,
    );
    final refunds = await db.select(db.localReturns).get();
    final sum = refunds.fold<int>(0, (a, r) => a + r.refundAmount);
    expect(sum, 3003);
  });

  test('two cash tenders persist as distinct payment rows', () async {
    final s = await seed();
    await checkout.execute(
      CheckoutCommand(
        workspaceId: PosMode.standaloneWorkspaceId,
        deviceId: 'dev-1',
        storeId: s.storeId,
        permissions: adminPerms,
        clientReference: 'sale-cash2',
        orderType: 'takeaway',
        lines: [
          PricedLine(
            productLocalId: s.productId,
            name: 'شاي',
            quantity: 1,
            unitPrice: 10,
          ),
        ],
        payments: const [
          PaymentTender(method: 'cash', amount: 4, tendered: 4),
          PaymentTender(method: 'cash', amount: 6, tendered: 6),
        ],
        shiftLocalId: s.shiftId,
      ),
    );
    final pays = await db.select(db.localPayments).get();
    expect(pays, hasLength(2));
    expect(pays.map((p) => p.amount), containsAll([400, 600]));
    expect(pays.map((p) => p.clientReference).toSet(), hasLength(2));
  });

  test('checkout without a real store is rejected', () async {
    final s = await seed();
    expect(
      () => checkout.execute(
        CheckoutCommand(
          workspaceId: PosMode.standaloneWorkspaceId,
          deviceId: 'dev-1',
          storeId: 'local-store',
          permissions: adminPerms,
          clientReference: 'ghost-store',
          orderType: 'takeaway',
          lines: [
            PricedLine(
              productLocalId: s.productId,
              name: 'شاي',
              quantity: 1,
              unitPrice: 10,
            ),
          ],
          payments: const [
            PaymentTender(method: 'cash', amount: 10, tendered: 10),
          ],
          shiftLocalId: s.shiftId,
        ),
      ),
      throwsA(isA<StoreNotFound>()),
    );
  });

  test('catalog stock edit writes an adjustment movement', () async {
    final s = await seed();
    await catalog.updateProduct(
      workspaceId: PosMode.standaloneWorkspaceId,
      localId: s.productId,
      stock: 4,
      permissions: adminPerms,
    );
    final product = await (db.select(db.localProducts)
          ..where((t) => t.localId.equals(s.productId)))
        .getSingle();
    expect(product.stock, 4);
    final adj = await (db.select(db.localStockMovements)
          ..where((t) => t.kind.equals('adjustment')))
        .get();
    expect(adj, isNotEmpty);
    expect(adj.last.afterQuantity, 4);
  });

  test('COGS subtracts returned cost', () async {
    final created = await auth.bootstrapStore(
      storeName: 'تقارير',
      adminName: 'مدير',
      username: 'admin2',
      pin: '1234',
    );
    final productId = await catalog.createProduct(
      workspaceId: PosMode.standaloneWorkspaceId,
      name: 'برجر',
      price: 10,
      cost: 4,
      stock: 10,
      trackStock: true,
      permissions: adminPerms,
    );
    final shiftId = await shifts.open(
      workspaceId: PosMode.standaloneWorkspaceId,
      userId: created.user.localId,
      openingCash: 50,
      permissions: adminPerms,
    );
    await checkout.execute(
      CheckoutCommand(
        workspaceId: PosMode.standaloneWorkspaceId,
        deviceId: 'dev-1',
        storeId: created.store.localId,
        permissions: adminPerms,
        clientReference: 'cogs-1',
        orderType: 'takeaway',
        lines: [
          PricedLine(
            productLocalId: productId,
            name: 'برجر',
            quantity: 2,
            unitPrice: 10,
            cost: 4,
          ),
        ],
        payments: const [
          PaymentTender(method: 'cash', amount: 20, tendered: 20),
        ],
        shiftLocalId: shiftId,
      ),
    );
    final item = (await db.select(db.localOrderItems).get()).single;
    await returns.execute(
      workspaceId: PosMode.standaloneWorkspaceId,
      orderLocalId: 'cogs-1',
      lines: [ReturnLineInput(orderItemLocalId: item.localId, quantity: 1)],
      allowNegativeStock: false,
      shiftLocalId: shiftId,
      clientReference: 'cogs-ret',
      permissions: adminPerms,
    );
    final report = await LocalReportsService(db).daily(
      workspaceId: PosMode.standaloneWorkspaceId,
      date: DateTime.now(),
    );
    expect(report['summary']['gross_profit'], 6);
    expect(report['summary']['net_sales'], 10);
    final top = (report['top_items'] as List).cast<Map>();
    expect(top, hasLength(1));
    expect(top.single['quantity'], 1);
    expect(top.single['sales'], 10);
  });

  test('channel switch restores the other channel draft', () async {
    final created = await auth.bootstrapStore(
      storeName: 'مسودة',
      adminName: 'مدير',
      username: 'admin3',
      pin: '1234',
    );
    final productId = await catalog.createProduct(
      workspaceId: PosMode.standaloneWorkspaceId,
      name: 'شاي',
      price: 5,
      stock: 5,
      permissions: adminPerms,
    );
    final cart = CartController(
      store: DraftCartStore(db),
      workspaceId: PosMode.standaloneWorkspaceId,
    );
    await cart.idle;
    cart.addItem(
      productLocalId: productId,
      name: 'شاي',
      unitPrice: 5,
    );
    await cart.idle;
    cart.setChannel(OrderChannel.delivery);
    await cart.idle;
    expect(cart.state.lines, isEmpty);
    expect(cart.state.channel, OrderChannel.delivery);
    cart.setChannel(OrderChannel.takeaway);
    await cart.idle;
    expect(cart.state.lines, hasLength(1));
    expect(cart.state.lines.single.productLocalId, productId);
    expect(created.store.localId, isNotEmpty);
  });

  test('standalone hive migration is a no-op', () async {
    final migration = HiveLegacyMigration(
      db,
      OrdersRepository(db, SyncQueueRepository(db)),
    );
    await migration.runIfNeeded(
      workspaceId: PosMode.standaloneWorkspaceId,
      deviceId: 'dev-1',
    );
    expect(
      await db.readMeta(PosMode.standaloneWorkspaceId, HiveLegacyMigration.metaKey),
      isNull,
    );
  });

  test('printer failure after checkout does not change the paid invoice', () async {
    final s = await seed();
    final result = await checkout.execute(
      CheckoutCommand(
        workspaceId: PosMode.standaloneWorkspaceId,
        deviceId: 'dev-1',
        storeId: s.storeId,
        permissions: adminPerms,
        clientReference: 'print-1',
        orderType: 'takeaway',
        lines: [
          PricedLine(
            productLocalId: s.productId,
            name: 'شاي',
            quantity: 1,
            unitPrice: 10,
          ),
        ],
        payments: const [
          PaymentTender(method: 'cash', amount: 10, tendered: 10),
        ],
        shiftLocalId: s.shiftId,
      ),
    );
    expect(result.invoiceNumber, isNotEmpty);
    expect(result.total, 10);
    final printResult = await UnconfiguredPrinterGateway().send(
      Uint8List(0),
      const PrinterProfile(
        id: '1',
        name: 't',
        transport: PrinterTransport.network,
        address: '10.255.255.1',
      ),
    );
    expect(printResult.success, isFalse);
    final invoices = await db.select(db.localInvoices).get();
    expect(invoices, hasLength(1));
    expect(invoices.single.totalAmount, 1000);
  });

  test('return on a closed shift is rejected and does not restock', () async {
    final s = await seed();
    await checkout.execute(
      CheckoutCommand(
        workspaceId: PosMode.standaloneWorkspaceId,
        deviceId: 'dev-1',
        storeId: s.storeId,
        permissions: adminPerms,
        clientReference: 'sale-closed-shift',
        orderType: 'takeaway',
        lines: [
          PricedLine(
            productLocalId: s.productId,
            name: 'شاي',
            quantity: 1,
            unitPrice: 10,
          ),
        ],
        payments: const [
          PaymentTender(method: 'cash', amount: 10, tendered: 10),
        ],
        shiftLocalId: s.shiftId,
      ),
    );
    await shifts.close(
      workspaceId: PosMode.standaloneWorkspaceId,
      shiftId: s.shiftId,
      actualCash: 110,
      permissions: adminPerms,
    );
    final item = (await db.select(db.localOrderItems).get()).last;
    expect(
      () => returns.execute(
        workspaceId: PosMode.standaloneWorkspaceId,
        orderLocalId: 'sale-closed-shift',
        lines: [ReturnLineInput(orderItemLocalId: item.localId, quantity: 1)],
        allowNegativeStock: false,
        shiftLocalId: s.shiftId,
        permissions: adminPerms,
      ),
      throwsA(isA<ShiftNotOpen>()),
    );
    final product = await (db.select(db.localProducts)
          ..where((t) => t.localId.equals(s.productId)))
        .getSingle();
    expect(product.stock, 9);
    expect(await db.select(db.localReturns).get(), isEmpty);
  });

  test('clear after add does not resurrect the draft', () async {
    final created = await auth.bootstrapStore(
      storeName: 'مسح',
      adminName: 'مدير',
      username: 'admin4',
      pin: '1234',
    );
    final productId = await catalog.createProduct(
      workspaceId: PosMode.standaloneWorkspaceId,
      name: 'شاي',
      price: 5,
      stock: 5,
      permissions: adminPerms,
    );
    final cart = CartController(
      store: DraftCartStore(db),
      workspaceId: PosMode.standaloneWorkspaceId,
    );
    await cart.idle;
    cart.addItem(productLocalId: productId, name: 'شاي', unitPrice: 5);
    cart.clear();
    await cart.idle;
    expect(cart.state.lines, isEmpty);
    final restored = CartController(
      store: DraftCartStore(db),
      workspaceId: PosMode.standaloneWorkspaceId,
    );
    await restored.idle;
    expect(restored.state.lines, isEmpty);
    expect(created.store.localId, isNotEmpty);
  });

  test('cart ignores missing local product ids', () {
    final cart = CartController();
    cart.addItem(productLocalId: '', name: 'شاي', unitPrice: 5);
    cart.addItem(productLocalId: 'null', name: 'شاي', unitPrice: 5);
    cart.addItem(productLocalId: '0', name: 'شاي', unitPrice: 5);
    expect(cart.state.lines, isEmpty);
  });

  test('parallel duplicate returns with same client ref restock once', () async {
    final s = await seed();
    await checkout.execute(
      CheckoutCommand(
        workspaceId: PosMode.standaloneWorkspaceId,
        deviceId: 'dev-1',
        storeId: s.storeId,
        permissions: adminPerms,
        clientReference: 'sale-par-ret',
        orderType: 'takeaway',
        lines: [
          PricedLine(
            productLocalId: s.productId,
            name: 'شاي',
            quantity: 1,
            unitPrice: 10,
          ),
        ],
        payments: const [
          PaymentTender(method: 'cash', amount: 10, tendered: 10),
        ],
        shiftLocalId: s.shiftId,
      ),
    );
    final item = (await (db.select(db.localOrderItems)
          ..where((t) => t.orderLocalId.equals('sale-par-ret')))
        .get())
        .single;
    Future<String> refund() => returns.execute(
      workspaceId: PosMode.standaloneWorkspaceId,
      orderLocalId: 'sale-par-ret',
      lines: [ReturnLineInput(orderItemLocalId: item.localId, quantity: 1)],
      allowNegativeStock: false,
      shiftLocalId: s.shiftId,
      clientReference: 'ret-par',
      permissions: adminPerms,
    );
    final results = await Future.wait([refund(), refund()]);
    expect(results.first, results.last);
    expect(await db.select(db.localReturns).get(), hasLength(1));
    final product = await (db.select(db.localProducts)
          ..where((t) => t.localId.equals(s.productId)))
        .getSingle();
    expect(product.stock, 10);
  });
}
