import 'dart:convert';
import 'dart:io';

import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hasim_cashier/core/local_db/app_database.dart';
import 'package:hasim_cashier/core/local_db/workspace_scope.dart';
import 'package:hasim_cashier/core/pos/application/backup_service.dart';
import 'package:hasim_cashier/core/pos/application/catalog_admin_service.dart';
import 'package:hasim_cashier/core/pos/application/checkout_service.dart';
import 'package:hasim_cashier/core/pos/application/document_numbers.dart';
import 'package:hasim_cashier/core/pos/application/draft_cart_store.dart';
import 'package:hasim_cashier/core/pos/application/local_auth_service.dart';
import 'package:hasim_cashier/core/pos/application/reports_service.dart';
import 'package:hasim_cashier/core/pos/application/return_service.dart';
import 'package:hasim_cashier/core/pos/application/shift_service.dart';
import 'package:hasim_cashier/core/pos/application/stock_engine.dart';
import 'package:hasim_cashier/core/pos/domain/pricing_service.dart';
import 'package:hasim_cashier/core/pos/pos_errors.dart';
import 'package:hasim_cashier/core/pos/pos_mode.dart';
import 'package:hasim_cashier/core/repositories/sync_queue_repository.dart';
import 'package:hasim_cashier/features/cart/cart_controller.dart';

