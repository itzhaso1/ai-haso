import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:uuid/uuid.dart';

import '../../core/api/cashier_api.dart';
import '../../core/auth/auth_controller.dart';
import '../../core/navigation/pos_shell_nav.dart';
import '../../core/network/cashier_link.dart';
import '../../core/local_db/local_db_providers.dart';
import '../../core/offline/offline_store.dart';
import '../../core/permissions/cashier_permissions.dart';
import '../../core/permissions/permissions_provider.dart';
import '../../core/printing/printer_service.dart';
import '../../core/sync/pos_sync_coordinator.dart';
import '../../core/theme/hasim_colors.dart';
import '../../core/theme/hasim_radius.dart';
import '../../core/theme/hasim_spacing.dart';
import '../../core/widgets/hasim_widgets.dart';
import '../admin/admin_placeholders.dart';
import '../cart/cart_controller.dart';
import '../customers/customers_panel.dart';
import '../invoices/invoices_list.dart';
import '../kitchen/kitchen_board.dart';
import '../offline/sync_queue_panel.dart';
import '../orders/menu_orders_feed.dart';
import '../orders/orders_list.dart';
import '../reports/daily_reports_panel.dart';
import '../settings/settings_panel.dart';
import '../tables/tables_board.dart';
import '../tables/table_workspace.dart';

enum _PosSection {
  cashier,
  tables,
  orders,
  menu,
  kitchen,
  invoices,
  customers,
  items,
  reports,
  sync,
  settings,
}

class ShellScreen extends ConsumerStatefulWidget {
  const ShellScreen({super.key});

  @override
  ConsumerState<ShellScreen> createState() => _ShellScreenState();
}

class _ShellScreenState extends ConsumerState<ShellScreen> {
  _PosSection _section = _PosSection.cashier;
  var _selectedCategoryId = 0;
  final _search = TextEditingController();
  Map<String, dynamic>? _bootstrap;
  String? _bootstrapError;
  var _bootstrapInFlight = false;
  var _pendingSync = 0;

