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

  Future<AuthSession> _sessionFromLoginPayload(Map<String, dynamic> data) async {
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
    return _sessionFromLoginPayload(data);
  }

  Future<AuthSession> socialLogin({
    required String provider,
    required String accessToken,
  }) async {
    final data = await _api.post('/auth/social', data: {
      'provider': provider,
      'access_token': accessToken,
      'device_name': 'كاشير حاسم',
      'device_type': 'cashier',
    });
    return _sessionFromLoginPayload(data);
  }

  Future<String> forgotPassword(String email) async {
    final data = await _api.post('/auth/forgot-password', data: {
      'email': email,
    });
    final message = data['message'];
    if (message is String && message.trim().isNotEmpty) return message;
    return 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني.';
  }

  Future<String> resetPassword({
    required String email,
    required String token,
    required String password,
    required String passwordConfirmation,
  }) async {
    final data = await _api.post('/auth/reset-password', data: {
      'email': email,
      'token': token,
      'password': password,
      'password_confirmation': passwordConfirmation,
    });
    final message = data['message'];
    if (message is String && message.trim().isNotEmpty) return message;
    return 'تم إعادة تعيين كلمة المرور بنجاح.';
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
            permissions: me['permissions'] is Map
                ? Map<String, dynamic>.from(me['permissions'] as Map)
                : {},
            posEnabled: me['pos_enabled'] == true,
            entitlements: me['entitlements'] is Map
                ? Map<String, dynamic>.from(me['entitlements'] as Map)
                : null,
          ),
        );
      } catch (_) {
        state = AsyncValue.data(restored);
      }
    } catch (e, st) {
      state = AsyncValue.error(e, st);
    }
  }

  Future<void> _applySession(AuthSession session) async {
    _ref.read(authTokenProvider.notifier).state = session.token;
    final wid = session.workspace?['id'];
    if (wid is int) {
      _ref.read(workspaceIdProvider.notifier).state = wid;
    }
    state = AsyncValue.data(session);
  }

  Future<void> login(String emailOrPhone, String password) async {
    // Keep previous session visible during login attempt — avoid splash remount loop.
    try {
      final session = await _ref.read(authRepositoryProvider).login(
            emailOrPhone: emailOrPhone,
            password: password,
          );
      await _applySession(session);
    } catch (e, st) {
      // Preserve logged-out state; surface error via thrown ApiException.
      if (state.valueOrNull == null) {
        state = AsyncValue.data(null);
      }
      Error.throwWithStackTrace(e, st);
    }
  }

  Future<void> socialLogin({
    required String provider,
    required String accessToken,
  }) async {
    try {
      final session = await _ref.read(authRepositoryProvider).socialLogin(
            provider: provider,
            accessToken: accessToken,
          );
      await _applySession(session);
    } catch (e, st) {
      if (state.valueOrNull == null) {
        state = AsyncValue.data(null);
      }
      Error.throwWithStackTrace(e, st);
    }
  }

  Future<String> forgotPassword(String email) {
    return _ref.read(authRepositoryProvider).forgotPassword(email);
  }

  Future<String> resetPassword({
    required String email,
    required String token,
    required String password,
    required String passwordConfirmation,
  }) {
    return _ref.read(authRepositoryProvider).resetPassword(
          email: email,
          token: token,
          password: password,
          passwordConfirmation: passwordConfirmation,
        );
  }

  Future<void> selectWorkspace(Map<String, dynamic> workspace) async {
    final rawId = workspace['id'];
    final id = rawId is int ? rawId : int.tryParse('$rawId');
    if (id == null) return;

    // Persist header workspace immediately so subsequent calls are scoped.
    _ref.read(workspaceIdProvider.notifier).state = id;
    await _ref.read(authRepositoryProvider).persistWorkspace(id);

    Map<String, dynamic> permissions = const {};
    Map<String, dynamic>? entitlements;
    var posEnabled = workspace['pos_enabled'] == true;
    Map<String, dynamic> resolvedWorkspace =
        Map<String, dynamic>.from(workspace);

    try {
      final switched = await _ref.read(cashierApiProvider).post(
        '/workspaces/switch',
        data: {
          'workspace_id': id,
          'device_name': 'كاشير حاسم',
          'device_type': 'cashier',
        },
      );
      if (switched['workspace'] is Map) {
        resolvedWorkspace =
            Map<String, dynamic>.from(switched['workspace'] as Map);
      }
      if (switched['permissions'] is Map) {
        permissions =
            Map<String, dynamic>.from(switched['permissions'] as Map);
      }
      if (switched['entitlements'] is Map) {
        entitlements =
            Map<String, dynamic>.from(switched['entitlements'] as Map);
      }
      if (switched.containsKey('pos_enabled')) {
        posEnabled = switched['pos_enabled'] == true;
      }
    } catch (_) {
      // Fall back to local selection; bootstrap will refresh permissions.
      final current = state.valueOrNull;
      permissions = current?.permissions ?? const {};
      entitlements = current?.entitlements;
      posEnabled =
          workspace['pos_enabled'] == true || (current?.posEnabled ?? false);
    }

    final current = state.valueOrNull;
    if (current != null) {
      state = AsyncValue.data(
        AuthSession(
          token: current.token,
          user: current.user,
          workspace: resolvedWorkspace,
          workspaces: current.workspaces,
          permissions:
              permissions.isNotEmpty ? permissions : current.permissions,
          posEnabled: posEnabled,
          entitlements: entitlements ?? current.entitlements,
        ),
      );
    }
  }

  /// Keep session permissions aligned with `/bootstrap` (source of truth).
  /// No-op when nothing meaningful changed — prevents auth refresh storms.
  void applyBootstrapSnapshot({
    required Map<String, dynamic> permissions,
    Map<String, dynamic>? workspace,
    Map<String, dynamic>? entitlements,
    bool? posEnabled,
  }) {
    final current = state.valueOrNull;
    if (current == null) return;

    final nextPerms =
        permissions.isNotEmpty ? permissions : current.permissions;
    final nextWorkspace = workspace ?? current.workspace;
    final nextEntitlements = entitlements ?? current.entitlements;
    final nextPos = posEnabled ?? current.posEnabled;

    if (_sameMap(current.permissions, nextPerms) &&
        _sameWorkspaceId(current.workspace, nextWorkspace) &&
        current.posEnabled == nextPos) {
      return;
    }

    state = AsyncValue.data(
      AuthSession(
        token: current.token,
        user: current.user,
        workspace: nextWorkspace,
        workspaces: current.workspaces,
        permissions: nextPerms,
        posEnabled: nextPos,
        entitlements: nextEntitlements,
      ),
    );
  }

  bool _sameWorkspaceId(Map<String, dynamic>? a, Map<String, dynamic>? b) {
    return a?['id'] == b?['id'];
  }

  bool _sameMap(Map<String, dynamic> a, Map<String, dynamic> b) {
    if (identical(a, b)) return true;
    if (a.length != b.length) return false;
    for (final entry in a.entries) {
      if (b[entry.key] != entry.value) return false;
    }
    return true;
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
