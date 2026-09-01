import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:uuid/uuid.dart';

import '../../core/api/cashier_api.dart';
import '../../core/auth/auth_controller.dart';
import '../../core/offline/offline_store.dart';
import '../../core/offline/sync_engine.dart';
import '../../core/theme/hasim_colors.dart';
import '../../core/theme/hasim_radius.dart';
import '../../core/theme/hasim_spacing.dart';
import '../../core/widgets/hasim_widgets.dart';
import '../cart/cart_controller.dart';

enum _PosSection { cashier, tables, orders, menu }

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
  Map<String, dynamic> _channelStats = const {};
  var _online = true;
  var _pendingSync = 0;
  String? _bootstrapError;

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
    _setOnline(!_isOffline(result));
    Connectivity().onConnectivityChanged.listen((event) {
      _setOnline(!_isOffline(event));
      if (_online) {
        ref.read(syncEngineProvider).flushPendingOrders().then((_) {
          _refreshPending();
        });
      }
    });
  }

  bool _isOffline(List<ConnectivityResult> results) {
    return results.isEmpty ||
        results.every((r) => r == ConnectivityResult.none);
  }

  void _setOnline(bool value) {
    if (!mounted) return;
    setState(() => _online = value);
  }

  void _refreshPending() {
    if (!mounted) return;
    setState(() {
      _pendingSync = OfflineStore.instance.pendingOrders().length;
    });
  }

  Future<void> _loadBootstrap() async {
    try {
      final data = await ref.read(cashierApiProvider).get('/bootstrap');
      if (!mounted) return;
      if (data['pos_enabled'] != true) {
        context.go('/pos-blocked');
        return;
      }
      final settings = data['settings'];
      if (settings is Map && settings['tax_rate'] != null) {
        ref
            .read(cartControllerProvider.notifier)
            .setTaxRate((settings['tax_rate'] as num).toDouble());
      }
      setState(() {
        _bootstrap = data;
        _channelStats = data['channel_stats'] is Map
            ? Map<String, dynamic>.from(data['channel_stats'] as Map)
            : {};
      });
      await ref.read(syncEngineProvider).flushPendingOrders();
      _refreshPending();
    } on ApiException catch (e) {
      if (e.statusCode == 403) {
        if (mounted) context.go('/pos-blocked');
        return;
      }
      setState(() => _bootstrapError = e.message);
    } catch (e) {
      setState(() => _bootstrapError = e.toString());
    }
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final isDesktop = width >= 1100;
    final isTablet = width >= 800 && width < 1100;
    final cart = ref.watch(cartControllerProvider);
    final session = ref.watch(authControllerProvider).valueOrNull;
    final workspaceName =
        (session?.workspace?['name'] as String?) ?? 'مساحة العمل';

    return Scaffold(
      body: Column(
        children: [
          _TopHeader(
            workspaceName: workspaceName,
            cartCount: cart.lines.fold<int>(0, (s, l) => s + l.quantity),
            online: _online,
            onCart: isDesktop
                ? null
                : () => _openCartSheet(context),
            onLogout: () async {
              await ref.read(authControllerProvider.notifier).logout();
              if (context.mounted) context.go('/login');
            },
            onSync: () async {
              final n =
                  await ref.read(syncEngineProvider).flushPendingOrders();
              _refreshPending();
              if (!context.mounted) return;
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text('تمت مزامنة $n طلبات')),
              );
            },
          ),
          _TopNav(
            section: _section,
            onSelect: (s) => setState(() => _section = s),
          ),
          ConnectionBanner(online: _online, pendingCount: _pendingSync),
          Expanded(
            child: _bootstrapError != null && _bootstrap == null
                ? Center(
                    child: HsEmpty(
                      title: 'تعذر تحميل الكاشير',
                      subtitle: _bootstrapError,
                      actionLabel: 'إعادة المحاولة',
                      onAction: _loadBootstrap,
                    ),
                  )
                : switch (_section) {
                    _PosSection.cashier => _CashierHome(
                        isDesktop: isDesktop,
                        isTablet: isTablet,
                        search: _search,
                        selectedCategoryId: _selectedCategoryId,
                        channelStats: _channelStats,
                        onCategory: (id) =>
                            setState(() => _selectedCategoryId = id),
                        onSearchChanged: () => setState(() {}),
                        onCheckout: _checkout,
                        onOpenMobileCart: () => _openCartSheet(context),
                      ),
                    _PosSection.tables => const _TablesBoard(),
                    _PosSection.orders => const _OrdersList(),
                    _PosSection.menu => const _MenuOrdersFeed(),
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
    if (cart.channel == OrderChannel.table && cart.tableId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('اختر طاولة لطلب الطاولة.')),
      );
      return;
    }

    final clientRef = const Uuid().v4();
    final payload = ref.read(cartControllerProvider.notifier).toOrderPayload(
          clientReference: clientRef,
        );

    try {
      final data = await ref.read(cashierApiProvider).post(
            '/orders',
            data: payload,
            idempotencyKey: clientRef,
          );
      ref.read(cartControllerProvider.notifier).clear();
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        barrierDismissible: false,
        builder: (context) => _SuccessOrderDialog(
          orderNumber: '${data['order_number'] ?? data['id']}',
          onPrint: () async {
            Navigator.pop(context);
            try {
              await ref
                  .read(cashierApiProvider)
                  .post('/orders/${data['id']}/invoice');
              if (!context.mounted) return;
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('تم إنشاء الفاتورة')),
              );
            } on ApiException catch (e) {
              if (!context.mounted) return;
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text(e.message)),
              );
            }
          },
          onContinue: () => Navigator.pop(context),
        ),
      );
    } on ApiException catch (e) {
      if (e.statusCode == 0) {
        await OfflineStore.instance.enqueueOrder(payload);
        ref.read(cartControllerProvider.notifier).clear();
        _refreshPending();
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message)),
        );
        return;
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    }
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