  @override
  void initState() {
    super.initState();
    _loadBootstrap();
    _watchConnectivity();
    _refreshPending();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _watchConnectivity() async {
    final result = await Connectivity().checkConnectivity();
    final deviceOnline = !_isOffline(result);
    ref.read(cashierLinkProvider.notifier).setDeviceOnline(deviceOnline);
    Connectivity().onConnectivityChanged.listen((event) {
      final nowOnline = !_isOffline(event);
      final wasOffline = !ref.read(cashierLinkProvider).deviceOnline;
      ref.read(cashierLinkProvider.notifier).setDeviceOnline(nowOnline);
      if (nowOnline && wasOffline) {
        _loadBootstrap();
        ref.read(posSyncCoordinatorProvider).flushPendingOrders(
              workspaceId: ref.read(workspaceIdProvider),
              deviceId: ref.read(deviceIdHeaderProvider),
            ).then((_) {
          _refreshPending();
        });
      }
    });
  }

  bool _isOffline(List<ConnectivityResult> results) {
    return results.isEmpty ||
        results.every((r) => r == ConnectivityResult.none);
  }

  void _refreshPending() {
    if (!mounted) return;
    final workspaceId = ref.read(workspaceIdProvider);
    if (workspaceId == null) {
      setState(() => _pendingSync = 0);
      return;
    }
    ref.read(syncQueueRepositoryProvider).pendingCount(workspaceId).then((n) {
      if (!mounted) return;
      setState(() => _pendingSync = n);
    });
  }

  Future<Map<String, dynamic>> _enqueueLocalTableOrder({
    required int tableId,
    required int workspaceId,
    required String clientRef,
    required String? notes,
    required List<Map<String, dynamic>> items,
  }) async {
    final deviceId =
        await ref.read(deviceIdentityProvider).getOrCreateDeviceId();
    return ref.read(ordersRepositoryProvider).createTableOrder(
          workspaceId: workspaceId,
          deviceId: deviceId,
          tableId: tableId,
          clientReference: clientRef,
          notes: notes,
          items: items,
        );
  }

  Future<Map<String, dynamic>> _enqueueLocalTakeawayOrder({
    required int workspaceId,
    required String clientRef,
    required String? notes,
    required List<Map<String, dynamic>> items,
    int? customerServerId,
  }) async {
    final deviceId =
        await ref.read(deviceIdentityProvider).getOrCreateDeviceId();
    return ref.read(ordersRepositoryProvider).createTakeawayOrder(
          workspaceId: workspaceId,
          deviceId: deviceId,
          clientReference: clientRef,
          notes: notes,
          items: items,
          customerServerId: customerServerId,
        );
  }

  Future<void> _migrateHiveOrders(int workspaceId) async {
    try {
      final deviceId =
          await ref.read(deviceIdentityProvider).getOrCreateDeviceId();
      await ref.read(ordersRepositoryProvider).migrateHivePending(
            workspaceId: workspaceId,
            deviceId: deviceId,
          );
    } catch (_) {}
  }

  Future<void> _loadBootstrap() async {
    if (_bootstrapInFlight) return;
    _bootstrapInFlight = true;
    // Seed permissions from auth session immediately so reports/nav aren't
    // hidden while bootstrap is in-flight (root cause of missing reports).
    final session = ref.read(authControllerProvider).valueOrNull;
    final sessionPerms = session?.permissions;
    if (sessionPerms != null &&
        sessionPerms.isNotEmpty &&
        ref.read(cashierPermissionsProvider).isEmpty) {
      ref.read(cashierPermissionsProvider.notifier).state =
          Map<String, dynamic>.from(sessionPerms);
    }
    if (session?.isLocalMode == true || session?.token == 'local-offline') {
      _applyBootstrapPayload({
        'pos_enabled': true,
        'permissions': sessionPerms ?? const {},
        'workspace': session?.workspace,
        'user': session?.user,
        'settings': {'tax_rate': 0},
      }, fromCache: true);
      final workspaceId = ref.read(workspaceIdProvider);
      if (workspaceId != null) {
        ref.invalidate(localPosReadyProvider(workspaceId));
        ref.invalidate(catalogItemsProvider);
        ref.invalidate(categoriesProvider);
      }
      setState(() => _bootstrapError = null);
      _bootstrapInFlight = false;
      return;
    }
    final cached = OfflineStore.instance.readBootstrap();
    try {
      final data = await ref.read(cashierApiProvider).get('/bootstrap');
      if (!mounted) return;
      if (data['pos_enabled'] != true) {
        context.go('/pos-blocked');
        return;
      }
      await OfflineStore.instance.cacheBootstrap(data);
      _applyBootstrapPayload(data, fromCache: false);
      final workspaceId = ref.read(workspaceIdProvider);
      if (workspaceId != null) {
        try {
          final deviceId =
              await ref.read(deviceIdentityProvider).getOrCreateDeviceId();
          ref.read(deviceIdHeaderProvider.notifier).state = deviceId;
          await ref.read(deviceRegistrationServiceProvider).register(
                deviceId: deviceId,
              );
          final syncResult =
              await ref.read(initialSyncServiceProvider).ensureReady(workspaceId);
          if (!syncResult.fromCache) {
            await ref.read(syncEngineV2Provider).anchorCursorToServerHead(
                  workspaceId: workspaceId,
                  deviceId: deviceId,
                );
          }
          await _migrateHiveOrders(workspaceId);
          ref.invalidate(localPosReadyProvider(workspaceId));
          ref.invalidate(catalogItemsProvider);
          ref.invalidate(categoriesProvider);
        } catch (_) {
          // Keep POS online path; offline readiness stays gated until sync succeeds.
        }
      }
      final deviceId = ref.read(deviceIdHeaderProvider);
      await ref.read(posSyncCoordinatorProvider).flushPendingOrders(
            workspaceId: workspaceId,
            deviceId: deviceId,
          );
      _refreshPending();
    } on ApiException catch (e) {
      if (e.isUnauthorized) {
        ref.read(cashierPermissionsProvider.notifier).state = {};
        await ref.read(authControllerProvider.notifier).logout();
        if (mounted) context.go('/login');
        return;
      }
      if (e.isForbidden && !e.isUnavailable) {
        if (mounted) context.go('/pos-blocked');
        return;
      }
      if (!mounted) return;
      if (cached != null && _bootstrap == null) {
        _applyBootstrapPayload(cached, fromCache: true);
      }
      setState(() {
        _bootstrapError = e.message;
      });
    } catch (e) {
      if (!mounted) return;
      if (cached != null && _bootstrap == null) {
        _applyBootstrapPayload(cached, fromCache: true);
      }
      setState(() {
        _bootstrapError = e.toString();
      });
    } finally {
      _bootstrapInFlight = false;
    }
  }

  void _applyBootstrapPayload(
    Map<String, dynamic> data, {
    required bool fromCache,
  }) {
    final settings = data['settings'];
    if (settings is Map && settings['tax_rate'] != null) {
      ref
          .read(cartControllerProvider.notifier)
          .setTaxRate((settings['tax_rate'] as num).toDouble());
    }
    if (data['permissions'] is Map) {
      final perms = Map<String, dynamic>.from(data['permissions'] as Map);
      ref.read(cashierPermissionsProvider.notifier).state = perms;
      ref.read(authControllerProvider.notifier).applyBootstrapSnapshot(
            permissions: perms,
            workspace: data['workspace'] is Map
                ? Map<String, dynamic>.from(data['workspace'] as Map)
                : null,
            entitlements: data['entitlements'] is Map
                ? Map<String, dynamic>.from(data['entitlements'] as Map)
                : null,
            posEnabled: data['pos_enabled'] == true ? true : null,
          );
    }
    setState(() {
      _bootstrap = data;
      _bootstrapError = fromCache ? _bootstrapError : null;
    });
  }

  @override
  Widget build(BuildContext context) {
    ref.listen<PosShellTab?>(posShellNavProvider, (prev, next) {
      if (next == null) return;
      setState(() {
        _section = switch (next) {
          PosShellTab.cashier => _PosSection.cashier,
          PosShellTab.tables => _PosSection.tables,
          PosShellTab.orders => _PosSection.orders,
          PosShellTab.menu => _PosSection.menu,
          PosShellTab.kitchen => _PosSection.kitchen,
          PosShellTab.invoices => _PosSection.invoices,
          PosShellTab.customers => _PosSection.customers,
          PosShellTab.items => _PosSection.items,
          PosShellTab.reports => _PosSection.reports,
          PosShellTab.sync => _PosSection.sync,
          PosShellTab.settings => _PosSection.settings,
        };
      });
      Future.microtask(
        () => ref.read(posShellNavProvider.notifier).state = null,
      );
    });

    final width = MediaQuery.sizeOf(context).width;
    final isDesktop = width >= 1100;
    final isTablet = width >= 800 && width < 1100;
    final cart = ref.watch(cartControllerProvider);
    final session = ref.watch(authControllerProvider).valueOrNull;
    final link = ref.watch(cashierLinkProvider);
    final workspaceName =
        (session?.workspace?['name'] as String?) ?? 'مساحة العمل';

    return Scaffold(
      body: Column(
        children: [
          _TopHeader(
            workspaceName: workspaceName,
            cartCount: cart.lines.fold<int>(0, (s, l) => s + l.quantity),
            online: link.isOnline,
            onCart: isDesktop
                ? null
                : () => _openCartSheet(context),
            onLogout: () async {
              await ref.read(authControllerProvider.notifier).logout();
              if (context.mounted) context.go('/login');
            },
            onSync: () async {
              if (!link.allowMutations) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('المزامنة تتطلب اتصالًا بالخادم.'),
                  ),
                );
                return;
              }
              final result =
                  await ref.read(posSyncCoordinatorProvider).flushPendingOrders(
                        workspaceId: ref.read(workspaceIdProvider),
                        deviceId: ref.read(deviceIdHeaderProvider),
                      );
              _refreshPending();
              if (!context.mounted) return;
              if (result.authRequired) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text(
                      'انتهت الجلسة. سجّل الدخول مجددًا لإكمال المزامنة.',
                    ),
                  ),
                );
                return;
              }
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text('تمت مزامنة ${result.synced} طلبات')),
              );
            },
          ),
          _TopNav(
            section: _section,
            onSelect: (s) => setState(() => _section = s),
          ),
          ConnectionBanner(
            link: link.link,
            pendingCount: _pendingSync,
            onRetry: _loadBootstrap,
          ),
          Expanded(
            child: switch (_section) {
                    _PosSection.cashier => _CashierHome(
                        isDesktop: isDesktop,
                        isTablet: isTablet,
                        search: _search,
                        selectedCategoryId: _selectedCategoryId,
                        onCategory: (id) =>
                            setState(() => _selectedCategoryId = id),
                        onSearchChanged: () => setState(() {}),
                        onCheckout: _checkout,
                        onOpenMobileCart: () => _openCartSheet(context),
                      ),
                    _PosSection.tables => const TablesBoard(),
                    _PosSection.orders => const OrdersList(),
                    _PosSection.menu => const MenuOrdersFeed(),
                    _PosSection.kitchen => const KitchenBoard(),
                    _PosSection.invoices => const InvoicesList(),
                    _PosSection.customers => const CustomersPanel(),
                    _PosSection.items => const ItemsAdminPanel(),
                    _PosSection.reports => const DailyReportsPanel(),
                    _PosSection.sync => const SyncQueuePanel(),
                    _PosSection.settings => const SettingsPanel(),
                  },
          ),
        ],
      ),
      floatingActionButton: (!isDesktop && _section == _PosSection.cashier)
          ? FloatingActionButton.extended(
              backgroundColor: HasimColors.cta,
              onPressed: () => _openCartSheet(context),
              icon: const Icon(Icons.shopping_bag_outlined),
              label: Text('السلة (${cart.lines.length})'),
            )
          : null,
    );
  }

  Future<void> _openCartSheet(BuildContext context) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: SizedBox(
          height: MediaQuery.sizeOf(context).height * 0.82,
          child: Material(
            color: HasimColors.page,
            borderRadius: const BorderRadius.vertical(
              top: Radius.circular(HasimRadius.lg),
            ),
            child: _CartPanel(onCheckout: _checkout),
          ),
        ),
      ),
    );
  }

  Future<void> _checkout() async {
    final cart = ref.read(cartControllerProvider);
    if (cart.lines.isEmpty) return;
    final perms = ref.read(cashierPermissionsProvider);
    if (!CashierPermissions.canCreateOrders(perms)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لا تملك صلاحية إنشاء طلبات.')),
      );
      return;
    }
    if (cart.channel == OrderChannel.table && cart.tableId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('اختر طاولة لطلب الطاولة.')),
      );
      return;
    }

    final wasTable = cart.channel == OrderChannel.table;
    final tableId = cart.tableId;
    final clientRef = const Uuid().v4();
    final lineItems = [
      for (final line in cart.lines)
        {
          'pos_menu_item_id': line.menuItemId,
          'name': line.name,
          'quantity': line.quantity,
          'unit_price': line.unitPrice,
          'total_amount': line.lineTotal,
        },
    ];
    final workspaceId = ref.read(workspaceIdProvider);
    if (workspaceId == null) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('لا توجد مساحة عمل محددة. لا يمكن حفظ الطلب محليًا.'),
        ),
      );
      return;
    }

    // Architecture: UI → Repository → SQLite first, then Sync Engine → Laravel.
    Map<String, dynamic> localOrder;
    try {
      if (wasTable) {
        if (tableId == null) return;
        localOrder = await _enqueueLocalTableOrder(
          tableId: tableId,
          workspaceId: workspaceId,
          clientRef: clientRef,
          notes: cart.notes,
          items: lineItems,
        );
      } else {
        localOrder = await _enqueueLocalTakeawayOrder(
          workspaceId: workspaceId,
          clientRef: clientRef,
          notes: cart.notes,
          items: lineItems,
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('تعذر حفظ الطلب محليًا: $e')),
      );
      return;
    }

    ref.read(cartControllerProvider.notifier).clear();
    _refreshPending();
    if (!mounted) return;

    // Never block the success UI on sync — offline timeouts made checkout feel stuck.
    final deviceId =
        await ref.read(deviceIdentityProvider).getOrCreateDeviceId();
    // ignore: unawaited_futures
    ref
        .read(posSyncCoordinatorProvider)
        .flushPendingOrders(
          workspaceId: workspaceId,
          deviceId: deviceId,
        )
        .then((_) {
      if (mounted) _refreshPending();
    });

    if (wasTable) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم حفظ الطلب على الطاولة.')),
      );
      if (tableId != null) {
        openTableWorkspace(ref, tableId);
        requestPosShellTab(ref, PosShellTab.tables);
      }
      return;
    }

    // Takeaway: show success immediately; invoice/print use local draft.
    final orderLabel =
        '${localOrder['order_number'] ?? localOrder['local_id'] ?? clientRef}';
    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (context) => _SuccessOrderDialog(
        orderNumber: orderLabel,
        onPrint: () async {
          Navigator.pop(context);
          try {
            final invoice =
                await ref.read(ordersRepositoryProvider).enqueueInvoiceForOrder(
                      workspaceId: workspaceId,
                      deviceId: deviceId,
                      orderLocalId: clientRef,
                    );
            // Sync in background — print local draft right away.
            // ignore: unawaited_futures
            ref.read(posSyncCoordinatorProvider).flushPendingOrders(
                  workspaceId: workspaceId,
                  deviceId: deviceId,
                );
            if (!context.mounted) return;
            final printer =
                await ref.read(printerServiceFutureProvider.future);
            final result = await printer.printInvoice(invoice);
            if (!context.mounted) return;
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(
                  result.success
                      ? 'تم إنشاء الفاتورة وطباعتها.'
                      : 'تم إنشاء الفاتورة. ${result.message}',
                ),
              ),
            );
          } catch (e) {
            if (!context.mounted) return;
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(e.toString())),
            );
          }
        },
        onContinue: () {
          Navigator.pop(context);
          // ignore: unawaited_futures
          () async {
            try {
              await ref.read(ordersRepositoryProvider).enqueueInvoiceForOrder(
                    workspaceId: workspaceId,
                    deviceId: deviceId,
                    orderLocalId: clientRef,
                  );
              await ref.read(posSyncCoordinatorProvider).flushPendingOrders(
                    workspaceId: workspaceId,
                    deviceId: deviceId,
                  );
              if (mounted) _refreshPending();
            } catch (_) {}
          }();
        },
      ),
    );
  }
}

