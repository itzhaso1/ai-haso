import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/auth/auth_controller.dart';
import '../features/auth/login_screen.dart';
import '../features/auth/pos_blocked_screen.dart';
import '../features/auth/workspace_picker_screen.dart';
import '../features/home/shell_screen.dart';

final appRouterProvider = Provider<GoRouter>((ref) {
  final auth = ref.watch(authControllerProvider);

  return GoRouter(
    initialLocation: '/splash',
    refreshListenable: _AuthRefresh(ref),
    redirect: (context, state) {
      final loggingIn = state.matchedLocation == '/login';
      final picking = state.matchedLocation == '/workspaces';
      final blocked = state.matchedLocation == '/pos-blocked';
      final splash = state.matchedLocation == '/splash';

      if (auth.isLoading) {
        return splash ? null : '/splash';
      }

      final session = auth.valueOrNull;
      if (session == null) {
        return loggingIn ? null : '/login';
      }

      final workspaces = session.workspaces;
      final needsPick = session.workspace == null && workspaces.length > 1;
      if (needsPick) {
        return picking ? null : '/workspaces';
      }

      if (session.workspace != null && session.posEnabled == false && !blocked) {
        // Soft gate: bootstrap will confirm; allow home then block there if needed.
      }

      if (loggingIn || splash || picking) {
        return '/home';
      }
      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (_, __) => const _Splash()),
      GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
      GoRoute(
        path: '/workspaces',
        builder: (_, __) => const WorkspacePickerScreen(),
      ),
      GoRoute(
        path: '/pos-blocked',
        builder: (_, __) => const PosBlockedScreen(),
      ),
      GoRoute(path: '/home', builder: (_, __) => const ShellScreen()),
    ],
  );
});

class _AuthRefresh extends ChangeNotifier {
  _AuthRefresh(this.ref) {
    ref.listen(authControllerProvider, (_, __) => notifyListeners());
  }

  final Ref ref;
}

class _Splash extends StatelessWidget {
  const _Splash();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            CircularProgressIndicator(),
            SizedBox(height: 16),
            Text('كاشير حاسم', style: TextStyle(fontWeight: FontWeight.w800)),
          ],
        ),
      ),
    );
  }
}
