import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/core/utils/relative_time.dart';
import 'package:hasim/core/widgets/async_body.dart';
import 'package:hasim/core/widgets/hasim_logo.dart';
import 'package:hasim/core/widgets/skeleton_list.dart';
import 'package:hasim/features/auth/providers/auth_controller.dart';
import 'package:hasim/features/conversations/providers/conversations_controller.dart';
import 'package:hasim/features/home/providers/home_controller.dart';
import 'package:hasim/features/stories/presentation/stories_strip.dart';
import 'package:hasim/features/stories/providers/stories_controller.dart';

/// الشاشة الأولى بعد الدخول — محادثات أولاً (Messaging App).
class ConversationsScreen extends ConsumerStatefulWidget {
  const ConversationsScreen({super.key});
  @override
  ConsumerState<ConversationsScreen> createState() => _ConversationsScreenState();
}

class _ConversationsScreenState extends ConsumerState<ConversationsScreen> {
  final _search = TextEditingController();

  /// فلاتر مدعومة في Mobile API فقط: all | unread | archived
  static const _filters = [
    ('all', 'الكل'),
    ('unread', 'غير مقروءة'),
    ('archived', 'مؤرشفة'),
  ];

  static const _channels = [
    ('whatsapp', 'واتساب'),
    ('email', 'بريد'),
    ('web', 'ويب'),
    ('manual', 'يدوي'),
  ];

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _openOverflowMenu() async {
    final value = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          children: [
            ListTile(leading: const Icon(Icons.person_outline), title: const Text('حسابي'), onTap: () => Navigator.pop(ctx, 'profile')),
            ListTile(leading: const Icon(Icons.contacts_outlined), title: const Text('جهات الاتصال'), onTap: () => Navigator.pop(ctx, 'contacts')),
            ListTile(leading: const Icon(Icons.hub_outlined), title: const Text('القنوات'), onTap: () => Navigator.pop(ctx, 'channels')),
            ListTile(leading: const Icon(Icons.workspace_premium_outlined), title: const Text('الباقة والاستخدام'), onTap: () => Navigator.pop(ctx, 'plans')),
            ListTile(leading: const Icon(Icons.notifications_outlined), title: const Text('الإشعارات'), onTap: () => Navigator.pop(ctx, 'notifications')),
            ListTile(leading: const Icon(Icons.settings_outlined), title: const Text('الإعدادات'), onTap: () => Navigator.pop(ctx, 'settings')),
            ListTile(leading: const Icon(Icons.security_outlined), title: const Text('الأمان والجلسات'), onTap: () => Navigator.pop(ctx, 'security')),
            ListTile(leading: const Icon(Icons.swap_horiz), title: const Text('تبديل مساحة العمل'), onTap: () => Navigator.pop(ctx, 'workspace')),
            const Divider(),
            ListTile(
              leading: Icon(Icons.logout, color: Theme.of(ctx).colorScheme.error),
              title: Text('تسجيل الخروج', style: TextStyle(color: Theme.of(ctx).colorScheme.error)),
              onTap: () => Navigator.pop(ctx, 'logout'),
            ),
          ],
        ),
      ),
    );
    if (value == null || !mounted) return;
    switch (value) {
      case 'profile':
        context.push('/profile');
      case 'contacts':
        context.push('/contacts');
      case 'channels':
        context.push('/channels');
      case 'plans':
        context.push('/plans');
      case 'notifications':
        context.push('/notifications');
      case 'settings':
        context.push('/settings');
      case 'security':
        context.push('/more/security');
      case 'workspace':
        context.push('/workspaces');
      case 'logout':
        await ref.read(authControllerProvider.notifier).logout();
        if (mounted) context.go('/login');
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(conversationsControllerProvider);
    final notifier = ref.read(conversationsControllerProvider.notifier);
    final snap = ref.watch(homeControllerProvider).snapshot;
    final theme = Theme.of(context);
    final notif = snap?.unreadNotifications ?? 0;

    return Scaffold(
      appBar: AppBar(
        automaticallyImplyLeading: false,
        titleSpacing: 4,
        title: Row(
          children: [
            Semantics(
              button: true,
              label: 'المزيد',
              child: IconButton(
                tooltip: 'المزيد',
                onPressed: _openOverflowMenu,
                icon: const Icon(Icons.more_horiz),
              ),
            ),
            const Spacer(),
            Text(
              'حاسم',
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w800,
                color: theme.colorScheme.primary,
              ),
            ),
            const SizedBox(width: 8),
            const HasimLogo(size: 28),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'الإشعارات',
            onPressed: () => context.push('/notifications'),
            icon: Badge(
              isLabelVisible: notif > 0,
              label: Text('$notif'),
              child: const Icon(Icons.notifications_outlined),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 0),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                hintText: 'ابحث في المحادثات',
                prefixIcon: const Icon(Icons.search),
                filled: true,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide.none,
                ),
              ),
              onSubmitted: notifier.setSearch,
              onChanged: (v) {
                if (v.isEmpty) notifier.setSearch('');
              },
            ),
          ),
          const StoriesStrip(),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.fromLTRB(12, 4, 12, 8),
            child: Row(
              children: [
                for (final f in _filters)
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: ChoiceChip(
                      label: Text(f.$2),
                      selected: state.filter == f.$1,
                      onSelected: (_) => notifier.setFilter(f.$1),
                    ),
                  ),
                const SizedBox(width: 6),
                for (final c in _channels)
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: FilterChip(
                      label: Text(c.$2),
                      selected: state.channel == c.$1,
                      onSelected: (sel) => notifier.setChannel(sel ? c.$1 : null),
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () async {
                await notifier.refresh();
                await ref.read(storiesControllerProvider.notifier).refresh();
                await ref.read(homeControllerProvider.notifier).refresh();
              },
              child: state.loading && state.items.isEmpty
                  ? const SkeletonList()
                  : AsyncBody(
                      loading: false,
                      error: state.error,
                      isEmpty: !state.loading && state.items.isEmpty,
                      emptyTitle: 'لا توجد محادثات حاليًا',
                      emptySubtitle: 'ستظهر هنا رسائل واتساب والويب والقنوات الموحدة.',
                      onRetry: () => notifier.refresh(),
                      child: NotificationListener<ScrollNotification>(
                        onNotification: (n) {
                          if (n.metrics.pixels > n.metrics.maxScrollExtent - 200) {
                            notifier.loadMore();
                          }
                          return false;
                        },
                        child: ListView.separated(
                          itemCount: state.items.length + (state.loadingMore ? 1 : 0),
                          separatorBuilder: (_, _) => Divider(
                            height: 1,
                            color: theme.dividerColor.withValues(alpha: 0.5),
                          ),
                          itemBuilder: (context, index) {
                            if (index >= state.items.length) {
                              return const Padding(
                                padding: EdgeInsets.all(16),
                                child: Center(child: CircularProgressIndicator()),
                              );
                            }
                            final c = state.items[index];
                            final time = relativeTimeAr(c.lastMessageAt);
                            final unread = c.unreadCount > 0;
                            return ListTile(
                              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                              onTap: () => context.push('/conversations/${c.id}'),
                              leading: CircleAvatar(
                                backgroundColor: theme.colorScheme.primary.withValues(alpha: 0.15),
                                child: Text(
                                  (c.title.isNotEmpty ? c.title[0] : '#'),
                                  style: TextStyle(
                                    color: theme.colorScheme.primary,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ),
                              title: Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      c.title,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                        fontWeight: unread ? FontWeight.w800 : FontWeight.w600,
                                      ),
                                    ),
                                  ),
                                  Text(
                                    time,
                                    style: theme.textTheme.labelSmall?.copyWith(
                                      color: unread
                                          ? theme.colorScheme.primary
                                          : theme.colorScheme.onSurfaceVariant,
                                    ),
                                  ),
                                ],
                              ),
                              subtitle: Row(
                                children: [
                                  Icon(_channelIcon(c.channel), size: 14, color: theme.colorScheme.onSurfaceVariant),
                                  const SizedBox(width: 6),
                                  Expanded(
                                    child: Text(
                                      c.preview,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                        fontWeight: unread ? FontWeight.w600 : FontWeight.w400,
                                      ),
                                    ),
                                  ),
                                  if (unread)
                                    Padding(
                                      padding: const EdgeInsets.only(right: 4),
                                      child: CircleAvatar(
                                        radius: 11,
                                        backgroundColor: theme.colorScheme.primary,
                                        child: Text(
                                          '${c.unreadCount}',
                                          style: const TextStyle(color: Colors.white, fontSize: 11),
                                        ),
                                      ),
                                    ),
                                ],
                              ),
                            );
                          },
                        ),
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }

  IconData _channelIcon(String channel) {
    return switch (channel) {
      'whatsapp' => Icons.chat,
      'email' => Icons.mail_outline,
      'web' => Icons.language,
      _ => Icons.forum_outlined,
    };
  }
}