class _TopNav extends StatelessWidget {
  const _TopNav({required this.section, required this.onSelect});

  final _PosSection section;
  final ValueChanged<_PosSection> onSelect;

  @override
  Widget build(BuildContext context) {
    final items = <(_PosSection, String)>[
      (_PosSection.cashier, 'الكاشير'),
      (_PosSection.tables, 'الطاولات'),
      (_PosSection.menu, 'Menu'),
      (_PosSection.orders, 'الطلبات'),
    ];
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
              HsNavPill(
                label: item.$2,
                selected: section == item.$1,
                onTap: () => onSelect(item.$1),
              ),
              const SizedBox(width: 6),
            ],
          ],
        ),
      ),
    );
  }
}

class _ChannelStats extends StatelessWidget {
  const _ChannelStats({required this.stats});

  final Map<String, dynamic> stats;

  int _n(String key) => (stats[key] as num?)?.toInt() ?? 0;

  @override
  Widget build(BuildContext context) {
    final cards = [
      ('اليوم · داخل المطعم (طاولة)', _n('table'), _n('open_table'), false),
      ('اليوم · طلب خارجي', _n('takeaway'), _n('open_takeaway'), false),
      ('اليوم · توصيل', _n('delivery'), _n('open_delivery'), false),
      ('إجمالي طلبات اليوم', _n('total'), _n('open_total'), true),
    ];
    return LayoutBuilder(
      builder: (context, c) {
        final cols = c.maxWidth >= 900
            ? 4
            : c.maxWidth >= 520
                ? 2
                : 1;
        return GridView.count(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          crossAxisCount: cols,
          mainAxisSpacing: 8,
          crossAxisSpacing: 8,
          childAspectRatio: 2.6,
          children: [
            for (final card in cards)
              HsCard(
                color: card.$4 ? const Color(0xFFECFDF5) : HasimColors.surface,
                borderColor:
                    card.$4 ? const Color(0xFFA7F3D0) : HasimColors.border,
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      card.$1,
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: card.$4
                            ? HasimColors.ctaDark
                            : HasimColors.muted,
                      ),
                    ),
                    Text(
                      '${card.$2}',
                      style: TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                        color: card.$4
                            ? const Color(0xFF065F46)
                            : HasimColors.ink,
                      ),
                    ),
                    Text(
                      'مفتوحة الآن: ${card.$3}',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: card.$4
                            ? HasimColors.ctaDark
                            : HasimColors.muted,
                      ),
                    ),
                  ],
                ),
              ),
          ],
        );
      },
    );
  }
}

