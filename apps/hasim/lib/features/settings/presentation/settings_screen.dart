
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/core/config/app_config.dart';
import 'package:hasim/core/di/providers.dart';
import 'package:hasim/core/models/models.dart';
import 'package:hasim/features/auth/providers/auth_controller.dart';

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

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authControllerProvider);
    final push = ref.watch(pushServiceProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('الإعدادات')),
      body: ListView(
        children: [
          ListTile(
            title: Text(auth.user?.name ?? 'مستخدم'),
            subtitle: Text(auth.user?.email ?? auth.user?.phone ?? ''),
            leading: const CircleAvatar(child: Icon(Icons.person)),
          ),
          ListTile(
            title: const Text('مساحة العمل'),
            subtitle: Text(auth.workspace?.name ?? 'غير محددة'),
            trailing: const Icon(Icons.chevron_left),
            onTap: () => context.push('/workspaces'),
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
