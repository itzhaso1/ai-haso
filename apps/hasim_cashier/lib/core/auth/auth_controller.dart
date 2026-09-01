import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../api/cashier_api.dart';

const _tokenKey = 'cashier_token';
const _workspaceKey = 'cashier_workspace_id';

class AuthSession {
  const AuthSession({
    required this.token,
    required this.user,
    required this.workspaces,
    this.workspace,
    this.permissions = const {},
    this.posEnabled = false,
    this.entitlements,
  });

  final String token;
  final Map<String, dynamic> user;
  final Map<String, dynamic>? workspace;
  final List<Map<String, dynamic>> workspaces;
  final Map<String, dynamic> permissions;
  final bool posEnabled;
  final Map<String, dynamic>? entitlements;

  String get userName => (user['name'] as String?) ?? '';
}

class AuthRepository {
  AuthRepository(this._api, this._storage);

  final CashierApiClient _api;
  final FlutterSecureStorage _storage;

  Future<AuthSession?> restore() async {
    final token = await _storage.read(key: _tokenKey);
    if (token == null || token.isEmpty) return null;
    final workspaceRaw = await _storage.read(key: _workspaceKey);
    return AuthSession(
      token: token,
      user: const {},
      workspace: workspaceRaw == null
          ? null
          : {'id': int.tryParse(workspaceRaw)},
      workspaces: const [],
      posEnabled: true,
    );
  }

  Future<AuthSession> login({
    required String emailOrPhone,
    required String password,
  }) async {
    final data = await _api.post('/auth/login', data: {
      'email_or_phone': emailOrPhone,
      'password': password,
      'device_name': 'كاشير حاسم',
      'device_type': 'cashier',
    });

    final token = data['token'] as String? ?? '';
    await _storage.write(key: _tokenKey, value: token);

    final workspace = data['workspace'] is Map
        ? Map<String, dynamic>.from(data['workspace'] as Map)
        : null;
    if (workspace?['id'] != null) {
      await _storage.write(
        key: _workspaceKey,
        value: workspace!['id'].toString(),
      );
    }

    final workspaces = <Map<String, dynamic>>[];
    final rawWorkspaces = data['workspaces'];
    if (rawWorkspaces is List) {
      for (final item in rawWorkspaces) {
        if (item is Map) workspaces.add(Map<String, dynamic>.from(item));
      }
    }

    return AuthSession(
      token: token,
      user: data['user'] is Map
          ? Map<String, dynamic>.from(data['user'] as Map)
          : {},
      workspace: workspace,
      workspaces: workspaces,
      permissions: data['permissions'] is Map
          ? Map<String, dynamic>.from(data['permissions'] as Map)
          : {},
      posEnabled: data['pos_enabled'] == true,
      entitlements: data['entitlements'] is Map
          ? Map<String, dynamic>.from(data['entitlements'] as Map)
          : null,
    );
  }

  Future<void> logout() async {
    try {
      await _api.post('/auth/logout');
    } catch (_) {
      // Still clear local session.
    }
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _workspaceKey);
  }

  Future<void> persistWorkspace(int workspaceId) async {
    await _storage.write(key: _workspaceKey, value: workspaceId.toString());
  }
}

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(
    ref.watch(cashierApiProvider),
    ref.watch(secureStorageProvider),
  );
});

class AuthController extends StateNotifier<AsyncValue<AuthSession?>> {
  AuthController(this._ref) : super(const AsyncValue.loading()) {
    _bootstrap();
  }

  final Ref _ref;

  Future<void> _bootstrap() async {
    try {
      final restored = await _ref.read(authRepositoryProvider).restore();
      if (restored == null) {
        state = const AsyncValue.data(null);
        return;
      }
      _ref.read(authTokenProvider.notifier).state = restored.token;
      final wid = restored.workspace?['id'];
      if (wid is int) {
        _ref.read(workspaceIdProvider.notifier).state = wid;
      }
      // Refresh profile
      try {
        final me = await _ref.read(cashierApiProvider).get('/auth/me');
        final workspaces = <Map<String, dynamic>>[];
        if (me['workspaces'] is List) {
          for (final item in me['workspaces'] as List) {
            if (item is Map) workspaces.add(Map<String, dynamic>.from(item));
          }
        }
        state = AsyncValue.data(
          AuthSession(
            token: restored.token,
            user: me['user'] is Map
                ? Map<String, dynamic>.from(me['user'] as Map)
                : {},
            workspace: me['workspace'] is Map
                ? Map<String, dynamic>.from(me['workspace'] as Map)
                : restored.workspace,
            workspaces: workspaces,
            posEnabled: true,
          ),
        );
      } catch (_) {
        state = AsyncValue.data(restored);
      }
    } catch (e, st) {
      state = AsyncValue.error(e, st);
    }
  }

  Future<void> login(String emailOrPhone, String password) async {
    state = const AsyncValue.loading();
    try {
      final session =
          await _ref.read(authRepositoryProvider).login(
                emailOrPhone: emailOrPhone,
                password: password,
              );
      _ref.read(authTokenProvider.notifier).state = session.token;
      final wid = session.workspace?['id'];
      if (wid is int) {
        _ref.read(workspaceIdProvider.notifier).state = wid;
      }
      state = AsyncValue.data(session);
    } catch (e, st) {
      state = AsyncValue.error(e, st);
      rethrow;
    }
  }

  Future<void> selectWorkspace(Map<String, dynamic> workspace) async {
    final id = workspace['id'];
    if (id is! int) return;
    _ref.read(workspaceIdProvider.notifier).state = id;
    await _ref.read(authRepositoryProvider).persistWorkspace(id);
    final current = state.valueOrNull;
    if (current != null) {
      state = AsyncValue.data(
        AuthSession(
          token: current.token,
          user: current.user,
          workspace: workspace,
          workspaces: current.workspaces,
          permissions: current.permissions,
          posEnabled: workspace['pos_enabled'] == true || current.posEnabled,
          entitlements: current.entitlements,
        ),
      );
    }
  }

  Future<void> logout() async {
    await _ref.read(authRepositoryProvider).logout();
    _ref.read(authTokenProvider.notifier).state = null;
    _ref.read(workspaceIdProvider.notifier).state = null;
    state = const AsyncValue.data(null);
  }
}

final authControllerProvider =
    StateNotifierProvider<AuthController, AsyncValue<AuthSession?>>((ref) {
  return AuthController(ref);
});
