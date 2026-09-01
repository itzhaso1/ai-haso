import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/cashier_api.dart';
import '../../core/permissions/cashier_permissions.dart';
import '../../core/permissions/permissions_provider.dart';
import '../../core/pos/pos_labels.dart';
import '../../core/theme/hasim_colors.dart';
import '../../core/theme/hasim_radius.dart';
import '../../core/widgets/hasim_widgets.dart';

/// Running POS orders — mirrors web running orders with filters/search.
class OrdersList extends ConsumerStatefulWidget {
  const OrdersList({super.key});

  @override
  ConsumerState<OrdersList> createState() => _OrdersListState();
}

class _OrdersListState extends ConsumerState<OrdersList> {
  List<Map<String, dynamic>> _orders = const [];
  var _loading = true;
  String? _error;
  final _search = TextEditingController();
  String _filter = 'all';

  static const _statusOptions = [
    'new',
    'accepted',
    'preparing',
    'ready',
    'delivered',
    'completed',
    'cancelled',
  ];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ref
          .read(cashierApiProvider)
          .get('/orders', query: {'status': 'running', 'per_page': 50});
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
    } on ApiException catch (e) {
      setState(() {
        _loading = false;
        _error = e.message;
      });
    } catch (e) {
      setState(() {
        _loading = false;
        _error = e.toString();
      });
    }
  }

  Future<void> _updateStatus(Map<String, dynamic> order, String status) async {
    final id = (order['id'] as num?)?.toInt();
    if (id == null) return;
    try {
      await ref.read(cashierApiProvider).post(
        '/orders/$id/status',
        data: {'pos_status': status},
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _createInvoice(Map<String, dynamic> order) async {
    final id = (order['id'] as num?)?.toInt();
    if (id == null) return;
    try {
      final data =
          await ref.read(cashierApiProvider).post('/orders/$id/invoice');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'تم إنشاء الفاتورة ${data['invoice_number'] ?? data['invoice_id']}',
          ),
        ),
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _returnOrder(Map<String, dynamic> order) async {
    final perms = ref.read(cashierPermissionsProvider);
    if (!CashierPermissions.canRefund(perms)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لا تملك صلاحية المرتجعات.')),
      );
      return;
    }
    final id = (order['id'] as num?)?.toInt();
    if (id == null) return;
    final items = order['items'] is List
        ? (order['items'] as List).whereType<Map>().toList()
        : <Map>[];
    if (items.isEmpty) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('مرتجع / استرداد'),
        content: Text(
          'سيتم تسجيل مرتجع لكل أصناف الطلب #${order['order_number'] ?? id} '
          'وتمييزه كمسترد مباشرة إن سمحت الصلاحية.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('تأكيد المرتجع'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(cashierApiProvider).post(
        '/orders/$id/returns',
        data: {
          'reason': 'مرتجع من تطبيق الكاشير',
          'mark_refunded': true,
          'items': [
            for (final item in items)
              {
                'order_item_id': item['id'],
                'qty': item['quantity'],
              },
          ],
        },
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم تسجيل المرتجع والاسترداد.')),
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  List<Map<String, dynamic>> get _filtered {
    final q = _search.text.trim().toLowerCase();
    return _orders.where((order) {
      final status = order['pos_status'] as String? ?? '';
      if (_filter == 'open' &&
          (order['payment_status'] == 'paid' || status == 'cancelled')) {
        return false;
      }
      if (_filter == 'paid' && order['payment_status'] != 'paid') return false;
      if (_filter == 'cancelled' && status != 'cancelled') return false;
      if (q.isEmpty) return true;
      final hay =
          '${order['order_number']}|${order['table']?['name']}|${order['customer']?['name']}|${order['pos_status']}'
              .toLowerCase();
      return hay.contains(q);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Padding(
        padding: const EdgeInsets.all(16),
        child: HsEmpty(
          title: 'تعذر تحميل الطلبات',
          subtitle: _error,
          actionLabel: 'إعادة المحاولة',
          onAction: _load,
        ),
      );
    }

    final filtered = _filtered;

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'طلبات POS / QR الجارية',
                style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: _search,
                onChanged: (_) => setState(() {}),
                decoration: const InputDecoration(
                  hintText: 'ابحث برقم الطلب / الطاولة / العميل…',
                  isDense: true,
                  prefixIcon: Icon(Icons.search, size: 18),
                ),
              ),
              const SizedBox(height: 8),
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: [
                    for (final f in [
                      ('all', 'الكل'),
                      ('open', 'مفتوحة'),
                      ('paid', 'مدفوعة'),
                      ('cancelled', 'ملغية'),
                    ])
                      Padding(
                        padding: const EdgeInsetsDirectional.only(end: 6),
                        child: FilterChip(
                          label: Text(f.$2),
                          selected: _filter == f.$1,
                          onSelected: (_) => setState(() => _filter = f.$1),
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: filtered.isEmpty
              ? const Padding(
                  padding: EdgeInsets.all(16),
                  child: HsEmpty(title: 'لا توجد طلبات جارية حاليًا.'),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView.separated(
                    padding: const EdgeInsets.all(12),
                    itemCount: filtered.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 8),
                    itemBuilder: (context, index) {
                      final order = filtered[index];
                      final items = order['items'] is List
                          ? (order['items'] as List).whereType<Map>()
                          : const Iterable<Map>.empty();
                      final current = order['pos_status'] as String? ?? 'new';
                      final paid = order['payment_status'] == 'paid';

                      return HsCard(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    '#${order['order_number'] ?? order['id']}',
                                    style: const TextStyle(
                                      fontSize: 14,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                ),
                                HsBadge(
                                  label: PosLabels.status(current),
                                  background: HasimColors.ctaSoft,
                                  foreground: HasimColors.ctaDark,
                                ),
                                const SizedBox(width: 6),
                                HsBadge(
                                  label: paid ? 'مدفوع' : 'غير مدفوع',
                                  background: paid
                                      ? HasimColors.navIdleBg
                                      : HasimColors.warningSoft,
                                  foreground: paid
                                      ? HasimColors.ink
                                      : HasimColors.warning,
                                ),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Text(
                              '${PosLabels.orderType(order['order_type'] as String?)}'
                              ' · الطاولة: ${order['table']?['name'] ?? '—'}'
                              ' · العميل: ${order['customer']?['name'] ?? '—'}',
                              style: const TextStyle(
                                fontSize: 12,
                                color: HasimColors.muted,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            Text(
                              'الوقت: ${order['placed_at'] ?? order['created_at'] ?? '—'} · المصدر: ${(order['source'] as String?)?.toUpperCase() ?? '—'}',
                              style: const TextStyle(
                                fontSize: 11,
                                color: HasimColors.muted,
                              ),
                            ),
                            if (items.isNotEmpty) ...[
                              const SizedBox(height: 8),
                              for (final item in items)
                                Padding(
                                  padding: const EdgeInsets.only(bottom: 2),
                                  child: Text(
                                    '${item['product_name']}'
                                    '${item['variant_name'] != null ? ' - ${item['variant_name']}' : ''}'
                                    ' × ${item['quantity']}'
                                    ' = ${((item['total_amount'] as num?) ?? 0).toStringAsFixed(2)}',
                                    style: const TextStyle(fontSize: 12),
                                  ),
                                ),
                            ],
                            if (order['notes'] != null &&
                                (order['notes'] as String).isNotEmpty) ...[
                              const SizedBox(height: 6),
                              Text(
                                'ملاحظات: ${order['notes']}',
                                style: const TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                            const SizedBox(height: 10),
            Row(
                              children: [
                                Expanded(
                                  child: Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 8,
                                    ),
                                    decoration: BoxDecoration(
                                      border: Border.all(
                                        color: HasimColors.border,
                                      ),
                                      borderRadius: BorderRadius.circular(
                                        HasimRadius.sm,
                                      ),
                                    ),
                                    child: DropdownButtonHideUnderline(
                                      child: DropdownButton<String>(
                                        isExpanded: true,
                                        value: _statusOptions.contains(current)
                                            ? current
                                            : 'new',
                                        items: [
                                          for (final s in _statusOptions)
                                            DropdownMenuItem(
                                              value: s,
                                              child: Text(
                                                PosLabels.status(s),
                                                style: const TextStyle(
                                                  fontSize: 12,
                                                ),
                                              ),
                                            ),
                                        ],
                                        onChanged: (v) {
                                          if (v != null) {
                                            _updateStatus(order, v);
                                          }
                                        },
                                      ),
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Text(
                                  ((order['total_amount'] as num?) ?? 0)
                                      .toStringAsFixed(2),
                                  style: const TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Wrap(
                              spacing: 6,
                              runSpacing: 6,
                              children: [
                                if (order['pos_cashier_invoice_id'] == null)
                                  OutlinedButton.icon(
                                    onPressed: () => _createInvoice(order),
                                    icon: const Icon(Icons.receipt_long, size: 16),
                                    label: const Text('فاتورة'),
                                  ),
                                if (CashierPermissions.canRefund(
                                  ref.watch(cashierPermissionsProvider),
                                ))
                                  OutlinedButton.icon(
                                    onPressed: () => _returnOrder(order),
                                    icon: const Icon(Icons.undo, size: 16),
                                    label: const Text('مرتجع'),
                                  ),
                              ],
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
        ),
      ],
    );
  }
}
