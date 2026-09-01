import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/api/cashier_api.dart';
import '../../core/printing/printer_service.dart';
import '../../core/theme/hasim_colors.dart';
import '../../core/widgets/hasim_widgets.dart';

/// Closed cashier invoices + full invoice detail / print / reprint.
class InvoicesList extends ConsumerStatefulWidget {
  const InvoicesList({super.key});

  @override
  ConsumerState<InvoicesList> createState() => _InvoicesListState();
}

class _InvoicesListState extends ConsumerState<InvoicesList> {
  List<Map<String, dynamic>> _invoices = const [];
  var _loading = true;
  String? _error;
  late DateTime _date;
  Map<String, dynamic>? _selected;
  String? _workspaceName;

  @override
  void initState() {
    super.initState();
    _date = DateTime.now();
    _load();
  }

  String get _dateQuery => DateFormat('yyyy-MM-dd').format(_date);

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
      _selected = null;
    });
    try {
      final boot = await ref.read(cashierApiProvider).get('/bootstrap');
      final data = await ref.read(cashierApiProvider).get(
        '/invoices',
        query: {'date': _dateQuery},
      );
      final list = <Map<String, dynamic>>[];
      if (data['invoices'] is List) {
        for (final item in data['invoices'] as List) {
          if (item is Map) list.add(Map<String, dynamic>.from(item));
        }
      }
      setState(() {
        _invoices = list;
        _loading = false;
        _workspaceName = boot['workspace'] is Map
            ? (boot['workspace']['name'] as String?)
            : null;
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

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 1)),
    );
    if (picked == null) return;
    setState(() => _date = picked);
    await _load();
  }

  Future<void> _openInvoice(Map<String, dynamic> invoice) async {
    final id = (invoice['id'] as num?)?.toInt();
    if (id == null) return;
    try {
      final data = await ref.read(cashierApiProvider).get('/invoices/$id');
      if (!mounted) return;
      final inv = data['invoice'] is Map
          ? Map<String, dynamic>.from(data['invoice'] as Map)
          : data;
      inv['store_name'] = _workspaceName ?? 'كاشير حاسم';
      setState(() => _selected = inv);
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _printSelected({required bool reprint}) async {
    final inv = _selected;
    if (inv == null) return;
    final printer = await ref.read(printerServiceFutureProvider.future);
    final result = await printer.printInvoice(inv);
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          reprint
              ? (result.success
                  ? 'تمت إعادة الطباعة.'
                  : result.message)
              : result.message,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
          child: Row(
            children: [
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'فواتير الكاشير المغلقة',
                      style:
                          TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'فاتورة مستقلة عن نظام الفوترة الخارجي.',
                      style: TextStyle(fontSize: 12, color: HasimColors.muted),
                    ),
                  ],
                ),
              ),
              OutlinedButton.icon(
                onPressed: _pickDate,
                icon: const Icon(Icons.calendar_today, size: 16),
                label: Text(_dateQuery),
              ),
            ],
          ),
        ),
        Expanded(
          child: _loading
              ? const Center(child: CircularProgressIndicator())
              : _error != null
                  ? Padding(
                      padding: const EdgeInsets.all(16),
                      child: HsEmpty(
                        title: 'تعذر تحميل الفواتير',
                        subtitle: _error,
                        actionLabel: 'إعادة المحاولة',
                        onAction: _load,
                      ),
                    )
                  : _invoices.isEmpty
                      ? const Padding(
                          padding: EdgeInsets.all(16),
                          child: HsEmpty(
                            title: 'لا توجد فواتير لهذا التاريخ.',
                          ),
                        )
                      : RefreshIndicator(
                          onRefresh: _load,
                          child: ListView.separated(
                            padding: const EdgeInsets.all(12),
                            itemCount: _invoices.length,
                            separatorBuilder: (_, _) =>
                                const SizedBox(height: 8),
                            itemBuilder: (context, index) {
                              final inv = _invoices[index];
                              return HsCard(
                                child: InkWell(
                                  onTap: () => _openInvoice(inv),
                                  child: Padding(
                                    padding: const EdgeInsets.symmetric(
                                      vertical: 4,
                                    ),
                                    child: Row(
                                      children: [
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            children: [
                                              Text(
                                                '${inv['invoice_number']}',
                                                style: const TextStyle(
                                                  fontWeight: FontWeight.w800,
                                                ),
                                              ),
                                              const SizedBox(height: 4),
                                              Text(
                                                'الطاولة: ${inv['table']?['name'] ?? '—'}'
                                                ' · ${inv['closer']?['name'] ?? '—'}',
                                                style: const TextStyle(
                                                  fontSize: 11,
                                                  color: HasimColors.muted,
                                                ),
                                              ),
                                              Text(
                                                '${inv['closed_at'] ?? ''}',
                                                style: const TextStyle(
                                                  fontSize: 11,
                                                  color: HasimColors.muted,
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                        Text(
                                          '${((inv['total_amount'] as num?) ?? 0).toStringAsFixed(2)} ${inv['currency'] ?? ''}',
                                          style: const TextStyle(
                                            fontWeight: FontWeight.w900,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
        ),
        if (_selected != null)
          Material(
            elevation: 10,
            color: Colors.white,
            child: SafeArea(
              top: false,
              child: ConstrainedBox(
                constraints: BoxConstraints(
                  maxHeight: MediaQuery.sizeOf(context).height * 0.55,
                ),
                child: _InvoiceDetail(
                  invoice: _selected!,
                  onClose: () => setState(() => _selected = null),
                  onPrint: () => _printSelected(reprint: false),
                  onReprint: () => _printSelected(reprint: true),
                ),
              ),
            ),
          ),
      ],
    );
  }
}

class _InvoiceDetail extends StatelessWidget {
  const _InvoiceDetail({
    required this.invoice,
    required this.onClose,
    required this.onPrint,
    required this.onReprint,
  });

  final Map<String, dynamic> invoice;
  final VoidCallback onClose;
  final VoidCallback onPrint;
  final VoidCallback onReprint;

  @override
  Widget build(BuildContext context) {
    final items = invoice['items'] is List
        ? (invoice['items'] as List).whereType<Map>()
        : const Iterable<Map>.empty();
    final tax = invoice['tax_amount'];
    final payment = invoice['payment_method'];

    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${invoice['store_name'] ?? 'كاشير حاسم'}',
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                        color: HasimColors.brandDark,
                      ),
                    ),
                    Text(
                      'فاتورة ${invoice['invoice_number']}',
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
              IconButton(onPressed: onClose, icon: const Icon(Icons.close)),
            ],
          ),
          Text(
            'التاريخ: ${invoice['closed_at'] ?? '—'}',
            style: const TextStyle(fontSize: 12, color: HasimColors.muted),
          ),
          Text(
            'الطاولة: ${invoice['table']?['name'] ?? '—'}',
            style: const TextStyle(fontSize: 12, color: HasimColors.muted),
          ),
          const SizedBox(height: 8),
          Expanded(
            child: ListView(
              children: [
                for (final item in items)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 6),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(
                            '${item['item_name'] ?? item['product_name'] ?? 'صنف'}'
                            '${item['size_label'] != null ? ' (${item['size_label']})' : ''}'
                            ' × ${item['quantity'] ?? 1}',
                            style: const TextStyle(fontSize: 12),
                          ),
                        ),
                        Text(
                          ((item['unit_price'] as num?) ?? 0).toStringAsFixed(2),
                          style: const TextStyle(
                            fontSize: 11,
                            color: HasimColors.muted,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Text(
                          ((item['total_amount'] as num?) ?? 0)
                              .toStringAsFixed(2),
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
          const Divider(),
          _row('المجموع الفرعي', invoice['subtotal']),
          _row('الخصم', invoice['discount_amount']),
          if (tax != null) _row('الضريبة', tax),
          _row('الإجمالي', invoice['total_amount'], bold: true),
          if (payment != null)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                'طريقة الدفع: $payment',
                style: const TextStyle(fontSize: 12),
              ),
            )
          else
            const Padding(
              padding: EdgeInsets.only(top: 4),
              child: Text(
                'طريقة الدفع: غير مُرجَعة من API الفاتورة الحالية',
                style: TextStyle(fontSize: 11, color: HasimColors.muted),
              ),
            ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: HsPrimaryButton(label: 'طباعة', onPressed: onPrint),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: HsOutlineButton(
                  label: 'إعادة طباعة',
                  onPressed: onReprint,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _row(String label, Object? value, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
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
            ((value as num?) ?? 0).toStringAsFixed(2),
            style: TextStyle(
              fontWeight: bold ? FontWeight.w900 : FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}
