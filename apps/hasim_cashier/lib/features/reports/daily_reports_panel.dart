import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/api/cashier_api.dart';
import '../../core/auth/auth_controller.dart';
import '../../core/permissions/cashier_permissions.dart';
import '../../core/permissions/permissions_provider.dart';
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

  Map<String, dynamic> get _perms => CashierPermissions.resolve(
        ref.read(cashierPermissionsProvider),
        ref.read(authControllerProvider).valueOrNull?.permissions,
      );

  Future<void> _load() async {
    if (!CashierPermissions.canViewReports(_perms)) {
      setState(() {
        _loading = false;
        _error = null;
        _data = null;
      });
      return;
    }
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
    final perms = CashierPermissions.resolve(
      ref.watch(cashierPermissionsProvider),
      ref.watch(authControllerProvider).valueOrNull?.permissions,
    );
    if (!CashierPermissions.canViewReports(perms)) {
      return const Padding(
        padding: EdgeInsets.all(16),
        child: HsEmpty(
          title: 'غير مصرح بعرض التقارير',
          subtitle:
              'لا تملك صلاحية reports.view. تواصل مع مدير مساحة العمل.',
        ),
      );
    }
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
    final channels = _data?['channel_stats'] is Map
        ? Map<String, dynamic>.from(_data!['channel_stats'] as Map)
        : <String, dynamic>{};
    final top = _asMaps(_data?['top_items']);
    final byType = _asMaps(_data?['quantity_by_type']);
    final payments = _asMaps(_data?['payment_methods']);
    final invoices = _asMaps(_data?['invoices']);
    final byHour = _asMaps(_data?['sales_by_hour']);
    final customers = _asMaps(_data?['customer_summary']);

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'التقارير اليومية',
                      style:
                          TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
                    ),
                    Text(
                      'بيانات حقيقية من Laravel — نفس مصدر لوحة الويب',
                      style: TextStyle(fontSize: 11, color: HasimColors.muted),
                    ),
                  ],
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
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _metric(
                ((summary['invoice_sales_total'] as num?) ??
                        (summary['invoices_total'] as num?) ??
                        0)
                    .toStringAsFixed(2),
                'إجمالي المبيعات',
                highlight: true,
              ),
              _metric('${summary['invoices_count'] ?? 0}', 'فواتير'),
              _metric('${summary['orders_count'] ?? 0}', 'طلبات'),
              _metric('${summary['open_orders_count'] ?? 0}', 'مفتوحة'),
              _metric('${summary['completed_orders_count'] ?? 0}', 'مكتملة'),
              _metric('${summary['cancelled_orders_count'] ?? 0}', 'ملغاة'),
              _metric('${summary['paid_orders_count'] ?? 0}', 'مدفوعة'),
              _metric('${summary['unpaid_orders_count'] ?? 0}', 'غير مدفوعة'),
              _metric(
                ((summary['discount_total'] as num?) ?? 0).toStringAsFixed(2),
                'خصومات',
              ),
              _metric(
                ((summary['tax_total'] as num?) ?? 0).toStringAsFixed(2),
                'ضرائب',
              ),
              _metric(
                '${summary['table_orders_count'] ?? channels['table'] ?? 0}',
                'طاولات',
              ),
              _metric(
                '${summary['takeaway_orders_count'] ?? channels['takeaway'] ?? 0}',
                'خارجي',
              ),
              _metric(
                '${summary['delivery_orders_count'] ?? channels['delivery'] ?? 0}',
                'توصيل',
              ),
              _metric('${summary['total_quantity'] ?? 0}', 'كميات'),
            ],
          ),
          if (payments.isNotEmpty) ...[
            const SizedBox(height: 16),
            const Text(
              'طرق الدفع',
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            for (final row in payments)
              _rowCard(
                '${row['method']}',
                '× ${row['orders_count']}',
              ),
          ],
          if (byType.isNotEmpty) ...[
            const SizedBox(height: 16),
            const Text(
              'الكميات حسب النوع',
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            for (final row in byType)
              _rowCard(
                '${row['item_type']}',
                '× ${row['quantity']} · ${((row['sales'] as num?) ?? 0).toStringAsFixed(2)}',
              ),
          ],
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
              _rowCard(
                '${item['product_name']}',
                '× ${item['quantity']} · ${((item['sales'] as num?) ?? 0).toStringAsFixed(2)}',
              ),
          if (byHour.isNotEmpty) ...[
            const SizedBox(height: 16),
            const Text(
              'المبيعات حسب الساعة',
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            for (final row in byHour)
              _rowCard(
                '${row['hour']}',
                '${row['orders_count']} طلب · ${((row['total_sales'] as num?) ?? 0).toStringAsFixed(2)}',
              ),
          ],
          if (customers.isNotEmpty) ...[
            const SizedBox(height: 16),
            const Text(
              'ملخص العملاء',
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            for (final row in customers.take(15))
              _rowCard(
                '${row['customer_name']} · ${row['customer_phone']}',
                '${row['orders_count']} · ${((row['total_sales'] as num?) ?? 0).toStringAsFixed(2)}',
              ),
          ],
          if (invoices.isNotEmpty) ...[
            const SizedBox(height: 16),
            const Text(
              'فواتير اليوم',
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            for (final inv in invoices)
              _rowCard(
                '${inv['invoice_number']} · ${inv['table']?['name'] ?? '—'}',
                ((inv['total_amount'] as num?) ?? 0).toStringAsFixed(2),
                highlight: true,
              ),
          ],
        ],
      ),
    );
  }

  List<Map<String, dynamic>> _asMaps(dynamic raw) {
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }

  Widget _metric(String value, String label, {bool highlight = false}) {
    return SizedBox(
      width: 108,
      child: HsCard(
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
        child: Column(
          children: [
            Text(
              value,
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w900,
                color: highlight ? HasimColors.ctaDark : HasimColors.ink,
              ),
            ),
            Text(
              label,
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 11, color: HasimColors.muted),
            ),
          ],
        ),
      ),
    );
  }

  Widget _rowCard(String title, String trailing, {bool highlight = false}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: HsCard(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Expanded(
              child: Text(
                title,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
            Text(
              trailing,
              style: TextStyle(
                fontWeight: FontWeight.w900,
                color: highlight ? HasimColors.ctaDark : HasimColors.ink,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
