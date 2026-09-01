import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/audio/menu_sound_service.dart';
import '../../core/printing/printer_service.dart';
import '../../core/realtime/pos_event_source.dart';
import '../../core/theme/hasim_colors.dart';
import '../../core/widgets/hasim_widgets.dart';

/// Cashier settings — sound, realtime mode, printer configuration.
class SettingsPanel extends ConsumerStatefulWidget {
  const SettingsPanel({super.key});

  @override
  ConsumerState<SettingsPanel> createState() => _SettingsPanelState();
}

class _SettingsPanelState extends ConsumerState<SettingsPanel> {
  var _sound = true;
  var _ready = false;
  PrinterProfile? _profile;
  final _name = TextEditingController(text: 'طابعة الشبكة');
  final _address = TextEditingController();
  PrinterTransport _transport = PrinterTransport.network;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _name.dispose();
    _address.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final sound = ref.read(menuSoundServiceProvider);
    final printer = await ref.read(printerServiceFutureProvider.future);
    await Future<void>.delayed(Duration.zero);
    if (!mounted) return;
    setState(() {
      _sound = sound.enabled;
      _profile = printer.selected;
      if (_profile != null) {
        _name.text = _profile!.name;
        _address.text = _profile!.address ?? '';
        _transport = _profile!.transport;
      }
      _ready = true;
    });
  }

  Future<void> _savePrinter() async {
    final printer = await ref.read(printerServiceFutureProvider.future);
    final profile = PrinterProfile(
      id: _profile?.id ?? 'primary',
      name: _name.text.trim().isEmpty ? 'طابعة' : _name.text.trim(),
      transport: _transport,
      address: _address.text.trim().isEmpty ? null : _address.text.trim(),
    );
    await printer.saveProfile(profile);
    if (!mounted) return;
    setState(() => _profile = profile);
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('تم حفظ إعدادات الطابعة.')),
    );
  }

  Future<void> _testPrint() async {
    final printer = await ref.read(printerServiceFutureProvider.future);
    final result = await printer.testPrint();
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(result.message)),
    );
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
        const SizedBox(height: 12),
        HsCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Realtime',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 6),
              Text(
                'الوضع الحالي: ${ref.watch(posRealtimeModeProvider)}',
                style: const TextStyle(fontSize: 12, color: HasimColors.muted),
              ),
              const SizedBox(height: 4),
              const Text(
                'Pusher/Reverb مُجهّز معماريًا عبر PosEventSource. لن يُفعَّل بدون credentials حقيقية — Polling يبقى fallback.',
                style: TextStyle(fontSize: 12, color: HasimColors.muted),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        HsCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'إعدادات الطابعة (ESC/POS)',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 6),
              Text(
                'الحالة: ${_profile == null ? 'غير مُعدّة' : (_profile!.address == null || _profile!.address!.isEmpty ? 'محفوظة بدون عنوان (غير متصلة)' : 'عنوان محفوظ — الإرسال يحتاج بوابة Native حقيقية')}',
                style: const TextStyle(fontSize: 12, color: HasimColors.muted),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: _name,
                decoration: const InputDecoration(
                  labelText: 'اسم الطابعة',
                  isDense: true,
                ),
              ),
              const SizedBox(height: 8),
              DropdownButtonFormField<PrinterTransport>(
                value: _transport,
                decoration: const InputDecoration(
                  labelText: 'نوع الاتصال',
                  isDense: true,
                ),
                items: const [
                  DropdownMenuItem(
                    value: PrinterTransport.network,
                    child: Text('Network'),
                  ),
                  DropdownMenuItem(
                    value: PrinterTransport.bluetooth,
                    child: Text('Bluetooth'),
                  ),
                  DropdownMenuItem(
                    value: PrinterTransport.usb,
                    child: Text('USB'),
                  ),
                  DropdownMenuItem(
                    value: PrinterTransport.system,
                    child: Text('System'),
                  ),
                ],
                onChanged: (v) {
                  if (v != null) setState(() => _transport = v);
                },
              ),
              const SizedBox(height: 8),
              TextField(
                controller: _address,
                decoration: const InputDecoration(
                  labelText: 'العنوان (IP / MAC / USB path)',
                  isDense: true,
                  hintText: 'مثال: 192.168.1.50',
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: HsPrimaryButton(
                      label: 'حفظ الطابعة',
                      onPressed: _savePrinter,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: HsOutlineButton(
                      label: 'Test Print',
                      onPressed: _testPrint,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              const Text(
                'لا يتم ادعاء نجاح الطباعة بدون جهاز. بوابة الإرسال الحالية UnconfiguredPrinterGateway حتى ربط Native SDK.',
                style: TextStyle(fontSize: 11, color: HasimColors.muted),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
