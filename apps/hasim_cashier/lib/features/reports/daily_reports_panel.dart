import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/api/cashier_api.dart';
import '../../core/theme/hasim_colors.dart';
import '../../core/widgets/hasim_widgets.dart';

class DailyReportsPanel extends ConsumerStatefulWidget {
  const DailyReportsPanel({super.key});

  @override
  ConsumerState<DailyReportsPanel> createState() => _DailyReportsPanelState();
}

class _DailyReportsPanelState extends ConsumerState<DailyReportsPanel> {
  Map<String, dynamic>? _data;
  var _loading = true;
  String? _error;
  late DateTime _date;

  @override
  void initState() {
    super.initState();
    _date = DateTime.now();
    _load();
  }

  String get _q => DateFormat('yyyy-MM-dd').format(_date);

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ref
          .read(cashierApiProvider)
          .get('/reports/daily', query: {'date': _q});
      setState(() {
        _data = data;
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
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return Padding(
        padding: const EdgeInsets.all(16),
        child: HsEmpty(
          title: 'تعذر تحميل التقرير',
          subtitle: _error,
          actionLabel: 'إعادة المحاولة',
          onAction: _load,
        ),
      );
    }
    final summary = _data?['summary'] is Map
        ? Map<String, dynamic>.from(_data!['summary'] as Map)
        : <String, dynamic>{};
    final top = _data?['top_items'] is List
        ? (_data!['top_items'] as List).whereType<Map>()
        : const Iterable<Map>.empty();

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Row(
          children: [
            const Expanded(
              child: Text(
                'التقارير اليومية',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
              ),
            ),
            OutlinedButton(
              onPressed: () async {
                final picked = await showDatePicker(
                  context: context,
                  initialDate: _date,
                  firstDate: DateTime(2020),
                  lastDate: DateTime.now(),
                );
                if (picked == null) return;
                setState(() => _date = picked);
                await _load();
              },
              child: Text(_q),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: HsCard(
                child: Column(
                  children: [
                    Text(
                      '${summary['invoices_count'] ?? 0}',
                      style: const TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const Text('فواتير', style: TextStyle(color: HasimColors.muted)),
                  ],
                ),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: HsCard(
                child: Column(
                  children: [
                    Text(
                      ((summary['invoices_total'] as num?) ?? 0)
                          .toStringAsFixed(2),
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                        color: HasimColors.ctaDark,
                      ),
                    ),
                    const Text('إجمالي الفواتير',
                        style: TextStyle(color: HasimColors.muted)),
                  ],
                ),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: HsCard(
                child: Column(
                  children: [
                    Text(
                      '${summary['orders_count'] ?? 0}',
                      style: const TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const Text('طلبات', style: TextStyle(color: HasimColors.muted)),
                  ],
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        const Text(
          'الأصناف الأكثر مبيعًا',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 8),
        if (top.isEmpty)
          const HsEmpty(title: 'لا توجد مبيعات مغلقة لهذا اليوم.')
        else
          for (final item in top)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: HsCard(
                padding: const EdgeInsets.all(12),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        '${item['product_name']}',
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ),
                    Text('× ${item['quantity']}'),
                    const SizedBox(width: 12),
                    Text(
                      ((item['sales'] as num?) ?? 0).toStringAsFixed(2),
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                  ],
                ),
              ),
            ),
      ],
    );
  }
}
