import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/core/config/app_config.dart';
import 'package:hasim/core/di/providers.dart';
import 'package:hasim/core/models/models.dart';
import 'package:hasim/core/theme/theme_mode_controller.dart';
import 'package:hasim/features/auth/providers/auth_controller.dart';
import 'package:cached_network_image/cached_network_image.dart';

class SettingsScreen extends ConsumerStatefulWidget {
  const SettingsScreen({super.key});
  @override
  ConsumerState<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends ConsumerState<SettingsScreen> {
  List<DeviceSessionModel> _sessions = [];

  @override
  void initState() {
    super.initState();
    _loadSessions();
  }

  Future<void> _loadSessions() async {
    try {
      final sessions = await ref.read(sessionRepositoryProvider).list();
      if (mounted) setState(() => _sessions = sessions);
    } catch (_) {}
  }

  String _themeLabel(ThemeMode mode) {
    return switch (mode) {
      ThemeMode.light => 'فاتح',
      ThemeMode.dark => 'داكن',
      ThemeMode.system => 'تلقائي',
    };
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authControllerProvider);
    final push = ref.watch(pushServiceProvider);
    final themeMode = ref.watch(themeModeControllerProvider);
    final user = auth.user;

    return Scaffold(
      appBar: AppBar(title: const Text('الإعدادات')),
      body: ListView(
        children: [
          ListTile(
            title: Text(user?.name ?? 'مستخدم'),
            subtitle: Text(user?.email ?? user?.phone ?? ''),
            leading: CircleAvatar(
              backgroundImage: user?.avatarUrl != null ? CachedNetworkImageProvider(user!.avatarUrl!) : null,
              child: user?.avatarUrl == null ? const Icon(Icons.person) : null,
            ),
            trailing: const Icon(Icons.chevron_left),
            onTap: () => context.push('/profile'),
          ),
          ListTile(
            title: const Text('مساحة العمل'),
            subtitle: Text(auth.workspace?.name ?? 'غير محددة'),
            trailing: const Icon(Icons.chevron_left),
            onTap: () => context.push('/workspaces'),
          ),
          ListTile(
            leading: const Icon(Icons.workspace_premium_outlined),
            title: const Text('الباقات والاستخدام'),
            trailing: const Icon(Icons.chevron_left),
            onTap: () => context.push('/plans'),
          ),
          ListTile(
            leading: const Icon(Icons.hub_outlined),
            title: const Text('القنوات'),
            trailing: const Icon(Icons.chevron_left),
            onTap: () => context.push('/channels'),
          ),
          ListTile(
            leading: const Icon(Icons.notifications_active_outlined),
            title: const Text('تفضيلات الإشعارات'),
            trailing: const Icon(Icons.chevron_left),
            onTap: () => context.push('/notification-preferences'),
          ),
          ListTile(
            leading: const Icon(Icons.brightness_6_outlined),
            title: const Text('المظهر'),
            subtitle: Text(_themeLabel(themeMode)),
            trailing: SegmentedButton<ThemeMode>(
              segments: const [
                ButtonSegment(value: ThemeMode.system, icon: Icon(Icons.brightness_auto, size: 18)),
                ButtonSegment(value: ThemeMode.light, icon: Icon(Icons.light_mode, size: 18)),
                ButtonSegment(value: ThemeMode.dark, icon: Icon(Icons.dark_mode, size: 18)),
              ],
              selected: {themeMode},
              onSelectionChanged: (s) => ref.read(themeModeControllerProvider.notifier).setMode(s.first),
            ),
          ),
          ListTile(
            title: const Text('عنوان API'),
            subtitle: Text(AppConfig.apiBase, textDirection: TextDirection.ltr),
          ),
          ListTile(
            title: const Text('الإشعارات الفورية (Push)'),
            subtitle: Text(push.statusLabel),
          ),
          const Divider(),
          const Padding(
            padding: EdgeInsets.fromLTRB(16, 8, 16, 4),
            child: Text('الجلسات / الأجهزة', style: TextStyle(fontWeight: FontWeight.w700)),
          ),
          for (final s in _sessions)
            ListTile(
              title: Text(s.deviceName ?? s.name),
              subtitle: Text(s.deviceType ?? 'جهاز'),
              trailing: s.isCurrent
                  ? const Text('الحالية')
                  : IconButton(
                      icon: const Icon(Icons.logout),
                      onPressed: () async {
                        await ref.read(sessionRepositoryProvider).revoke(s.id);
                        await _loadSessions();
                      },
                    ),
            ),
          TextButton(
            onPressed: () async {
              await ref.read(sessionRepositoryProvider).revokeOthers();
              await _loadSessions();
            },
            child: const Text('إنهاء الجلسات الأخرى'),
          ),
          const Divider(),
          ListTile(
            leading: const Icon(Icons.logout, color: Colors.red),
            title: const Text('تسجيل الخروج', style: TextStyle(color: Colors.red)),
            onTap: () async {
              await ref.read(authControllerProvider.notifier).logout();
              if (context.mounted) context.go('/login');
            },
          ),
        ],
      ),
    );
  }
}
