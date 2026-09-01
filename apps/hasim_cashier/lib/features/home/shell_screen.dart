import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:uuid/uuid.dart';

import '../../core/api/cashier_api.dart';
import '../../core/auth/auth_controller.dart';
import '../../core/offline/offline_store.dart';
import '../../core/offline/sync_engine.dart';
import '../../core/theme/cashier_theme.dart';
import '../cart/cart_controller.dart';

class ShellScreen extends ConsumerStatefulWidget {
  const ShellScreen({super.key});

  @override
  ConsumerState<ShellScreen> createState() => _ShellScreenState();
}

class _ShellScreenState extends ConsumerState<ShellScreen> {
  var _tab = 0;
  var _selectedCategoryId = 0; // 0 = all
  final _search = TextEditingController();
  String? _bootstrapError;
  Map<String, dynamic>? _bootstrap;

  @override
  void initState() {
    super.initState();
    _loadBootstrap();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
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
      setState(() => _bootstrap = data);
      await ref.read(syncEngineProvider).flushPendingOrders();
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
    final isWide = width >= 900;
    final cart = ref.watch(cartControllerProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('كاشير حاسم'),
        actions: [
          IconButton(
            tooltip: 'مزامنة',
            onPressed: () async {
              final n = await ref.read(syncEngineProvider).flushPendingOrders();
              if (!context.mounted) return;
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text('تمت مزامنة $n طلبات معلّقة')),
              );
            },
            icon: const Icon(Icons.sync),
          ),
          IconButton(
            tooltip: 'خروج',
            onPressed: () async {
              await ref.read(authControllerProvider.notifier).logout();
              if (context.mounted) context.go('/login');
            },
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: _bootstrapError != null && _bootstrap == null
          ? Center(child: Text(_bootstrapError!))
          : Row(
              children: [
                Expanded(
                  flex: isWide ? 3 : 1,
                  child: IndexedStack(
                    index: _tab,
                    children: [
                      _ProductsTab(
                        search: _search,
                        selectedCategoryId: _selectedCategoryId,
                        onCategory: (id) =>
                            setState(() => _selectedCategoryId = id),
                        onSearchChanged: () => setState(() {}),
                      ),
                      const _OrdersTab(),
                      const _TablesTab(),
                      const _MenuFeedTab(),
                    ],
                  ),
                ),
                if (isWide)
                  SizedBox(
                    width: 360,
                    child: _CartPanel(
                      cart: cart,
                      onCheckout: _checkout,
                    ),
                  ),
              ],
            ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.grid_view_rounded),
            label: 'المنتجات',
          ),
          NavigationDestination(
            icon: Icon(Icons.receipt_long_outlined),
            label: 'الطلبات',
          ),
          NavigationDestination(
            icon: Icon(Icons.table_restaurant_outlined),
            label: 'الطاولات',
          ),
          NavigationDestination(
            icon: Icon(Icons.qr_code_2_outlined),
            label: 'المنيو',
          ),
        ],
      ),
      floatingActionButton: isWide
          ? null
          : FloatingActionButton.extended(
              onPressed: () => _openCartSheet(context, cart),
              backgroundColor: CashierTheme.brand,
              label: Text('السلة (${cart.lines.length})'),
              icon: const Icon(Icons.shopping_bag_outlined),
            ),
    );
  }

  Future<void> _openCartSheet(BuildContext context, CartState cart) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: SizedBox(
          height: MediaQuery.sizeOf(context).height * 0.75,
          child: _CartPanel(cart: cart, onCheckout: _checkout),
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
    final payload =
        ref.read(cartControllerProvider.notifier).toOrderPayload(
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
        builder: (context) => AlertDialog(
          title: const Text('تم إنشاء الطلب بنجاح'),
          content: Text('رقم الطلب: ${data['order_number'] ?? data['id']}'),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('بدون فاتورة'),
            ),
            FilledButton(
              onPressed: () async {
                Navigator.pop(context);
                try {
                  await ref.read(cashierApiProvider).post(
                        '/orders/${data['id']}/invoice',
                      );
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
              child: const Text('طباعة الفاتورة'),
            ),
          ],
        ),
      );
    } on ApiException catch (e) {
      if (e.statusCode == 0) {
        await OfflineStore.instance.enqueueOrder(payload);
        ref.read(cartControllerProvider.notifier).clear();
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

class _ProductsTab extends ConsumerWidget {
  const _ProductsTab({
    required this.search,
    required this.selectedCategoryId,
    required this.onCategory,
    required this.onSearchChanged,
  });

  final TextEditingController search;
  final int selectedCategoryId;
  final ValueChanged<int> onCategory;
  final VoidCallback onSearchChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final categories = ref.watch(categoriesProvider);
    final items = ref.watch(catalogItemsProvider);
    final width = MediaQuery.sizeOf(context).width;
    final crossAxis = width >= 1200
        ? 5
        : width >= 800
            ? 4
            : width >= 500
                ? 3
                : 2;

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
          child: TextField(
            controller: search,
            decoration: const InputDecoration(
              prefixIcon: Icon(Icons.search),
              hintText: 'بحث بالاسم / SKU / باركود',
            ),
            onChanged: (_) => onSearchChanged(),
          ),
        ),
        SizedBox(
          height: 48,
          child: categories.when(
            data: (list) {
              final chips = [
                {'id': 0, 'name': 'الكل'},
                ...list,
              ];
              return ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                itemCount: chips.length,
                separatorBuilder: (_, __) => const SizedBox(width: 8),
                itemBuilder: (context, index) {
                  final cat = chips[index];
                  final id = (cat['id'] as num?)?.toInt() ?? 0;
                  final selected = id == selectedCategoryId;
                  return ChoiceChip(
                    label: Text((cat['name'] as String?) ?? ''),
                    selected: selected,
                    onSelected: (_) => onCategory(id),
                  );
                },
              );
            },
            loading: () => const Center(child: LinearProgressIndicator()),
            error: (e, _) => Center(child: Text('$e')),
          ),
        ),
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
                if (offline.isNotEmpty && list.isEmpty) {
                  return _ProductGrid(
                    items: offline,
                    crossAxis: crossAxis,
                  );
                }
                return const Center(child: Text('لا توجد منتجات'));
              }

              OfflineStore.instance.cacheCatalog(list);
              return _ProductGrid(items: filtered, crossAxis: crossAxis);
            },
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) {
              final offline = OfflineStore.instance.readCatalog();
              if (offline.isNotEmpty) {
                return _ProductGrid(items: offline, crossAxis: crossAxis);
              }
              return Center(child: Text('$e'));
            },
          ),
        ),
      ],
    );
  }
}

