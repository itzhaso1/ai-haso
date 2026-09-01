import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/core/utils/relative_time.dart';
import 'package:hasim/core/widgets/async_body.dart';
import 'package:hasim/core/widgets/skeleton_list.dart';
import 'package:hasim/features/conversations/providers/conversations_controller.dart';

class ConversationsScreen extends ConsumerStatefulWidget {
  const ConversationsScreen({super.key});
  @override
  ConsumerState<ConversationsScreen> createState() => _ConversationsScreenState();
}

class _ConversationsScreenState extends ConsumerState<ConversationsScreen> {
  final _search = TextEditingController();

  static const _channels = [
    (null, 'الكل'),
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

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(conversationsControllerProvider);
    final notifier = ref.read(conversationsControllerProvider.notifier);

    return Scaffold(
      appBar: AppBar(title: const Text('المحادثات')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: TextField(
              controller: _search,
              decoration: const InputDecoration(
                hintText: 'بحث في المحادثات...',
                prefixIcon: Icon(Icons.search),
              ),
              onSubmitted: notifier.setSearch,
            ),
          ),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            child: Row(
              children: [
                for (final f in [('all', 'الكل'), ('unread', 'غير مقروء'), ('archived', 'مؤرشف')])
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: ChoiceChip(
                      label: Text(f.$2),
                      selected: state.filter == f.$1,
                      onSelected: (_) => notifier.setFilter(f.$1),
                    ),
                  ),
                const SizedBox(width: 8),
                for (final c in _channels)
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: FilterChip(
                      label: Text(c.$2),
                      selected: state.channel == c.$1,
                      onSelected: (_) => notifier.setChannel(c.$1),
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => notifier.refresh(),
              child: state.loading && state.items.isEmpty
                  ? const SkeletonList()
                  : AsyncBody(
                      loading: false,
                      error: state.error,
                      isEmpty: !state.loading && state.items.isEmpty,
                      emptyTitle: 'لا توجد محادثات',
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
                          separatorBuilder: (_, _) => const Divider(height: 1),
                          itemBuilder: (context, index) {
                            if (index >= state.items.length) {
                              return const Padding(
                                padding: EdgeInsets.all(16),
                                child: Center(child: CircularProgressIndicator()),
                              );
                            }
                            final c = state.items[index];
                            final time = relativeTimeAr(c.lastMessageAt);
                            return ListTile(
                              onTap: () => context.push('/conversations/${c.id}'),
                              leading: CircleAvatar(
                                backgroundColor: Theme.of(context).colorScheme.primary.withValues(alpha: 0.15),
                                child: Text(
                                  (c.title.isNotEmpty ? c.title[0] : '#'),
                                  style: TextStyle(
                                    color: Theme.of(context).colorScheme.primary,
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
                                      style: TextStyle(fontWeight: c.unreadCount > 0 ? FontWeight.w800 : FontWeight.w600),
                                    ),
                                  ),
                                  Text(time, style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                                ],
                              ),
                              subtitle: Row(
                                children: [
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: Colors.grey.shade200,
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Text(c.channelLabel, style: const TextStyle(fontSize: 11)),
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(child: Text(c.preview, maxLines: 1, overflow: TextOverflow.ellipsis)),
                                  if (c.unreadCount > 0)
                                    CircleAvatar(
                                      radius: 11,
                                      backgroundColor: Theme.of(context).colorScheme.primary,
                                      child: Text('${c.unreadCount}', style: const TextStyle(color: Colors.white, fontSize: 11)),
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
}
