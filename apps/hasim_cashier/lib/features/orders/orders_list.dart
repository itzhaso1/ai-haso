import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/cashier_api.dart';
import '../../core/pos/pos_labels.dart';
import '../../core/theme/hasim_colors.dart';
import '../../core/theme/hasim_radius.dart';
import '../../core/widgets/hasim_widgets.dart';

/// Running POS orders — mirrors `workspace/pos/orders/running`.
class OrdersList extends ConsumerStatefulWidget {
  const OrdersList({super.key});

  @override
  ConsumerState<OrdersList> createState() => _OrdersListState();
}

class _OrdersListState extends ConsumerState<OrdersList> {
  List<Map<String, dynamic>> _orders = const [];
  var _loading = true;
  String? _error;

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

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
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
        separatorBuilder: (_, _) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final order = _orders[index];
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
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    HsBadge(
                      label: PosLabels.status(current),
                      background: HasimColors.navIdleBg,
                      foreground: HasimColors.ink,
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  '${PosLabels.orderType(order['order_type'] as String?)}'
                  ' · ${order['table']?['name'] ?? '—'}'
                  ' · ${paid ? 'مدفوع' : 'غير مدفوع'}'
                  ' · ${(order['source'] as String?)?.toUpperCase() ?? ''}',
                  style: const TextStyle(
                    fontSize: 11,
                    color: HasimColors.muted,
                    fontWeight: FontWeight.w600,
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
                        style: const TextStyle(fontSize: 12, color: HasimColors.muted),
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
                        padding: const EdgeInsets.symmetric(horizontal: 8),
                        decoration: BoxDecoration(
                          border: Border.all(color: HasimColors.border),
                          borderRadius: BorderRadius.circular(HasimRadius.sm),
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
                                    style: const TextStyle(fontSize: 12),
                                  ),
                                ),
                            ],
                            onChanged: (v) {
                              if (v != null) _updateStatus(order, v);
                            },
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Text(
                      ((order['total_amount'] as num?) ?? 0).toStringAsFixed(2),
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
