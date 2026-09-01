import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/cashier_api.dart';
import '../../core/offline/offline_store.dart';
import '../../core/sync/pos_sync_coordinator.dart';
import '../../core/theme/hasim_colors.dart';
import '../../core/theme/hasim_radius.dart';
import '../../core/widgets/hasim_widgets.dart';

/// Visible sync queue: Pending / Syncing / Synced / Failed + Retry.
class SyncQueuePanel extends ConsumerStatefulWidget {
  const SyncQueuePanel({super.key});

  @override
  ConsumerState<SyncQueuePanel> createState() => _SyncQueuePanelState();
}

class _SyncQueuePanelState extends ConsumerState<SyncQueuePanel> {
  List<Map<String, dynamic>> _records = const [];
  var _busy = false;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    setState(() {
      _records = OfflineStore.instance
          .allOrderRecords(workspaceId: ref.read(workspaceIdProvider))
          .take(40)
          .toList();
    });
  }

  String _label(String? status) => switch (status) {
        'pending' => 'Pending',
        'syncing' => 'Syncing',
        'synced' => 'Synced',
        'failed' => 'Failed',
        _ => status ?? '—',
      };

  Color _bg(String? status) => switch (status) {
        'pending' => HasimColors.warningSoft,
        'syncing' => HasimColors.brandSoft,
        'synced' => HasimColors.ctaSoft,
        'failed' => HasimColors.dangerSoft,
        _ => HasimColors.navIdleBg,
      };

  Color _fg(String? status) => switch (status) {
        'pending' => HasimColors.warning,
        'syncing' => HasimColors.brandDark,
        'synced' => HasimColors.ctaDark,
        'failed' => HasimColors.danger,
        _ => HasimColors.ink,
      };

  Future<void> _flush() async {
    setState(() => _busy = true);
    await ref.read(posSyncCoordinatorProvider).flushPendingOrders(
          workspaceId: ref.read(workspaceIdProvider),
        );
    if (!mounted) return;
    setState(() => _busy = false);
    _reload();
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
                child: Text(
                  'طابور المزامنة',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
                ),
              ),
              HsPrimaryButton(
                label: _busy ? 'جاري…' : 'مزامنة الكل',
                loading: _busy,
                onPressed: _busy ? null : _flush,
              ),
            ],
          ),
        ),
        const Padding(
          padding: EdgeInsets.symmetric(horizontal: 16),
          child: Text(
            'الطلبات المحلية لا تُحذف عند الفشل. Idempotency عبر client_reference تمنع التكرار.',
            style: TextStyle(fontSize: 12, color: HasimColors.muted),
          ),
        ),
        Expanded(
          child: _records.isEmpty
              ? const Padding(
                  padding: EdgeInsets.all(16),
                  child: HsEmpty(title: 'لا توجد عمليات مزامنة محفوظة.'),
                )
              : ListView.separated(
                  padding: const EdgeInsets.all(12),
                  itemCount: _records.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (context, index) {
                    final row = _records[index];
                    final status = row['status'] as String?;
                    final payload = row['payload'];
                    final itemsCount = payload is Map && payload['items'] is List
                        ? (payload['items'] as List).length
                        : 0;
                    return HsCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  'محلي ${'${row['local_id']}'.substring(0, 8)}…',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                    fontSize: 12,
                                  ),
                                ),
                              ),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 4,
                                ),
                                decoration: BoxDecoration(
                                  color: _bg(status),
                                  borderRadius:
                                      BorderRadius.circular(HasimRadius.pill),
                                ),
                                child: Text(
                                  _label(status),
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w800,
                                    color: _fg(status),
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text(
                            '${row['created_at'] ?? ''} · $itemsCount أصناف',
                            style: const TextStyle(
                              fontSize: 11,
                              color: HasimColors.muted,
                            ),
                          ),
                          if (row['last_error'] != null) ...[
                            const SizedBox(height: 4),
                            Text(
                              '${row['last_error']}',
                              style: const TextStyle(
                                fontSize: 11,
                                color: HasimColors.danger,
                              ),
                            ),
                          ],
                          if (status == 'failed' || status == 'pending') ...[
                            const SizedBox(height: 8),
                            Align(
                              alignment: AlignmentDirectional.centerEnd,
                              child: TextButton(
                                onPressed: () async {
                                  await ref
                                      .read(posSyncCoordinatorProvider)
                                      .retryOne(
                                        '${row['local_id']}',
                                        workspaceId:
                                            ref.read(workspaceIdProvider),
                                      );
                                  _reload();
                                },
                                child: const Text('Retry'),
                              ),
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
