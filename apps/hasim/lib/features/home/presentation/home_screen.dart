
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/core/widgets/async_body.dart';
import 'package:hasim/features/auth/providers/auth_controller.dart';
import 'package:hasim/features/home/providers/home_controller.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);
    final home = ref.watch(homeControllerProvider);
    final snap = home.snapshot;

    return Scaffold(
      appBar: AppBar(
        title: Text(auth.workspace?.name ?? 'حاسم'),
        actions: [
          IconButton(onPressed: () => context.push('/workspaces'), icon: const Icon(Icons.swap_horiz)),
          IconButton(onPressed: () => context.push('/notifications'), icon: const Icon(Icons.notifications_outlined)),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.read(homeControllerProvider.notifier).refresh(),
        child: AsyncBody(
          loading: home.loading && snap == null,
          error: home.error,
          isEmpty: false,
          onRetry: () => ref.read(homeControllerProvider.notifier).refresh(),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text('مرحباً ${auth.user?.name ?? ''}', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
              const SizedBox(height: 4),
              Text('نظرة سريعة على نشاط اليوم', style: TextStyle(color: Colors.grey.shade700)),
              const SizedBox(height: 16),
              _Card(title: 'محادثات غير مقروءة', value: '${snap?.unreadConversations ?? 0}', icon: Icons.chat_bubble_outline, onTap: () => context.go('/conversations')),
              _Card(title: 'بريد جديد', value: '${snap?.unreadEmail ?? 0}', icon: Icons.mail_outline, onTap: () => context.go('/email')),
              _Card(title: 'حجوزات اليوم', value: '${snap?.todaysBookingsCount ?? 0}', icon: Icons.event_available, onTap: () => context.go('/appointments')),
              _Card(title: 'إشعارات', value: '${snap?.unreadNotifications ?? 0}', icon: Icons.notifications_active_outlined, onTap: () => context.push('/notifications')),
            ],
          ),
        ),
      ),
    );
  }
}

class _Card extends StatelessWidget {
  const _Card({required this.title, required this.value, required this.icon, required this.onTap});
  final String title;
  final String value;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: ListTile(
        onTap: onTap,
        leading: CircleAvatar(backgroundColor: Theme.of(context).colorScheme.primary.withValues(alpha: 0.12), child: Icon(icon, color: Theme.of(context).colorScheme.primary)),
        title: Text(title),
        trailing: Text(value, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800, color: Theme.of(context).colorScheme.primary)),
      ),
    );
  }
}
