import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/cashier_api.dart';
import '../../core/audio/menu_sound_service.dart';
import '../../core/auth/auth_controller.dart';
import '../../core/permissions/cashier_permissions.dart';
import '../../core/permissions/permissions_provider.dart';
import '../../core/printing/printer_service.dart';
import '../../core/realtime/pos_event_source.dart';
import '../../core/theme/hasim_colors.dart';
import '../../core/widgets/hasim_widgets.dart';
import '../cart/cart_controller.dart';

/// Cashier settings — POS settings via API + local printer/realtime.
class SettingsPanel extends ConsumerStatefulWidget {
  const SettingsPanel({super.key});

  @override
  ConsumerState<SettingsPanel> createState() => _SettingsPanelState();
}

class _SettingsPanelState extends ConsumerState<SettingsPanel> {
  var _sound = true;
  var _delivery = true;
  var _ready = false;
  var _savingPos = false;
  final _tax = TextEditingController(text: '0');
  final _currency = TextEditingController(text: 'SAR');
  PrinterProfile? _profile;
  final _name = TextEditingController(text: 'طابعة الشبكة');
  final _address = TextEditingController();
  PrinterTransport _transport = PrinterTransport.network;

  Map<String, dynamic> get _perms => CashierPermissions.resolve(
        ref.read(cashierPermissionsProvider),
        ref.read(authControllerProvider).valueOrNull?.permissions,
      );

  bool get _canManagePos => CashierPermissions.canManageMenu(_perms);

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _tax.dispose();
    _currency.dispose();
    _name.dispose();
    _address.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final sound = ref.read(menuSoundServiceProvider);
    final printer = await ref.read(printerServiceFutureProvider.future);
    Map<String, dynamic>? settings;
    try {
      final bootstrap = await ref.read(cashierApiProvider).get('/bootstrap');
      if (bootstrap['settings'] is Map) {
        settings = Map<String, dynamic>.from(bootstrap['settings'] as Map);
      }
    } catch (_) {
      // Keep local defaults if bootstrap fails.
    }
    if (!mounted) return;
    setState(() {
      if (settings != null) {
        _tax.text =
            ((settings['tax_rate'] as num?) ?? 0).toStringAsFixed(2);
        _currency.text = '${settings['currency'] ?? 'SAR'}';
        _sound = settings['sound_enabled'] == true ||
            settings['new_order_sound'] == true;
        _delivery = settings['enable_delivery'] != false;
        ref.read(menuSoundServiceProvider).setEnabled(_sound);
        ref
            .read(cartControllerProvider.notifier)
            .setTaxRate(((settings['tax_rate'] as num?) ?? 0).toDouble());
      } else {
        _sound = sound.enabled;
      }
      _profile = printer.selected;
      if (_profile != null) {
        _name.text = _profile!.name;
        _address.text = _profile!.address ?? '';
        _transport = _profile!.transport;
      }
      _ready = true;
    });
  }

  Future<void> _savePosSettings() async {
    if (!_canManagePos) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لا تملك صلاحية تحديث إعدادات الكاشير.')),
      );
      return;
    }
    final tax = double.tryParse(_tax.text.trim());
    if (tax == null || tax < 0 || tax > 100) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('نسبة الضريبة غير صالحة.')),
      );
      return;
    }
    setState(() => _savingPos = true);
    try {
      final data = await ref.read(cashierApiProvider).patch(
        '/settings/pos',
        data: {
          'tax_rate': tax,
          'new_order_sound': _sound,
          'enable_delivery': _delivery,
          'currency': _currency.text.trim().toUpperCase(),
        },
      );
      await ref.read(menuSoundServiceProvider).setEnabled(_sound);
      ref.read(cartControllerProvider.notifier).setTaxRate(
            ((data['tax_rate'] as num?) ?? tax).toDouble(),
          );
      if (!mounted) return;
      setState(() {
        _savingPos = false;
        _tax.text = ((data['tax_rate'] as num?) ?? tax).toStringAsFixed(2);
        _currency.text = '${data['currency'] ?? _currency.text}';
        _sound = data['sound_enabled'] == true || data['new_order_sound'] == true;
        _delivery = data['enable_delivery'] == true;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم حفظ إعدادات الكاشير على الخادم.')),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _savingPos = false);
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    } catch (e) {
      if (!mounted) return;
      setState(() => _savingPos = false);
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.toString())));
    }
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
    final canManage = CashierPermissions.canManageMenu(
      CashierPermissions.resolve(
        ref.watch(cashierPermissionsProvider),
        ref.watch(authControllerProvider).valueOrNull?.permissions,
      ),
    );

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const Text(
          'الإعدادات',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 12),
        HsCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'إعدادات الكاشير (Laravel)',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 6),
              Text(
                canManage
                    ? 'تُحفظ عبر PATCH /settings/pos'
                    : 'عرض فقط — تحتاج menu.manage للتعديل',
                style: const TextStyle(fontSize: 12, color: HasimColors.muted),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: _tax,
                enabled: canManage && _ready && !_savingPos,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(
                  labelText: 'نسبة الضريبة %',
                  isDense: true,
                ),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: _currency,
                enabled: canManage && _ready && !_savingPos,
                decoration: const InputDecoration(
                  labelText: 'العملة',
                  isDense: true,
                ),
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text(
                  'صوت طلبات المنيو',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
                value: _sound,
                activeThumbColor: HasimColors.cta,
                onChanged: (!canManage || !_ready || _savingPos)
                    ? null
                    : (v) => setState(() => _sound = v),
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text(
                  'تفعيل التوصيل',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
                value: _delivery,
                activeThumbColor: HasimColors.cta,
                onChanged: (!canManage || !_ready || _savingPos)
                    ? null
                    : (v) => setState(() => _delivery = v),
              ),
              if (canManage)
                HsPrimaryButton(
                  label: _savingPos ? 'جاري الحفظ…' : 'حفظ إعدادات الكاشير',
                  onPressed: (!_ready || _savingPos) ? null : _savePosSettings,
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
                'Polling هو المصدر الافتراضي. Pusher/Reverb لن يُفعَّل بدون credentials.',
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
