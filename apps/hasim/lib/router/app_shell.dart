import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/features/home/providers/home_controller.dart';

class AppShell extends ConsumerWidget {
  const AppShell({super.key, required this.navigationShell});
  final StatefulNavigationShell navigationShell;

  void _go(int index) => navigationShell.goBranch(index, initialLocation: index == navigationShell.currentIndex);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final snap = ref.watch(homeControllerProvider).snapshot;
    final chatBadge = snap?.unreadConversations ?? 0;
    final emailBadge = snap?.unreadEmail ?? 0;

    return Scaffold(
      body: navigationShell,
      bottomNavigationBar: NavigationBar(
        selectedIndex: navigationShell.currentIndex,
        onDestinationSelected: _go,
        destinations: [
          const NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home),
            label: 'الرئيسية',
          ),
          NavigationDestination(
            icon: Badge(
              isLabelVisible: chatBadge > 0,
              label: Text('$chatBadge'),
              child: const Icon(Icons.chat_bubble_outline),
            ),
            selectedIcon: Badge(
              isLabelVisible: chatBadge > 0,
              label: Text('$chatBadge'),
              child: const Icon(Icons.chat_bubble),
            ),
            label: 'المحادثات',
          ),
          NavigationDestination(
            icon: Badge(
              isLabelVisible: emailBadge > 0,
              label: Text('$emailBadge'),
              child: const Icon(Icons.mail_outline),
            ),
            selectedIcon: Badge(
              isLabelVisible: emailBadge > 0,
              label: Text('$emailBadge'),
              child: const Icon(Icons.mail),
            ),
            label: 'البريد',
          ),
          const NavigationDestination(
            icon: Icon(Icons.event_outlined),
            selectedIcon: Icon(Icons.event),
            label: 'الحجوزات',
          ),
          const NavigationDestination(
            icon: Icon(Icons.settings_outlined),
            selectedIcon: Icon(Icons.settings),
            label: 'المزيد',
          ),
        ],
      ),
    );
  }
}