class _TopHeader extends StatelessWidget {
  const _TopHeader({
    required this.workspaceName,
    required this.cartCount,
    required this.online,
    required this.onLogout,
    required this.onSync,
    this.onCart,
  });

  final String workspaceName;
  final int cartCount;
  final bool online;
  final VoidCallback onLogout;
  final VoidCallback onSync;
  final VoidCallback? onCart;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: HasimColors.surface.withValues(alpha: 0.95),
      child: SafeArea(
        bottom: false,
        child: Container(
          height: 56,
          padding: const EdgeInsets.symmetric(horizontal: 12),
          decoration: const BoxDecoration(
            border: Border(bottom: BorderSide(color: HasimColors.border)),
          ),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      workspaceName,
                      style: const TextStyle(
                        fontSize: 11,
                        color: HasimColors.muted,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const Text(
                      'واجهة الكاشير',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              Row(
                children: [
                  Icon(
                    Icons.circle,
                    size: 8,
                    color: online ? HasimColors.cta : HasimColors.warning,
                  ),
                  const SizedBox(width: 4),
                  Text(
                    online ? 'متصل' : 'غير متصل',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      color: online ? HasimColors.ctaDark : HasimColors.warning,
                    ),
                  ),
                ],
              ),
              const SizedBox(width: 8),
              IconButton(
                tooltip: 'مزامنة',
                onPressed: onSync,
                icon: const Icon(Icons.sync, size: 20),
              ),
              if (onCart != null)
                Stack(
                  clipBehavior: Clip.none,
                  children: [
                    IconButton(
                      onPressed: onCart,
                      icon: const Icon(Icons.shopping_bag_outlined),
                    ),
                    if (cartCount > 0)
                      Positioned(
                        top: 4,
                        left: 4,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 5,
                            vertical: 1,
                          ),
                          decoration: BoxDecoration(
                            color: HasimColors.cta,
                            borderRadius:
                                BorderRadius.circular(HasimRadius.pill),
                          ),
                          child: Text(
                            '$cartCount',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 10,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              TextButton(
                onPressed: onLogout,
                child: const Text(
                  'خروج',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: HasimColors.ink,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TopNav extends ConsumerWidget {
  const _TopNav({required this.section, required this.onSelect});

  final _PosSection section;
  final ValueChanged<_PosSection> onSelect;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // Match Web POS top-nav: reports always visible (auth happens on API).
    final items = <(_PosSection, String)>[
      (_PosSection.cashier, 'الكاشير'),
      (_PosSection.tables, 'الطاولات'),
      (_PosSection.menu, 'طلبات المنيو'),
      (_PosSection.orders, 'الطلبات'),
      (_PosSection.kitchen, 'المطبخ'),
      (_PosSection.invoices, 'الفواتير'),
      (_PosSection.reports, 'التقارير'),
      if (CashierPermissions.canManageMenu(
        CashierPermissions.resolve(
          ref.watch(cashierPermissionsProvider),
          ref.watch(authControllerProvider).valueOrNull?.permissions,
        ),
      ))
        (_PosSection.items, 'إدارة الأصناف'),
      (_PosSection.customers, 'العملاء'),
      (_PosSection.sync, 'المزامنة'),
      (_PosSection.settings, 'الإعدادات'),
    ];
    final menuBadge = ref.watch(menuNewOrdersCountProvider);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(8, 8, 8, 8),
      decoration: const BoxDecoration(
        color: HasimColors.surface,
        border: Border(bottom: BorderSide(color: HasimColors.border)),
      ),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            for (final item in items) ...[
              Stack(
                clipBehavior: Clip.none,
                children: [
                  HsNavPill(
                    label: item.$2,
                    selected: section == item.$1,
                    onTap: () => onSelect(item.$1),
                  ),
                  if (item.$1 == _PosSection.menu && menuBadge > 0)
                    Positioned(
                      top: -4,
                      left: -2,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 5,
                          vertical: 1,
                        ),
                        decoration: BoxDecoration(
                          color: HasimColors.warning,
                          borderRadius: BorderRadius.circular(99),
                        ),
                        child: Text(
                          '$menuBadge',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(width: 6),
            ],
          ],
        ),
      ),
    );
  }
}

class _CashierHome extends ConsumerWidget {
  const _CashierHome({
    required this.isDesktop,
    required this.isTablet,
    required this.search,
    required this.selectedCategoryId,
    required this.onCategory,
    required this.onSearchChanged,
    required this.onCheckout,
    required this.onOpenMobileCart,
  });

  final bool isDesktop;
  final bool isTablet;
  final TextEditingController search;
  final int selectedCategoryId;
  final ValueChanged<int> onCategory;
  final VoidCallback onSearchChanged;
  final Future<void> Function() onCheckout;
  final VoidCallback onOpenMobileCart;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final categories = ref.watch(categoriesProvider);
    final items = ref.watch(catalogItemsProvider);

    if (isDesktop || isTablet) {
      return Padding(
        padding: const EdgeInsets.all(HasimSpacing.md),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (isDesktop)
              SizedBox(
                width: 180,
                child: HsCard(
                  padding: const EdgeInsets.all(8),
                  child: categories.when(
                    data: (list) => ListView(
                      children: [
                        const Padding(
                          padding: EdgeInsets.fromLTRB(8, 4, 8, 8),
                          child: Text(
                            'التصنيفات',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w800,
                              color: HasimColors.muted,
                            ),
                          ),
                        ),
                        HsCategoryTile(
                          label: 'الكل',
                          count: (items.valueOrNull ?? []).length,
                          selected: selectedCategoryId == 0,
                          onTap: () => onCategory(0),
                        ),
                        for (final cat in list)
                          HsCategoryTile(
                            label: (cat['name'] as String?) ?? '',
                            count: (items.valueOrNull ?? [])
                                .where((i) =>
                                    i['pos_item_category_id'] == cat['id'])
                                .length,
                            selected: selectedCategoryId ==
                                (cat['id'] as num?)?.toInt(),
                            onTap: () =>
                                onCategory((cat['id'] as num).toInt()),
                          ),
                      ],
                    ),
                    loading: () =>
                        const Center(child: CircularProgressIndicator()),
                    error: (e, _) => Text('$e'),
                  ),
                ),
              ),
            if (isDesktop) const SizedBox(width: 10),
            Expanded(
              flex: 7,
              child: HsCard(
                padding: const EdgeInsets.all(10),
                child: _ProductsPanel(
                  search: search,
                  selectedCategoryId: selectedCategoryId,
                  onCategory: onCategory,
                  onSearchChanged: onSearchChanged,
                  showMobileCategories: !isDesktop,
                ),
              ),
            ),
            const SizedBox(width: 10),
            SizedBox(
              width: isDesktop ? 280 : 260,
              child: _CartPanel(onCheckout: onCheckout),
            ),
          ],
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.all(HasimSpacing.md),
      children: [
          categories.when(
            data: (list) => SizedBox(
              height: 52,
              child: ListView(
                scrollDirection: Axis.horizontal,
                children: [
                  _chip('الكل', selectedCategoryId == 0, () => onCategory(0)),
                  for (final cat in list)
                    _chip(
                      (cat['name'] as String?) ?? '',
                      selectedCategoryId == (cat['id'] as num?)?.toInt(),
                      () => onCategory((cat['id'] as num).toInt()),
                    ),
                ],
              ),
            ),
            loading: () => const LinearProgressIndicator(),
            error: (e, _) {
              final offline = OfflineStore.instance.readCategories(
                workspaceId: ref.read(workspaceIdProvider),
              );
              if (offline.isEmpty) {
                return HsEmpty(
                  title: 'تعذر تحميل التصنيفات',
                  subtitle: '$e',
                  actionLabel: 'إعادة',
                  onAction: () => ref.invalidate(categoriesProvider),
                );
              }
              return SizedBox(
                height: 52,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  children: [
                    _chip('الكل', selectedCategoryId == 0, () => onCategory(0)),
                    for (final cat in offline)
                      _chip(
                        (cat['name'] as String?) ?? '',
                        selectedCategoryId == (cat['id'] as num?)?.toInt(),
                        () => onCategory((cat['id'] as num).toInt()),
                      ),
                  ],
                ),
              );
            },
          ),
          const SizedBox(height: 8),
          HsCard(
            child: SizedBox(
              height: MediaQuery.sizeOf(context).height - 280,
              child: _ProductsPanel(
                search: search,
                selectedCategoryId: selectedCategoryId,
                onCategory: onCategory,
                onSearchChanged: onSearchChanged,
                showMobileCategories: false,
              ),
            ),
          ),
      ],
    );
  }

  Widget _chip(String label, bool selected, VoidCallback onTap) {
    return Padding(
      padding: const EdgeInsetsDirectional.only(end: 8),
      child: Material(
        color: selected ? HasimColors.brand : HasimColors.surface,
        borderRadius: BorderRadius.circular(HasimRadius.md),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(HasimRadius.md),
          child: Container(
            alignment: Alignment.center,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            constraints: const BoxConstraints(minHeight: 44, minWidth: 64),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(HasimRadius.md),
              border: Border.all(
                color: selected ? HasimColors.brand : HasimColors.border,
              ),
            ),
            child: Text(
              label,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w800,
                color: selected ? Colors.white : HasimColors.ink,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _ProductsPanel extends ConsumerWidget {
  const _ProductsPanel({
    required this.search,
    required this.selectedCategoryId,
    required this.onCategory,
    required this.onSearchChanged,
    required this.showMobileCategories,
  });

  final TextEditingController search;
  final int selectedCategoryId;
  final ValueChanged<int> onCategory;
  final VoidCallback onSearchChanged;
  final bool showMobileCategories;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final items = ref.watch(catalogItemsProvider);
    final width = MediaQuery.sizeOf(context).width;
    final crossAxis = width >= 1500
        ? 5
        : width >= 1200
            ? 4
            : width >= 900
                ? 3
                : 2;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            const Expanded(
              child: Text(
                'أصناف الكاشير',
                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
              ),
            ),
            SizedBox(
              width: width >= 500 ? 260 : 160,
              child: TextField(
                controller: search,
                onChanged: (_) => onSearchChanged(),
                onSubmitted: (_) {
                  // barcode enter parity with web
                },
                decoration: const InputDecoration(
                  hintText: 'ابحث بالاسم أو الباركود أو SKU...',
                  isDense: true,
                  prefixIcon: Icon(Icons.search, size: 18),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        Expanded(
          child: items.when(
            data: (list) {
              final q = search.text.trim().toLowerCase();
              final filtered = list.where((item) {
                if (selectedCategoryId != 0 &&
                    item['pos_item_category_id'] != selectedCategoryId) {
                  return false;
                }
                if (q.isEmpty) return true;
                final hay =
                    '${item['name']}|${item['sku']}|${item['barcode']}'
                        .toLowerCase();
                return hay.contains(q);
              }).toList();

              if (filtered.isEmpty) {
                final offline = OfflineStore.instance.readCatalog(
                  workspaceId: ref.read(workspaceIdProvider),
                );
                if (list.isEmpty && offline.isNotEmpty) {
                  return _grid(ref, offline, crossAxis);
                }
                return HsEmpty(
                  title: 'لا توجد منتجات في هذا التصنيف.',
                  actionLabel: 'عرض الكل',
                  onAction: () {
                    onCategory(0);
                    search.clear();
                    onSearchChanged();
                  },
                );
              }
              OfflineStore.instance.cacheCatalog(
                list,
                workspaceId: ref.read(workspaceIdProvider),
              );
              return _grid(ref, filtered, crossAxis);
            },
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) {
              final offline = OfflineStore.instance.readCatalog(
                workspaceId: ref.read(workspaceIdProvider),
              );
              if (offline.isNotEmpty) return _grid(ref, offline, crossAxis);
              return HsEmpty(title: 'تعذر تحميل المنتجات', subtitle: '$e');
            },
          ),
        ),
      ],
    );
  }

  Widget _grid(
    WidgetRef ref,
    List<Map<String, dynamic>> items,
    int crossAxis,
  ) {
    return GridView.builder(
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: crossAxis,
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        childAspectRatio: 0.72,
      ),
      itemCount: items.length,
      itemBuilder: (context, index) {
        final item = items[index];
        final id = (item['id'] as num?)?.toInt() ?? 0;
        final name = (item['name'] as String?) ?? '';
        final price = (item['price'] as num?)?.toDouble() ?? 0;
        final available = item['is_active'] != false &&
            item['availability'] != 'unavailable';
        return ProductCard(
          name: name,
          priceLabel: price.toStringAsFixed(2),
          currency: (item['currency'] as String?) ?? 'SAR',
          imageUrl: item['image_url'] as String?,
          sku: item['sku'] as String?,
          available: available,
          onAdd: () {
            ref.read(cartControllerProvider.notifier).addItem(
                  menuItemId: id,
                  name: name,
                  unitPrice: price,
                );
          },
        );
      },
    );
  }
}

class _CartPanel extends ConsumerStatefulWidget {
  const _CartPanel({required this.onCheckout});

  final Future<void> Function() onCheckout;

  @override
  ConsumerState<_CartPanel> createState() => _CartPanelState();
}

class _CartPanelState extends ConsumerState<_CartPanel> {
  List<Map<String, dynamic>> _tables = const [];
  final _notesController = TextEditingController();
  var _metaLoaded = false;

  @override
  void initState() {
    super.initState();
    // Never kick off setState from build — schedule after first frame.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _ensureMeta();
    });
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  void _syncNotesFromCart(String? notes) {
    final next = notes ?? '';
    if (_notesController.text == next) return;
    // Writing TextEditingController during build marks the element dirty mid-frame
    // and can cascade into semantics.parentDataDirty assertion storms.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      if (_notesController.text != next) {
        _notesController.text = next;
      }
    });
  }

  Future<void> _ensureMeta() async {
    if (_metaLoaded) return;
    _metaLoaded = true;
    final workspaceId = ref.read(workspaceIdProvider);
    if (workspaceId == null || workspaceId <= 0) return;
    try {
      // Local SQLite first — no network wait on cart open.
      final local =
          await ref.read(tablesRepositoryProvider).listTables(workspaceId);
      if (!mounted) return;
      if (local.isNotEmpty) {
        setState(() => _tables = local);
      }
      // Best-effort remote refresh in background.
      final board =
          await ref.read(tablesRepositoryProvider).loadBoard(workspaceId);
      if (!mounted) return;
      if (board.isNotEmpty) {
        setState(() => _tables = board);
      }
    } catch (_) {
      // Offline — takeaway still works without tables list.
    }
  }

  @override
  Widget build(BuildContext context) {
    final cart = ref.watch(cartControllerProvider);
    final notifier = ref.read(cartControllerProvider.notifier);
    if (cart.notes != null &&
        cart.notes!.isNotEmpty &&
        _notesController.text != cart.notes) {
      _syncNotesFromCart(cart.notes);
    }

    return HsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'طلب جديد',
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 10),
          const Text(
            'نوع الطلب',
            style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              for (final channel in OrderChannel.values)
                Expanded(
                  child: Padding(
                    padding: const EdgeInsetsDirectional.only(end: 4),
                    child: _OrderTypeChip(
                      label: channel.labelAr,
                      selected: cart.channel == channel,
                      onTap: () => notifier.setChannel(channel),
                    ),
                  ),
                ),
            ],
          ),
          if (cart.channel == OrderChannel.table) ...[
            const SizedBox(height: 8),
            DropdownButtonFormField<int>(
              value: _tables.any((t) => (t['id'] as num).toInt() == cart.tableId)
                  ? cart.tableId
                  : null,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'الطاولة',
                isDense: true,
              ),
              items: [
                for (final t in _tables)
                  DropdownMenuItem(
                    value: (t['id'] as num).toInt(),
                    child: Text('${t['name']}'),
                  ),
              ],
              onChanged: (v) => notifier.setTable(v),
            ),
          ],
          const SizedBox(height: 8),
          TextField(
            controller: _notesController,
            decoration: const InputDecoration(
              labelText: 'ملاحظات',
              isDense: true,
            ),
            maxLines: 2,
            onChanged: notifier.setNotes,
          ),
          const SizedBox(height: 8),
          TextField(
            enabled: CashierPermissions.canDiscount(
              ref.watch(cashierPermissionsProvider),
            ),
            decoration: InputDecoration(
              labelText: CashierPermissions.canDiscount(
                ref.watch(cashierPermissionsProvider),
              )
                  ? 'الخصم (مبلغ)'
                  : 'الخصم (غير مسموح)',
              isDense: true,
            ),
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            onChanged: CashierPermissions.canDiscount(
              ref.watch(cashierPermissionsProvider),
            )
                ? (v) => notifier.setDiscount(double.tryParse(v) ?? 0)
                : null,
          ),
          const SizedBox(height: 10),
          Expanded(
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: HasimColors.surfaceSoft,
                borderRadius: BorderRadius.circular(HasimRadius.md),
                border: Border.all(color: HasimColors.border),
              ),
              child: Padding(
                padding: const EdgeInsets.all(8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text(
                      'ملخص الطلب',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Expanded(
                      child: cart.lines.isEmpty
                          ? const Center(
                              child: Text(
                                'السلة فارغة.',
                                style: TextStyle(
                                  fontSize: 12,
                                  color: HasimColors.muted,
                                ),
                              ),
                            )
                          : ListView.separated(
                              itemCount: cart.lines.length,
                              separatorBuilder: (_, __) =>
                                  const SizedBox(height: 6),
                              itemBuilder: (context, index) {
                                final line = cart.lines[index];
                                return Container(
                                  padding: const EdgeInsets.all(6),
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    borderRadius:
                                        BorderRadius.circular(HasimRadius.sm),
                                  ),
                                  child: Column(
                                    children: [
                                      Row(
                                        children: [
                                          Expanded(
                                            child: Text(
                                              line.name,
                                              overflow: TextOverflow.ellipsis,
                                              style: const TextStyle(
                                                fontSize: 11,
                                                fontWeight: FontWeight.w700,
                                              ),
                                            ),
                                          ),
                                          Text(
                                            line.unitPrice.toStringAsFixed(2),
                                            style: const TextStyle(
                                              fontSize: 10,
                                              color: HasimColors.muted,
                                            ),
                                          ),
                                          TextButton(
                                            onPressed: () => notifier
                                                .removeItem(line.menuItemId),
                                            style: TextButton.styleFrom(
                                              padding: EdgeInsets.zero,
                                              minimumSize: const Size(32, 24),
                                              tapTargetSize:
                                                  MaterialTapTargetSize
                                                      .shrinkWrap,
                                            ),
                                            child: const Text(
                                              'حذف',
                                              style: TextStyle(
                                                color: HasimColors.danger,
                                                fontSize: 11,
                                                fontWeight: FontWeight.w700,
                                              ),
                                            ),
                                          ),
                                        ],
                                      ),
                                      Row(
                                        children: [
                                          _qtyBtn(
                                            '-',
                                            () => notifier.setQuantity(
                                              line.menuItemId,
                                              line.quantity - 1,
                                            ),
                                          ),
                                          Padding(
                                            padding: const EdgeInsets.symmetric(
                                              horizontal: 8,
                                            ),
                                            child: Text('${line.quantity}'),
                                          ),
                                          _qtyBtn(
                                            '+',
                                            () => notifier.setQuantity(
                                              line.menuItemId,
                                              line.quantity + 1,
                                            ),
                                          ),
                                          const Spacer(),
                                          Text(
                                            line.lineTotal.toStringAsFixed(2),
                                            style: const TextStyle(
                                              fontSize: 11,
                                              fontWeight: FontWeight.w700,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ],
                                  ),
                                );
                              },
                            ),
                    ),
                    const Divider(height: 16),
                    _money('المجموع الفرعي', cart.subtotal),
                    _money('الخصم', cart.discountAmount),
                    _money('الضريبة', cart.taxAmount),
                    _money('الإجمالي', cart.total, bold: true),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 10),
          HsPrimaryButton(
            label: 'إنشاء الطلب',
            onPressed:
                cart.lines.isEmpty ? null : () => widget.onCheckout(),
          ),
          const SizedBox(height: 6),
          HsOutlineButton(
            label: 'طلب خارجي',
            onPressed: cart.lines.isEmpty
                ? null
                : () {
                    notifier.setChannel(OrderChannel.takeaway);
                    widget.onCheckout();
                  },
          ),
        ],
      ),
    );
  }

  Widget _qtyBtn(String label, VoidCallback onTap) {
    return Material(
      color: HasimColors.surface,
      borderRadius: BorderRadius.circular(HasimRadius.sm),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(HasimRadius.sm),
        child: Container(
          constraints: const BoxConstraints(minWidth: 40, minHeight: 40),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            border: Border.all(color: HasimColors.border),
            borderRadius: BorderRadius.circular(HasimRadius.sm),
          ),
          child: Text(
            label,
            style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16),
          ),
        ),
      ),
    );
  }

  Widget _money(String label, double value, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 1),
      child: Row(
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: bold ? 13 : 11,
              fontWeight: bold ? FontWeight.w900 : FontWeight.w600,
            ),
          ),
          const Spacer(),
          Text(
            value.toStringAsFixed(2),
            style: TextStyle(
              fontSize: bold ? 13 : 11,
              fontWeight: bold ? FontWeight.w900 : FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _OrderTypeChip extends StatelessWidget {
  const _OrderTypeChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? HasimColors.cta : HasimColors.surface,
      borderRadius: BorderRadius.circular(HasimRadius.sm),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(HasimRadius.sm),
        child: Container(
          alignment: Alignment.center,
          padding: const EdgeInsets.symmetric(vertical: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(HasimRadius.sm),
            border: Border.all(
              color: selected ? HasimColors.cta : HasimColors.border,
            ),
          ),
          child: Text(
            label,
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              color: selected ? Colors.white : HasimColors.ink,
            ),
          ),
        ),
      ),
    );
  }
}

class _SuccessOrderDialog extends StatelessWidget {
  const _SuccessOrderDialog({
    required this.orderNumber,
    required this.onPrint,
    required this.onContinue,
  });

  final String orderNumber;
  final VoidCallback onPrint;
  final VoidCallback onContinue;

  @override
  Widget build(BuildContext context) {
    return Dialog(
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(HasimRadius.lg),
      ),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: const BoxDecoration(
                color: HasimColors.ctaSoft,
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.check, color: HasimColors.cta),
            ),
            const SizedBox(height: 12),
            const Text(
              'تم إنشاء الطلب بنجاح',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 4),
            Text(
              'رقم الطلب: #$orderNumber',
              style: const TextStyle(color: HasimColors.muted),
            ),
            const SizedBox(height: 18),
            HsPrimaryButton(label: 'طباعة الفاتورة', onPressed: onPrint),
            const SizedBox(height: 8),
            HsOutlineButton(
              label: 'بدون فاتورة',
              onPressed: onContinue,
            ),
          ],
        ),
      ),
    );
  }
}
