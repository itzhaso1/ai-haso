import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/auth/auth_controller.dart';
import '../core/theme/hasim_colors.dart';
import '../core/theme/hasim_theme.dart';
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
      final splash = state.matchedLocation == '/splash';

      if (auth.isLoading) {
        return splash ? null : '/splash';
      }

      final session = auth.valueOrNull;
      if (session == null) {
        return loggingIn ? null : '/login';
      }

      final needsPick =
          session.workspace == null && session.workspaces.length > 1;
      if (needsPick) {
        return picking ? null : '/workspaces';
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
    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [HasimColors.brandSoft, Colors.white],
          ),
        ),
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 88,
                height: 88,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: HasimColors.border),
                  boxShadow: const [
                    BoxShadow(
                      color: Color(0x1406C2A4),
                      blurRadius: 24,
                      offset: Offset(0, 8),
                    ),
                  ],
                ),
                child: const Text(
                  'ح',
                  style: TextStyle(
                    fontSize: 42,
                    fontWeight: FontWeight.w900,
                    color: HasimColors.brand,
                  ),
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'كاشير حاسم',
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w900,
                  color: HasimColors.ink,
                ),
              ),
              const SizedBox(height: 18),
              const SizedBox(
                width: 28,
                height: 28,
                child: CircularProgressIndicator(
                  strokeWidth: 2.5,
                  color: HasimColors.brand,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Expose theme helper for MaterialApp.
ThemeData hasimCashierTheme() => HasimTheme.light();