class _CashierHome extends ConsumerWidget {
  const _CashierHome({
    required this.isDesktop,
    required this.isTablet,
    required this.search,
    required this.selectedCategoryId,
    required this.channelStats,
    required this.onCategory,
    required this.onSearchChanged,
    required this.onCheckout,
    required this.onOpenMobileCart,
  });

  final bool isDesktop;
  final bool isTablet;
  final TextEditingController search;
  final int selectedCategoryId;
  final Map<String, dynamic> channelStats;
  final ValueChanged<int> onCategory;
  final VoidCallback onSearchChanged;
  final Future<void> Function() onCheckout;
  final VoidCallback onOpenMobileCart;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final categories = ref.watch(categoriesProvider);
    final items = ref.watch(catalogItemsProvider);

    return ListView(
      padding: const EdgeInsets.all(HasimSpacing.md),
      children: [
        _ChannelStats(stats: channelStats),
        const SizedBox(height: HasimSpacing.md),
        if (isDesktop || isTablet)
          SizedBox(
            height: MediaQuery.sizeOf(context).height - 220,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // RTL: first = RIGHT = categories
                if (isDesktop)
                  SizedBox(
                    width: 200,
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
                if (isDesktop) const SizedBox(width: 12),
                Expanded(
                  flex: 7,
                  child: HsCard(
                    child: _ProductsPanel(
                      search: search,
                      selectedCategoryId: selectedCategoryId,
                      onCategory: onCategory,
                      onSearchChanged: onSearchChanged,
                      showMobileCategories: !isDesktop,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                SizedBox(
                  width: isDesktop ? 300 : 280,
                  child: _CartPanel(onCheckout: onCheckout),
                ),
              ],
            ),
          )
        else ...[
          categories.when(
            data: (list) => SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
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
            error: (e, _) => Text('$e'),
          ),
          const SizedBox(height: 8),
          HsCard(
            child: _ProductsPanel(
              search: search,
              selectedCategoryId: selectedCategoryId,
              onCategory: onCategory,
              onSearchChanged: onSearchChanged,
              showMobileCategories: false,
            ),
          ),
        ],
      ],
    );
  }

  Widget _chip(String label, bool selected, VoidCallback onTap) {
    return Padding(
      padding: const EdgeInsetsDirectional.only(end: 6),
      child: Material(
        color: selected ? HasimColors.brand : HasimColors.surface,
        borderRadius: BorderRadius.circular(HasimRadius.sm),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(HasimRadius.sm),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(HasimRadius.sm),
              border: Border.all(
                color: selected ? HasimColors.brand : HasimColors.border,
              ),
            ),
            child: Text(
              label,
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
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
    final crossAxis = width >= 1400
        ? 5
        : width >= 1100
            ? 4
            : width >= 700
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
                final offline = OfflineStore.instance.readCatalog();
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
              OfflineStore.instance.cacheCatalog(list);
              return _grid(ref, filtered, crossAxis);
            },
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) {
              final offline = OfflineStore.instance.readCatalog();
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
        mainAxisSpacing: 8,
        crossAxisSpacing: 8,
        childAspectRatio: 0.78,
      ),
      itemCount: items.length,
      itemBuilder: (context, index) {
        final item = items[index];
        final id = (item['id'] as num?)?.toInt() ?? 0;
        final name = (item['name'] as String?) ?? '';
        final price = (item['price'] as num?)?.toDouble() ?? 0;
        return ProductCard(
          name: name,
          priceLabel: price.toStringAsFixed(2),
          currency: (item['currency'] as String?) ?? 'SAR',
          imageUrl: item['image_url'] as String?,
          onAdd: () {
            ref.read(cartControllerProvider.notifier).addItem(
                  menuItemId: id,
                  name: name,
                  unitPrice: price,
                );
          },
        ).animate().fadeIn(duration: 160.ms);
      },
    );
  }
}

