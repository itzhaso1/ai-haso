import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/cashier_api.dart';
import '../../core/pos/pos_labels.dart';
import '../../core/theme/hasim_colors.dart';
import '../../core/theme/hasim_radius.dart';
import '../../core/widgets/hasim_widgets.dart';
import 'table_detail_screen.dart';
import 'table_workspace.dart';

/// Tables board — tap a card to enter the full table workspace (no sidebar).
class TablesBoard extends ConsumerStatefulWidget {
  const TablesBoard({super.key});

  @override
  ConsumerState<TablesBoard> createState() => _TablesBoardState();
}

class _TablesBoardState extends ConsumerState<TablesBoard> {
  List<Map<String, dynamic>> _tables = const [];
  var _loading = true;
  String? _error;

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

  @override
  Widget build(BuildContext context) {
    final openId = ref.watch(openTableIdProvider);
    if (openId != null) {
      return TableDetailScreen(key: ValueKey('table-$openId'), tableId: openId);
    }

    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return Padding(
        padding: const EdgeInsets.all(16),
        child: HsEmpty(
          title: 'تعذر تحميل الطاولات',
          subtitle: _error,
          actionLabel: 'إعادة المحاولة',
          onAction: _load,
        ),
      );
    }

    final width = MediaQuery.sizeOf(context).width;
    final crossAxis = width >= 1100
        ? 5
        : width >= 800
            ? 4
            : width >= 520
                ? 3
                : 2;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
          child: Row(
            children: [
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'الطاولات',
                      style:
                          TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
                    ),
                    Text(
                      'اضغط على الطاولة للدخول إلى تفاصيلها وعملياتها',
                      style: TextStyle(fontSize: 11, color: HasimColors.muted),
                    ),
                  ],
                ),
              ),
              IconButton(
                onPressed: _load,
                icon: const Icon(Icons.refresh),
              ),
            ],
          ),
        ),
        Expanded(
          child: _tables.isEmpty
              ? const Padding(
                  padding: EdgeInsets.all(16),
                  child: HsEmpty(title: 'لا توجد طاولات بعد.'),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: GridView.builder(
                    padding: const EdgeInsets.fromLTRB(12, 4, 12, 16),
                    gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: crossAxis,
                      mainAxisSpacing: 10,
                      crossAxisSpacing: 10,
                      childAspectRatio: 1.15,
                    ),
                    itemCount: _tables.length,
                    itemBuilder: (context, index) =>
                        _tableCard(_tables[index]),
                  ),
                ),
        ),
      ],
    );
  }

  Widget _tableCard(Map<String, dynamic> table) {
    final occupied = table['status'] == 'occupied';
    final hasSession = table['session_id'] != null;
    final total = ((table['total'] as num?) ?? 0).toDouble();
    final orders = table['open_orders_count'] ?? table['orders_count'] ?? 0;
    final id = (table['id'] as num).toInt();

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(HasimRadius.md),
      child: InkWell(
        borderRadius: BorderRadius.circular(HasimRadius.md),
        onTap: () => openTableWorkspace(ref, id),
        child: Ink(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(HasimRadius.md),
            border: Border.all(
              color: occupied ? HasimColors.occupied : HasimColors.border,
              width: occupied ? 1.4 : 1,
            ),
          ),
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      '${table['name']}',
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  Icon(
                    Icons.chevron_left,
                    color: HasimColors.muted,
                    size: 20,
                  ),
                ],
              ),
              const SizedBox(height: 6),
              occupied
                  ? HsBadge.occupied(
                      PosLabels.tableStatus(table['status'] as String?),
                    )
                  : HsBadge.available(
                      PosLabels.tableStatus(table['status'] as String?),
                    ),
              const SizedBox(height: 6),
              Text(
                hasSession ? 'جلسة مفتوحة' : 'مغلقة',
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: hasSession ? HasimColors.ink : HasimColors.muted,
                ),
              ),
              const Spacer(),
              Text(
                'الطلبات: $orders',
                style: const TextStyle(fontSize: 12, color: HasimColors.muted),
              ),
              if (total > 0)
                Text(
                  'الإجمالي: ${total.toStringAsFixed(2)}',
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                    color: HasimColors.ctaDark,
                  ),
                ),
              const SizedBox(height: 4),
              Text(
                'اضغط للدخول',
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  color: HasimColors.brand,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
