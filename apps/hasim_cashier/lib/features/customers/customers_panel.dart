import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/cashier_api.dart';
import '../../core/theme/hasim_colors.dart';
import '../../core/widgets/hasim_widgets.dart';

class CustomersPanel extends ConsumerStatefulWidget {
  const CustomersPanel({super.key});

  @override
  ConsumerState<CustomersPanel> createState() => _CustomersPanelState();
}

class _CustomersPanelState extends ConsumerState<CustomersPanel> {
  List<Map<String, dynamic>> _customers = const [];
  var _loading = true;
  String? _error;
  final _search = TextEditingController();

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
      final data = await ref.read(cashierApiProvider).get(
        '/customers',
        query: {
          if (_search.text.trim().isNotEmpty) 'q': _search.text.trim(),
        },
      );
      final list = <Map<String, dynamic>>[];
      final raw = data['customers'] ?? data['value'];
      if (raw is List) {
        for (final item in raw) {
          if (item is Map) list.add(Map<String, dynamic>.from(item));
        }
      }
      setState(() {
        _customers = list;
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

  Future<void> _addCustomer() async {
    final name = TextEditingController();
    final phone = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('إضافة عميل'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: name,
              decoration: const InputDecoration(labelText: 'الاسم'),
            ),
            TextField(
              controller: phone,
              decoration: const InputDecoration(labelText: 'الجوال'),
              keyboardType: TextInputType.phone,
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('حفظ'),
          ),
        ],
      ),
    );
    if (ok != true || name.text.trim().isEmpty) return;
    try {
      await ref.read(cashierApiProvider).post(
        '/customers',
        data: {
          'name': name.text.trim(),
          if (phone.text.trim().isNotEmpty) 'phone': phone.text.trim(),
        },
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
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _search,
                  decoration: const InputDecoration(
                    hintText: 'بحث عن عميل…',
                    isDense: true,
                    prefixIcon: Icon(Icons.search, size: 18),
                  ),
                  onSubmitted: (_) => _load(),
                ),
              ),
              const SizedBox(width: 8),
              HsPrimaryButton(label: 'إضافة', onPressed: _addCustomer),
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
                        title: 'تعذر تحميل العملاء',
                        subtitle: _error,
                        actionLabel: 'إعادة المحاولة',
                        onAction: _load,
                      ),
                    )
                  : _customers.isEmpty
                      ? const Padding(
                          padding: EdgeInsets.all(16),
                          child: HsEmpty(title: 'لا يوجد عملاء.'),
                        )
                      : RefreshIndicator(
                          onRefresh: _load,
                          child: ListView.separated(
                            padding: const EdgeInsets.all(12),
                            itemCount: _customers.length,
                            separatorBuilder: (_, _) =>
                                const SizedBox(height: 8),
                            itemBuilder: (context, index) {
                              final c = _customers[index];
                              return HsCard(
                                child: Row(
                                  children: [
                                    const CircleAvatar(
                                      backgroundColor: HasimColors.brandSoft,
                                      child: Icon(
                                        Icons.person_outline,
                                        color: HasimColors.brandDark,
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            '${c['name']}',
                                            style: const TextStyle(
                                              fontWeight: FontWeight.w800,
                                            ),
                                          ),
                                          Text(
                                            '${c['phone'] ?? '—'}',
                                            style: const TextStyle(
                                              fontSize: 12,
                                              color: HasimColors.muted,
                                            ),
                                          ),
                                        ],
                                      ),
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