class _CartPanel extends ConsumerWidget {
  const _CartPanel({required this.onCheckout});

  final Future<void> Function() onCheckout;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cart = ref.watch(cartControllerProvider);
    final notifier = ref.read(cartControllerProvider.notifier);

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
          const SizedBox(height: 10),
          TextField(
            decoration: const InputDecoration(
              labelText: 'الخصم (مبلغ)',
              isDense: true,
            ),
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            onChanged: (v) => notifier.setDiscount(double.tryParse(v) ?? 0),
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
            onPressed: cart.lines.isEmpty ? null : () => onCheckout(),
          ),
          const SizedBox(height: 6),
          HsOutlineButton(
            label: 'طلب خارجي',
            onPressed: cart.lines.isEmpty
                ? null
                : () {
                    notifier.setChannel(OrderChannel.takeaway);
                    onCheckout();
                  },
          ),
        ],
      ),
    );
  }

  Widget _qtyBtn(String label, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
        decoration: BoxDecoration(
          border: Border.all(color: HasimColors.border),
          borderRadius: BorderRadius.circular(4),
        ),
        child: Text(label, style: const TextStyle(fontWeight: FontWeight.w800)),
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
              label: 'متابعة بدون طباعة',
              onPressed: onContinue,
            ),
          ],
        ),
      ),
    );
  }
}

class _TablesBoard extends ConsumerStatefulWidget {
  const _TablesBoard();

  @override
  ConsumerState<_TablesBoard> createState() => _TablesBoardState();
}

