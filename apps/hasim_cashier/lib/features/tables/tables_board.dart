import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/cashier_api.dart';
import '../../core/config/app_config.dart';
import '../../core/network/cashier_link.dart';
import '../../core/offline/offline_store.dart';
import '../../core/pos/pos_labels.dart';
import '../../core/realtime/pos_event_source.dart';
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
  PollingPosEventSource? _source;

  @override
  void initState() {
    super.initState();
    _load();
    _startPolling();
  }

  @override
  void dispose() {
    _source?.dispose();
    _source = null;
    super.dispose();
  }

  Future<void> _startPolling() async {
    _source?.dispose();
    _source = PollingPosEventSource(
      interval: Duration(seconds: AppConfig.tablesPollSeconds),
      enabled: () => ref.read(cashierLinkProvider).isOnline,
      poll: () async {
        // Skip while a table detail is open — detail owns its own refresh.
        if (!mounted || ref.read(openTableIdProvider) != null) {
          return const <PosEvent>[];
        }
        await _load(silent: true);
        return const <PosEvent>[];
      },
    );
    await _source!.start();
  }

  Future<void> _load({bool silent = false}) async {
    if (!silent && mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      final data = await ref.read(cashierApiProvider).get('/tables');
      final list = <Map<String, dynamic>>[];
      if (data['tables'] is List) {
        for (final item in data['tables'] as List) {
          if (item is Map) list.add(Map<String, dynamic>.from(item));
        }
      }
      if (!mounted) return;
      await OfflineStore.instance.cacheTables(list);
      if (silent && _sameBoardSnapshot(_tables, list)) {
        return;
      }
      setState(() {
        _tables = list;
        _loading = false;
        _error = null;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      final cached = OfflineStore.instance.readTables();
      if (cached.isNotEmpty && _tables.isEmpty) {
        setState(() {
          _tables = cached;
          _loading = false;
          _error = silent ? null : e.message;
        });
        return;
      }
      if (silent) return;
      setState(() {
        _loading = false;
        _error = e.message;
      });
    } catch (e) {
      if (!mounted) return;
      if (silent) return;
      setState(() {
        _loading = false;
        _error = e.toString();
      });
    }
  }

  bool _sameBoardSnapshot(
    List<Map<String, dynamic>> current,
    List<Map<String, dynamic>> next,
  ) {
    if (current.length != next.length) return false;
    for (var i = 0; i < current.length; i++) {
      final a = current[i];
      final b = next[i];
      if (a['id'] != b['id'] ||
          a['status'] != b['status'] ||
          a['session_id'] != b['session_id'] ||
          a['open_orders_count'] != b['open_orders_count'] ||
          a['orders_count'] != b['orders_count'] ||
          a['total'] != b['total'] ||
          a['name'] != b['name']) {
        return false;
      }
    }
    return true;
  }

  @override
  Widget build(BuildContext context) {
    final openId = ref.watch(openTableIdProvider);
    if (openId != null) {
      return TableDetailScreen(key: ValueKey('table-$openId'), tableId: openId);
    }

    if (_loading && _tables.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null && _tables.isEmpty) {
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
