
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/features/appointments/presentation/appointment_detail_screen.dart';
import 'package:hasim/features/appointments/presentation/appointments_screen.dart';
import 'package:hasim/features/auth/presentation/login_screen.dart';
import 'package:hasim/features/auth/providers/auth_controller.dart';
import 'package:hasim/features/conversations/presentation/chat_screen.dart';
import 'package:hasim/features/conversations/presentation/conversations_screen.dart';
import 'package:hasim/features/email/presentation/email_compose_screen.dart';
import 'package:hasim/features/email/presentation/email_detail_screen.dart';
import 'package:hasim/features/email/presentation/email_list_screen.dart';
import 'package:hasim/features/home/presentation/home_screen.dart';
import 'package:hasim/features/notifications/presentation/notifications_screen.dart';
import 'package:hasim/features/settings/presentation/settings_screen.dart';
import 'package:hasim/features/workspace/presentation/workspace_picker_screen.dart';
import 'package:hasim/router/app_shell.dart';

final _rootKey = GlobalKey<NavigatorState>();

final appRouterProvider = Provider<GoRouter>((ref) {
  final auth = ref.watch(authControllerProvider);

  return GoRouter(
    navigatorKey: _rootKey,
    initialLocation: '/home',
    refreshListenable: _AuthListenable(ref),
    redirect: (context, state) {
      if (auth.bootstrapping) return null;
      final loggingIn = state.matchedLocation == '/login';
      if (!auth.isAuthenticated) return loggingIn ? null : '/login';
      if (auth.isAuthenticated && loggingIn) {
        return auth.workspace == null ? '/workspaces' : '/home';
      }
      if (auth.isAuthenticated && auth.workspace == null && state.matchedLocation != '/workspaces') {
        return '/workspaces';
      }
      return null;
    },
    routes: [
      GoRoute(path: '/login', builder: (context, state) => const LoginScreen()),
      GoRoute(path: '/workspaces', builder: (context, state) => const WorkspacePickerScreen()),
      GoRoute(path: '/notifications', builder: (context, state) => const NotificationsScreen()),
      GoRoute(path: '/conversations/:id', builder: (context, state) => ChatScreen(conversationId: int.parse(state.pathParameters['id']!))),
      GoRoute(path: '/email/compose', builder: (context, state) => const EmailComposeScreen()),
      GoRoute(path: '/email/:id', builder: (context, state) => EmailDetailScreen(id: int.parse(state.pathParameters['id']!))),
      GoRoute(path: '/appointments/:id', builder: (context, state) => AppointmentDetailScreen(id: int.parse(state.pathParameters['id']!))),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) => AppShell(navigationShell: navigationShell),
        branches: [
          StatefulShellBranch(routes: [GoRoute(path: '/home', builder: (context, state) => const HomeScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/conversations', builder: (context, state) => const ConversationsScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/email', builder: (context, state) => const EmailListScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/appointments', builder: (context, state) => const AppointmentsScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/settings', builder: (context, state) => const SettingsScreen())]),
        ],
      ),
    ],
  );
});

class _AuthListenable extends ChangeNotifier {
  _AuthListenable(this.ref) {
    ref.listen(authControllerProvider, (previous, next) => notifyListeners());
  }
  final Ref ref;
}