void main() {
  late AppDatabase db;
  late CatalogAdminService catalog;
  late CheckoutService checkout;
  late StockEngine stock;
  late DocumentNumberService numbers;
  late ShiftService shifts;
  late ReturnService returns;
  late LocalAuthService auth;

  setUp(() {
    db = AppDatabase.memory();
    catalog = CatalogAdminService(db);
    stock = StockEngine(db);
    numbers = DocumentNumberService(db);
    checkout = CheckoutService(db, stock, numbers, SyncQueueRepository(db));
    shifts = ShiftService(db);
    returns = ReturnService(db, stock);
    auth = LocalAuthService(db);
  });

  tearDown(() async {
    await db.close();
  });

  Future<({String storeId, String productId, String userId, String shiftId})>
      seedStore() async {
    final created = await auth.bootstrapStore(
      storeName: 'متجر اختبار',
      adminName: 'مدير',
      username: 'admin',
      pin: '1234',
      taxRate: 15,
    );
    final productId = await catalog.createProduct(
      workspaceId: PosMode.standaloneWorkspaceId,
      name: 'برجر',
      price: 10,
      cost: 4,
      taxRate: 15,
      stock: 10,
      trackStock: true,
      barcode: '123456',
    );
    final shiftId = await shifts.open(
      workspaceId: PosMode.standaloneWorkspaceId,
      userId: created.user.localId,
      openingCash: 100,
    );
    return (
      storeId: created.store.localId,
      productId: productId,
      userId: created.user.localId,
      shiftId: shiftId,
    );
  }

  test('pricing engine rounds tax after discount', () {
    const pricing = PricingService();
    final quote = pricing.quote(
      lines: const [
        PricedLine(
          productLocalId: 'p1',
          name: 'شاي',
          quantity: 2,
          unitPrice: 10,
        ),
      ],
      orderDiscountAmount: 5,
      fallbackTaxRate: 10,
    );
    expect(quote.subtotal, 20);
    expect(quote.orderDiscount, 5);
    expect(quote.taxAmount, 1.5);
    expect(quote.total, 16.5);
  });

  test(
    'standalone sale writes invoice tax payments and stock atomically',
    () async {
      final seed = await seedStore();
      final result = await checkout.execute(
        CheckoutCommand(
          workspaceId: PosMode.standaloneWorkspaceId,
          deviceId: 'dev-1',
          storeId: seed.storeId,
          clientReference: 'sale-1',
          orderType: 'takeaway',
          lines: [
            PricedLine(
              productLocalId: seed.productId,
              name: 'برجر',
              quantity: 3,
              unitPrice: 10,
              taxRate: 15,
              cost: 4,
            ),
          ],
          payments: const [
            PaymentTender(method: 'cash', amount: 34.5, tendered: 40),
          ],
          taxRate: 15,
          createdByUserId: seed.userId,
          shiftLocalId: seed.shiftId,
        ),
      );
      expect(result.invoiceNumber, 'INV-000001');
      expect(result.total, 34.5);
      expect(result.changeDue, 5.5);

      final order = await (db.select(
        db.localOrders,
      )..where((t) => t.localId.equals('sale-1'))).getSingle();
      expect(order.taxAmount, 4.5);
      expect(order.subtotal, 30);
      expect(order.totalAmount, 34.5);
      expect(order.paymentStatus, 'paid');

      final item = (await (db.select(
        db.localOrderItems,
      )..where((t) => t.orderLocalId.equals('sale-1'))).get()).single;
      expect(item.taxRate, 15);
      expect(item.taxAmount, 4.5);
      expect(item.name, 'برجر');

      final product = await (db.select(
        db.localProducts,
      )..where((t) => t.localId.equals(seed.productId))).getSingle();
      expect(product.stock, 7);

      final movement = (await db.select(db.localStockMovements).get()).single;
      expect(movement.beforeQuantity, 10);
      expect(movement.afterQuantity, 7);
      expect(movement.kind, 'sale');

      final queue = await db.select(db.syncQueueItems).get();
      expect(queue, isEmpty);
    },
  );

  test('double tap pay is idempotent', () async {
    final seed = await seedStore();
    final cmd = CheckoutCommand(
      workspaceId: PosMode.standaloneWorkspaceId,
      deviceId: 'dev-1',
      storeId: seed.storeId,
      clientReference: 'sale-dup',
      orderType: 'delivery',
      lines: [
        PricedLine(
          productLocalId: seed.productId,
          name: 'برجر',
          quantity: 1,
          unitPrice: 10,
          taxRate: 0,
        ),
      ],
      payments: const [PaymentTender(method: 'card', amount: 10)],
      shiftLocalId: seed.shiftId,
    );
    final a = await checkout.execute(cmd);
    final b = await checkout.execute(cmd);
    expect(a.invoiceLocalId, b.invoiceLocalId);
    expect(await db.select(db.localOrders).get(), hasLength(1));
    expect(await db.select(db.localInvoices).get(), hasLength(1));
  });

  test('insufficient stock blocks the whole sale', () async {
    final seed = await seedStore();
    expect(
      () => checkout.execute(
        CheckoutCommand(
          workspaceId: PosMode.standaloneWorkspaceId,
          deviceId: 'dev-1',
          storeId: seed.storeId,
          clientReference: 'sale-stock',
          orderType: 'takeaway',
          lines: [
            PricedLine(
              productLocalId: seed.productId,
              name: 'برجر',
              quantity: 99,
              unitPrice: 10,
            ),
          ],
          payments: const [PaymentTender(method: 'cash', amount: 990)],
          shiftLocalId: seed.shiftId,
        ),
      ),
      throwsA(isA<InsufficientStock>()),
    );
    expect(await db.select(db.localOrders).get(), isEmpty);
    final product = await (db.select(
      db.localProducts,
    )..where((t) => t.localId.equals(seed.productId))).getSingle();
    expect(product.stock, 10);
  });

  test('split payment cash+card and credit shortfall', () async {
    final seed = await seedStore();
    final result = await checkout.execute(
      CheckoutCommand(
        workspaceId: PosMode.standaloneWorkspaceId,
        deviceId: 'dev-1',
        storeId: seed.storeId,
        clientReference: 'sale-split',
        orderType: 'takeaway',
        lines: [
          PricedLine(
            productLocalId: seed.productId,
            name: 'برجر',
            quantity: 2,
            unitPrice: 25,
          ),
        ],
        payments: const [
          PaymentTender(method: 'cash', amount: 20, tendered: 20),
          PaymentTender(method: 'card', amount: 30),
        ],
        shiftLocalId: seed.shiftId,
      ),
    );
    expect(result.total, 50);
    final pays = await db.select(db.localPayments).get();
    expect(pays, hasLength(2));
    expect(pays.map((p) => p.method), containsAll(['cash', 'card']));
  });

  test('return restocks and records refund cash movement', () async {
    final seed = await seedStore();
    await checkout.execute(
      CheckoutCommand(
        workspaceId: PosMode.standaloneWorkspaceId,
        deviceId: 'dev-1',
        storeId: seed.storeId,
        clientReference: 'sale-ret',
        orderType: 'takeaway',
        lines: [
          PricedLine(
            productLocalId: seed.productId,
            name: 'برجر',
            quantity: 2,
            unitPrice: 10,
          ),
        ],
        payments: const [
          PaymentTender(method: 'cash', amount: 20, tendered: 20),
        ],
        shiftLocalId: seed.shiftId,
      ),
    );
    final item = (await db.select(db.localOrderItems).get()).single;
    await returns.execute(
      workspaceId: PosMode.standaloneWorkspaceId,
      orderLocalId: 'sale-ret',
      lines: [ReturnLineInput(orderItemLocalId: item.localId, quantity: 1)],
      allowNegativeStock: true,
      shiftLocalId: seed.shiftId,
      createdByUserId: seed.userId,
      deviceId: 'dev-1',
    );
    final product = await (db.select(
      db.localProducts,
    )..where((t) => t.localId.equals(seed.productId))).getSingle();
    expect(product.stock, 9);
    expect(
      () => returns.execute(
        workspaceId: PosMode.standaloneWorkspaceId,
        orderLocalId: 'sale-ret',
        lines: [ReturnLineInput(orderItemLocalId: item.localId, quantity: 3)],
        allowNegativeStock: true,
      ),
      throwsA(isA<InvalidReturnQuantity>()),
    );
  });

  test('draft cart survives database reopen', () async {
    final dir = await Directory.systemTemp.createTemp('pos-draft');
    final file = File('${dir.path}/draft.sqlite');
    var fileDb = AppDatabase(NativeDatabase(file));
    final store = DraftCartStore(fileDb);
    await store.save(
      workspaceId: 1,
      channel: 'takeaway',
      lines: const [
        PricedLine(
          productLocalId: 'p1',
          name: 'شاي',
          quantity: 2,
          unitPrice: 5,
        ),
      ],
    );
    await fileDb.close();
    fileDb = AppDatabase(NativeDatabase(file));
    final loaded = await DraftCartStore(
      fileDb,
    ).load(workspaceId: 1, channel: 'takeaway');
    expect(loaded, isNotNull);
    expect(loaded!.lines.single.quantity, 2);
    expect(loaded.lines.single.name, 'شاي');
    await fileDb.close();
    await dir.delete(recursive: true);
  });

  test('document numbers never use max(id)+1', () async {
    expect(await numbers.nextInvoiceNumber(storeId: 's1'), 'INV-000001');
    expect(await numbers.nextInvoiceNumber(storeId: 's1'), 'INV-000002');
    expect(await numbers.nextOrderNumber(storeId: 's1'), 'ORD-000001');
  });

  test('local pin auth and reports', () async {
    final seed = await seedStore();
    final user = await auth.login(
      workspaceId: PosMode.standaloneWorkspaceId,
      username: 'admin',
      pin: '1234',
    );
    expect(user.role, 'admin');
    expect(
      () => auth.login(
        workspaceId: PosMode.standaloneWorkspaceId,
        username: 'admin',
        pin: '0000',
      ),
      throwsA(isA<InvalidPin>()),
    );

    await checkout.execute(
      CheckoutCommand(
        workspaceId: PosMode.standaloneWorkspaceId,
        deviceId: 'dev-1',
        storeId: seed.storeId,
        clientReference: 'sale-rep',
        orderType: 'takeaway',
        lines: [
          PricedLine(
            productLocalId: seed.productId,
            name: 'برجر',
            quantity: 1,
            unitPrice: 10,
          ),
        ],
        payments: const [
          PaymentTender(method: 'cash', amount: 10, tendered: 10),
        ],
        shiftLocalId: seed.shiftId,
      ),
    );
    final report = await LocalReportsService(
      db,
    ).daily(workspaceId: PosMode.standaloneWorkspaceId, date: DateTime.now());
    expect(report['summary']['invoices_count'], 1);
    expect((report['payment_methods'] as List).first['total'], 10);
    expect((report['payment_methods'] as List).first['method'], 'cash');
  });

  test('backup export and restore keep invoices', () async {
    final seed = await seedStore();
    await checkout.execute(
      CheckoutCommand(
        workspaceId: PosMode.standaloneWorkspaceId,
        deviceId: 'dev-1',
        storeId: seed.storeId,
        clientReference: 'sale-bak',
        orderType: 'takeaway',
        lines: [
          PricedLine(
            productLocalId: seed.productId,
            name: 'برجر',
            quantity: 1,
            unitPrice: 10,
          ),
        ],
        payments: const [
          PaymentTender(method: 'cash', amount: 10, tendered: 10),
        ],
        shiftLocalId: seed.shiftId,
      ),
    );
    final backup = BackupService(db);
    final file = await backup.exportBackup(
      workspaceId: PosMode.standaloneWorkspaceId,
    );
    final payload = jsonDecode(await file.readAsString()) as Map;
    expect(payload['format_version'], 1);
    expect((payload['tables'] as Map)['local_invoices'], isNotEmpty);
    await backup.restore(Map<String, dynamic>.from(payload), confirmed: true);
    expect(await db.select(db.localInvoices).get(), isNotEmpty);
  });

  test('shift expected cash formula', () async {
    final seed = await seedStore();
    await checkout.execute(
      CheckoutCommand(
        workspaceId: PosMode.standaloneWorkspaceId,
        deviceId: 'dev-1',
        storeId: seed.storeId,
        clientReference: 'sale-shift',
        orderType: 'takeaway',
        lines: [
          PricedLine(
            productLocalId: seed.productId,
            name: 'برجر',
            quantity: 1,
            unitPrice: 10,
          ),
        ],
        payments: const [
          PaymentTender(method: 'cash', amount: 10, tendered: 10),
        ],
        shiftLocalId: seed.shiftId,
      ),
    );
    final closed = await shifts.close(
      workspaceId: PosMode.standaloneWorkspaceId,
      shiftId: seed.shiftId,
      actualCash: 109,
    );
    expect(closed['expected'], 110);
    expect(closed['difference'], -1);
  });

  test('barcode lookup is local', () async {
    await seedStore();
    final hit = await catalog.findByBarcode(
      workspaceId: PosMode.standaloneWorkspaceId,
      barcode: '123456',
    );
    expect(hit?['name'], 'برجر');
  });

  test('store existence makes offline POS ready without products', () async {
    expect(await db.isOfflinePosReady(1), isFalse);
    await auth.bootstrapStore(
      storeName: 'فارغ',
      adminName: 'مدير',
      username: 'admin',
      pin: '1234',
    );
    expect(await db.isOfflinePosReady(1), isTrue);
  });

  test('cart controller still totals after productLocalId rewrite', () {
    final cart = CartController();
    cart.setTaxRate(10);
    cart.addItem(
      productLocalId: '1',
      menuItemId: 1,
      name: 'شاي',
      unitPrice: 10,
    );
    cart.addItem(
      productLocalId: '1',
      menuItemId: 1,
      name: 'شاي',
      unitPrice: 10,
    );
    cart.setDiscount(5);
    expect(cart.state.subtotal, 20);
    expect(cart.state.taxAmount, 1.5);
    expect(cart.state.total, 16.5);
    expect(cart.state.channel, OrderChannel.takeaway);
  });
}