class _ProductGrid extends ConsumerWidget {
  const _ProductGrid({required this.items, required this.crossAxis});

  final List<Map<String, dynamic>> items;
  final int crossAxis;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return GridView.builder(
      padding: const EdgeInsets.all(12),
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: crossAxis,
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        childAspectRatio: 0.86,
      ),
      itemCount: items.length,
      itemBuilder: (context, index) {
        final item = items[index];
        final name = (item['name'] as String?) ?? '';
        final price = (item['price'] as num?)?.toDouble() ?? 0;
        final image = item['image_url'] as String?;
        final id = (item['id'] as num?)?.toInt() ?? 0;

        return Material(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          child: InkWell(
            borderRadius: BorderRadius.circular(14),
            onTap: () {
              ref.read(cartControllerProvider.notifier).addItem(
                    menuItemId: id,
                    name: name,
                    unitPrice: price,
                  );
            },
            child: Ink(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Expanded(
                    child: ClipRRect(
                      borderRadius: const BorderRadius.vertical(
                        top: Radius.circular(14),
                      ),
                      child: image == null || image.isEmpty
                          ? Container(
                              color: const Color(0xFFF1F5F9),
                              child: const Icon(Icons.fastfood_outlined),
                            )
                          : CachedNetworkImage(
                              imageUrl: image,
                              fit: BoxFit.cover,
                            ),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.all(8),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                        Text(
                          '${price.toStringAsFixed(2)}',
                          style: const TextStyle(
                            color: CashierTheme.brandDark,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ).animate().fadeIn(duration: 200.ms);
      },
    );
  }
}

class _CartPanel extends ConsumerWidget {
  const _CartPanel({required this.cart, required this.onCheckout});

  final CartState cart;
  final Future<void> Function() onCheckout;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notifier = ref.read(cartControllerProvider.notifier);
    return Material(
      color: Colors.white,
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              children: [
                Text(
                  'السلة',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                ),
                const Spacer(),
                DropdownButton<OrderChannel>(
                  value: cart.channel,
                  items: const [
                    DropdownMenuItem(
                      value: OrderChannel.table,
                      child: Text('طاولة'),
                    ),
                    DropdownMenuItem(
                      value: OrderChannel.takeaway,
                      child: Text('طلب خارجي'),
                    ),
                    DropdownMenuItem(
                      value: OrderChannel.delivery,
                      child: Text('توصيل'),
                    ),
                  ],
                  onChanged: (v) {
                    if (v != null) notifier.setChannel(v);
                  },
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: cart.lines.isEmpty
                ? const Center(child: Text('السلة فارغة'))
                : ListView.builder(
                    itemCount: cart.lines.length,
                    itemBuilder: (context, index) {
                      final line = cart.lines[index];
                      return ListTile(
                        title: Text(line.name),
                        subtitle: Text(line.unitPrice.toStringAsFixed(2)),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            IconButton(
                              onPressed: () => notifier.setQuantity(
                                line.menuItemId,
                                line.quantity - 1,
                              ),
                              icon: const Icon(Icons.remove_circle_outline),
                            ),
                            Text('${line.quantity}'),
                            IconButton(
                              onPressed: () => notifier.setQuantity(
                                line.menuItemId,
                                line.quantity + 1,
                              ),
                              icon: const Icon(Icons.add_circle_outline),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
          ),
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              children: [
                _moneyRow('المجموع الفرعي', cart.subtotal),
                _moneyRow('الخصم', cart.discountAmount),
                _moneyRow('الضريبة', cart.taxAmount),
                _moneyRow('الإجمالي', cart.total, bold: true),
                const SizedBox(height: 8),
                FilledButton(
                  onPressed: cart.lines.isEmpty ? null : onCheckout,
                  child: const Text('إنشاء الطلب'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _moneyRow(String label, double value, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        children: [
          Text(
            label,
            style: TextStyle(
              fontWeight: bold ? FontWeight.w900 : FontWeight.w600,
            ),
          ),
          const Spacer(),
          Text(
            value.toStringAsFixed(2),
            style: TextStyle(
              fontWeight: bold ? FontWeight.w900 : FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _OrdersTab extends ConsumerStatefulWidget {
  const _OrdersTab();

  @override
  ConsumerState<_OrdersTab> createState() => _OrdersTabState();
}

class _OrdersTabState extends ConsumerState<_OrdersTab> {
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
      final data =
          await ref.read(cashierApiProvider).get('/orders', query: {'status': 'running'});
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

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_orders.isEmpty) {
      return const Center(child: Text('لا توجد طلبات جارية'));
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.all(12),
        itemCount: _orders.length,
        separatorBuilder: (_, __) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final order = _orders[index];
          return ListTile(
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
              side: const BorderSide(color: Color(0xFFE2E8F0)),
            ),
            title: Text(
              '#${order['order_number'] ?? order['id']}',
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
            subtitle: Text(
              '${order['order_type'] ?? ''} · ${order['pos_status'] ?? ''}',
            ),
            trailing: Text(
              ((order['total_amount'] as num?) ?? 0).toStringAsFixed(2),
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
          );
        },
      ),
    );
  }
}

class _TablesTab extends ConsumerStatefulWidget {
  const _TablesTab();

  @override
  ConsumerState<_TablesTab> createState() => _TablesTabState();
}

class _TablesTabState extends ConsumerState<_TablesTab> {
  List<Map<String, dynamic>> _tables = const [];
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
      });
    } catch (_) {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_tables.isEmpty) {
      return const Center(child: Text('لا توجد طاولات'));
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: GridView.builder(
        padding: const EdgeInsets.all(12),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          mainAxisSpacing: 10,
          crossAxisSpacing: 10,
          childAspectRatio: 1.2,
        ),
        itemCount: _tables.length,
        itemBuilder: (context, index) {
          final table = _tables[index];
          final occupied = table['status'] == 'occupied';
          return InkWell(
            onTap: () {
              final id = (table['id'] as num?)?.toInt();
              if (id == null) return;
              ref.read(cartControllerProvider.notifier).setChannel(OrderChannel.table);
              ref.read(cartControllerProvider.notifier).setTable(id);
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text('تم اختيار ${table['name']}')),
              );
            },
            child: Ink(
              decoration: BoxDecoration(
                color: occupied
                    ? const Color(0xFFECFDF5)
                    : Colors.white,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  color: occupied
                      ? CashierTheme.brand
                      : const Color(0xFFE2E8F0),
                ),
              ),
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    (table['name'] as String?) ?? '',
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                  const Spacer(),
                  Text(occupied ? 'مشغولة' : 'فارغة'),
                  Text(
                    ((table['total'] as num?) ?? 0).toStringAsFixed(2),
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

class _MenuFeedTab extends ConsumerStatefulWidget {
  const _MenuFeedTab();

  @override
  ConsumerState<_MenuFeedTab> createState() => _MenuFeedTabState();
}

class _MenuFeedTabState extends ConsumerState<_MenuFeedTab> {
  List<Map<String, dynamic>> _orders = const [];

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
        if (mounted) setState(() => _orders = list);
      } catch (_) {}
      await Future<void>.delayed(const Duration(seconds: 3));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_orders.isEmpty) {
      return const Center(child: Text('لا توجد طلبات منيو حالياً'));
    }
    return ListView.separated(
      padding: const EdgeInsets.all(12),
      itemCount: _orders.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (context, index) {
        final order = _orders[index];
        return ListTile(
          tileColor: const Color(0xFFFFFBEB),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
            side: const BorderSide(color: Color(0xFFFDE68A)),
          ),
          title: Text(
            'طلب جديد #${order['order_number'] ?? order['id']}',
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
          subtitle: Text(
            'طاولة: ${order['table']?['name'] ?? '—'} · ${order['created_at'] ?? ''}',
          ),
        );
      },
    );
  }
}
