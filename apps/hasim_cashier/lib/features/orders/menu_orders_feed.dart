import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/cashier_api.dart';
import '../../core/audio/menu_sound_service.dart';
import '../../core/pos/pos_labels.dart';
import '../../core/theme/hasim_colors.dart';
import '../../core/widgets/hasim_widgets.dart';

/// Menu / QR order feed with optional chime — mirrors menu-orders UX.
class MenuOrdersFeed extends ConsumerStatefulWidget {
  const MenuOrdersFeed({super.key});

  @override
  ConsumerState<MenuOrdersFeed> createState() => _MenuOrdersFeedState();
}

class _MenuOrdersFeedState extends ConsumerState<MenuOrdersFeed> {
  List<Map<String, dynamic>> _orders = const [];
  int? _lastSeenId;
  var _soundEnabled = true;
  var _loadedPrefs = false;

  @override
  void initState() {
    super.initState();
    _initSound();
    _poll();
  }

  Future<void> _initSound() async {
    final sound = ref.read(menuSoundServiceProvider);
    await Future<void>.delayed(Duration.zero);
    if (!mounted) return;
    setState(() {
      _soundEnabled = sound.enabled;
      _loadedPrefs = true;
    });
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
        if (list.isNotEmpty) {
          final newest = (list.first['id'] as num?)?.toInt();
          if (_lastSeenId != null &&
              newest != null &&
              newest != _lastSeenId &&
              mounted) {
            final table = list.first['table']?['name'] ?? '—';
            final number = list.first['order_number'] ?? newest;
            if (_soundEnabled) {
              await ref.read(menuSoundServiceProvider).playNewOrder();
            }
            if (!mounted) return;
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('طلب جديد من طاولة $table #$number'),
                duration: const Duration(milliseconds: 4200),
                backgroundColor: HasimColors.ink,
              ),
            );
          }
          _lastSeenId = newest;
        }
        if (mounted) setState(() => _orders = list);
      } catch (_) {}
      await Future<void>.delayed(const Duration(seconds: 3));
    }
  }

  Color _statusBg(String? status) => switch (status) {
        'new' => HasimColors.warningSoft,
        'accepted' => HasimColors.brandSoft,
        'preparing' => const Color(0xFFEFF6FF),
        'ready' => HasimColors.ctaSoft,
        'cancelled' => HasimColors.dangerSoft,
        _ => HasimColors.navIdleBg,
      };

  Color _statusFg(String? status) => switch (status) {
        'new' => HasimColors.warning,
        'accepted' => HasimColors.brandDark,
        'preparing' => const Color(0xFF1D4ED8),
        'ready' => HasimColors.ctaDark,
        'cancelled' => HasimColors.danger,
        _ => HasimColors.ink,
      };

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
          child: Row(
            children: [
              const Expanded(
                child: Text(
                  'طلبات المنيو',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
              FilterChip(
                label: Text(_soundEnabled ? 'الصوت: تشغيل' : 'الصوت: إيقاف'),
                selected: _soundEnabled,
                onSelected: _loadedPrefs
                    ? (v) async {
                        setState(() => _soundEnabled = v);
                        await ref
                            .read(menuSoundServiceProvider)
                            .setEnabled(v);
                      }
                    : null,
              ),
            ],
          ),
        ),
        Expanded(
          child: _orders.isEmpty
              ? const Padding(
                  padding: EdgeInsets.all(16),
                  child: HsEmpty(title: 'لا توجد طلبات منيو حالياً'),
                )
              : ListView.separated(
                  padding: const EdgeInsets.all(12),
                  itemCount: _orders.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (context, index) {
                    final order = _orders[index];
                    final status = order['pos_status'] as String?;
                    final items = order['items'] is List
                        ? (order['items'] as List).whereType<Map>()
                        : const Iterable<Map>.empty();
                    final card = HsCard(
                      color: status == 'new'
                          ? HasimColors.warningSoft
                          : HasimColors.surface,
                      borderColor: status == 'new'
                          ? const Color(0xFFFDE68A)
                          : HasimColors.border,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  status == 'new'
                                      ? 'طلب جديد #${order['order_number'] ?? order['id']}'
                                      : '#${order['order_number'] ?? order['id']}',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ),
                              HsBadge(
                                label: PosLabels.status(status),
                                background: _statusBg(status),
                                foreground: _statusFg(status),
                              ),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'الطاولة: ${order['table']?['name'] ?? '—'}',
                            style: const TextStyle(fontSize: 12),
                          ),
                          Text(
                            '${order['created_at'] ?? ''}',
                            style: const TextStyle(
                              fontSize: 11,
                              color: HasimColors.muted,
                            ),
                          ),
                          if (order['notes'] != null &&
                              (order['notes'] as String).isNotEmpty) ...[
                            const SizedBox(height: 4),
                            Text(
                              'ملاحظات: ${order['notes']}',
                              style: const TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                          if (items.isNotEmpty) ...[
                            const SizedBox(height: 8),
                            for (final item in items)
                              Text(
                                '${item['product_name']} × ${item['quantity']}',
                                style: const TextStyle(fontSize: 12),
                              ),
                          ],
                        ],
                      ),
                    );
                    if (index == 0 && status == 'new') {
                      return card
                          .animate()
                          .fadeIn(duration: 280.ms)
                          .slideY(begin: -0.05, curve: Curves.easeOut);
                    }
                    return card;
                  },
                ),
        ),
      ],
    );
  }
}