class _TablesBoardState extends ConsumerState<_TablesBoard> {
  List<Map<String, dynamic>> _tables = const [];
  Map<String, dynamic>? _selected;
  var _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final data = await ref.read(cashierApiProvider).get('/tables');
      final list = <Map<String, dynamic>>[];
      if (data['tables'] is List) {
        for (final item in data['tables'] as List) {
          if (item is Map) list.add(Map<String, dynamic>.from(item));
        }
      }
      setState(() {
        _tables = list;
        _loading = false;
        if (_selected != null) {
          _selected = list.cast<Map<String, dynamic>?>().firstWhere(
                (t) => t?['id'] == _selected!['id'],
                orElse: () => _selected,
              );
        }
      });
    } catch (_) {
      setState(() => _loading = false);
    }
  }

  String _statusLabel(String? status) => switch (status) {
        'occupied' => 'مشغولة',
        'reserved' => 'محجوزة',
        'cleaning' => 'تنظيف',
        'closed' => 'مغلقة',
        _ => 'فارغة',
      };

  List<Widget> _selectedLines(Map<String, dynamic> selected) {
    final raw = selected['lines'];
    if (raw is! List) return const [];
    return raw.whereType<Map>().map((line) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 6),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
          decoration: BoxDecoration(
            color: HasimColors.surfaceSoft,
            borderRadius: BorderRadius.circular(HasimRadius.sm),
          ),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  '${line['name']} × ${line['quantity']}',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              Text(
                ((line['total'] as num?) ?? 0).toStringAsFixed(2),
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ],
          ),
        ),
      );
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    final width = MediaQuery.sizeOf(context).width;
    final desktop = width >= 1000;

    final details = HsCard(
      child: _selected == null
          ? const Padding(
              padding: EdgeInsets.symmetric(vertical: 40),
              child: Center(
                child: Text(
                  'اختر طاولة لعرض تفاصيل طلباتها.',
                  style: TextStyle(color: HasimColors.muted, fontSize: 12),
                ),
              ),
            )
          : Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        '${_selected!['name']}',
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    _selected!['status'] == 'occupied'
                        ? HsBadge.occupied(_statusLabel(_selected!['status'] as String?))
                        : HsBadge.available(
                            _statusLabel(_selected!['status'] as String?),
                          ),
                  ],
                ),
                const SizedBox(height: 10),
                ..._selectedLines(_selected!),
                const Divider(),
                Row(
                  children: [
                    const Text(
                      'الإجمالي',
                      style: TextStyle(fontWeight: FontWeight.w900),
                    ),
                    const Spacer(),
                    Text(
                      ((_selected!['total'] as num?) ?? 0).toStringAsFixed(2),
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                HsPrimaryButton(
                  label: 'إضافة طلب لهذه الطاولة',
                  onPressed: () {
                    final id = (_selected!['id'] as num?)?.toInt();
                    if (id == null) return;
                    ref
                        .read(cartControllerProvider.notifier)
                        .setChannel(OrderChannel.table);
                    ref.read(cartControllerProvider.notifier).setTable(id);
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text('تم اختيار ${_selected!['name']}'),
                      ),
                    );
                  },
                ),
              ],
            ),
    );

    final grid = RefreshIndicator(
      onRefresh: _load,
      child: GridView.builder(
        padding: const EdgeInsets.all(4),
        gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: width >= 1200
              ? 3
              : width >= 700
                  ? 2
                  : 2,
          mainAxisSpacing: 8,
          crossAxisSpacing: 8,
          childAspectRatio: 1.35,
        ),
        itemCount: _tables.length,
        itemBuilder: (context, index) {
          final table = _tables[index];
          final selected = _selected?['id'] == table['id'];
          final occupied = table['status'] == 'occupied';
          return Material(
            color: Colors.white,
            borderRadius: BorderRadius.circular(HasimRadius.md),
            child: InkWell(
              borderRadius: BorderRadius.circular(HasimRadius.md),
              onTap: () => setState(() => _selected = table),
              child: Ink(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(HasimRadius.md),
                  border: Border.all(
                    color: selected ? HasimColors.cta : HasimColors.border,
                    width: selected ? 1.5 : 1,
                  ),
                ),
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${table['name']}',
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _statusLabel(table['status'] as String?),
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: occupied
                            ? HasimColors.occupied
                            : HasimColors.available,
                      ),
                    ),
                    const Spacer(),
                    Text(
                      ((table['total'] as num?) ?? 0).toStringAsFixed(2),
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );

    if (!desktop) {
      return Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          children: [
            Expanded(flex: 3, child: HsCard(child: grid)),
            const SizedBox(height: 8),
            Expanded(flex: 2, child: details),
          ],
        ),
      );
    }

    // RTL: details first (RIGHT), cards center
    return Padding(
      padding: const EdgeInsets.all(12),
      child: Row(
        children: [
          SizedBox(width: 300, child: details),
          const SizedBox(width: 12),
          Expanded(
            child: HsCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'الطاولات',
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 8),
                  Expanded(child: grid),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _OrdersList extends ConsumerStatefulWidget {
  const _OrdersList();

  @override
  ConsumerState<_OrdersList> createState() => _OrdersListState();
}

class _OrdersListState extends ConsumerState<_OrdersList> {
  List<Map<String, dynamic>> _orders = const [];
  var _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final data = await ref
          .read(cashierApiProvider)
          .get('/orders', query: {'status': 'running'});
      final list = <Map<String, dynamic>>[];
      if (data['orders'] is List) {
        for (final item in data['orders'] as List) {
          if (item is Map) list.add(Map<String, dynamic>.from(item));
        }
      }
      setState(() {
        _orders = list;
        _loading = false;
      });
    } catch (_) {
      setState(() => _loading = false);
    }
  }

  String _typeLabel(String? type) => switch (type) {
        'table' => 'طاولة',
        'delivery' => 'توصيل',
        _ => 'خارجي',
      };

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_orders.isEmpty) {
      return const Padding(
        padding: EdgeInsets.all(16),
        child: HsEmpty(title: 'لا توجد طلبات جارية حاليًا.'),
      );
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.all(12),
        itemCount: _orders.length,
        separatorBuilder: (_, __) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final order = _orders[index];
          return HsCard(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        '#${order['order_number'] ?? order['id']}',
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: HasimColors.navIdleBg,
                        borderRadius: BorderRadius.circular(HasimRadius.pill),
                      ),
                      child: Text(
                        '${order['pos_status'] ?? ''}',
                        style: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  '${_typeLabel(order['order_type'] as String?)} · ${order['table']?['name'] ?? '—'}',
                  style: const TextStyle(
                    fontSize: 11,
                    color: HasimColors.muted,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 8),
                Align(
                  alignment: AlignmentDirectional.centerEnd,
                  child: Text(
                    ((order['total_amount'] as num?) ?? 0).toStringAsFixed(2),
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _MenuOrdersFeed extends ConsumerStatefulWidget {
  const _MenuOrdersFeed();

  @override
  ConsumerState<_MenuOrdersFeed> createState() => _MenuOrdersFeedState();
}

class _MenuOrdersFeedState extends ConsumerState<_MenuOrdersFeed> {
  List<Map<String, dynamic>> _orders = const [];
  int? _lastSeenId;
  var _soundEnabled = true;

  @override
  void initState() {
    super.initState();
    _poll();
  }

  Future<void> _poll() async {
    while (mounted) {
      try {
        final data = await ref
            .read(cashierApiProvider)
            .get('/orders', query: {'status': 'menu'});
        final list = <Map<String, dynamic>>[];
        if (data['orders'] is List) {
          for (final item in data['orders'] as List) {
            if (item is Map) list.add(Map<String, dynamic>.from(item));
          }
        }
        if (list.isNotEmpty) {
          final newest = (list.first['id'] as num?)?.toInt();
          if (_lastSeenId != null &&
              newest != null &&
              newest != _lastSeenId &&
              _soundEnabled &&
              mounted) {
            final table = list.first['table']?['name'] ?? '—';
            final number = list.first['order_number'] ?? newest;
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('🛎️ طلب جديد من طاولة $table #$number'),
                duration: const Duration(milliseconds: 4200),
              ),
            );
          }
          _lastSeenId = newest;
        }
        if (mounted) setState(() => _orders = list);
      } catch (_) {}
      await Future<void>.delayed(const Duration(seconds: 3));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
          child: Row(
            children: [
              const Expanded(
                child: Text(
                  'طلبات المنيو',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
              FilterChip(
                label: Text(_soundEnabled ? 'الصوت: تشغيل' : 'الصوت: إيقاف'),
                selected: _soundEnabled,
                onSelected: (v) => setState(() => _soundEnabled = v),
              ),
            ],
          ),
        ),
        Expanded(
          child: _orders.isEmpty
              ? const Padding(
                  padding: EdgeInsets.all(16),
                  child: HsEmpty(title: 'لا توجد طلبات منيو حالياً'),
                )
              : ListView.separated(
                  padding: const EdgeInsets.all(12),
                  itemCount: _orders.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 8),
                  itemBuilder: (context, index) {
                    final order = _orders[index];
                    return HsCard(
                      color: HasimColors.warningSoft,
                      borderColor: const Color(0xFFFDE68A),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'طلب جديد #${order['order_number'] ?? order['id']}',
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'الطاولة: ${order['table']?['name'] ?? '—'}',
                            style: const TextStyle(fontSize: 12),
                          ),
                          Text(
                            '${order['created_at'] ?? ''}',
                            style: const TextStyle(
                              fontSize: 11,
                              color: HasimColors.muted,
                            ),
                          ),
                          if (order['items'] is List) ...[
                            const SizedBox(height: 8),
                            for (final item
                                in (order['items'] as List).whereType<Map>())
                              Text(
                                '${item['product_name']} × ${item['quantity']}',
                                style: const TextStyle(fontSize: 12),
                              ),
                          ],
                        ],
                      ),
                    );
                  },
                ),
        ),
      ],
    );
  }
}
