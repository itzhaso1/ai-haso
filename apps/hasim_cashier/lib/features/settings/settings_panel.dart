import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/audio/menu_sound_service.dart';
import '../../core/theme/hasim_colors.dart';
import '../../core/widgets/hasim_widgets.dart';

/// Cashier settings — sound and connection preferences.
class SettingsPanel extends ConsumerStatefulWidget {
  const SettingsPanel({super.key});

  @override
  ConsumerState<SettingsPanel> createState() => _SettingsPanelState();
}

class _SettingsPanelState extends ConsumerState<SettingsPanel> {
  var _sound = true;
  var _ready = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final service = ref.read(menuSoundServiceProvider);
    await Future<void>.delayed(Duration.zero);
    if (!mounted) return;
    setState(() {
      _sound = service.enabled;
      _ready = true;
    });
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const Text(
          'الإعدادات',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 12),
        HsCard(
          child: SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text(
              'صوت طلبات المنيو',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
            subtitle: const Text(
              'تشغيل تنبيه عند وصول طلب جديد من المنيو.',
              style: TextStyle(fontSize: 12, color: HasimColors.muted),
            ),
            value: _sound,
            activeThumbColor: HasimColors.cta,
            onChanged: !_ready
                ? null
                : (v) async {
                    setState(() => _sound = v);
                    await ref.read(menuSoundServiceProvider).setEnabled(v);
                  },
          ),
        ),
        const SizedBox(height: 12),
        HsCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'اختبار الصوت',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              HsOutlineButton(
                label: 'تشغيل عينة',
                onPressed: () =>
                    ref.read(menuSoundServiceProvider).playNewOrder(),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
