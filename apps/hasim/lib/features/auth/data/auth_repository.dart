
import 'package:hasim/core/models/models.dart';
import 'package:hasim/core/network/api_client.dart';
import 'package:hasim/core/network/api_exception.dart';

class AuthRepository {
  AuthRepository(this._api);
  final ApiClient _api;

  Future<LoginResult> login({
    required String emailOrPhone,
    required String password,
    int? workspaceId,
    String deviceName = 'حاسم Flutter',
    String deviceType = 'mobile',
  }) async {
    final res = await _api.post<LoginResult>(
      '/auth/login',
      body: {
        'email_or_phone': emailOrPhone,
        'password': password,
        if (workspaceId != null) 'workspace_id': workspaceId,
        'device_name': deviceName,
        'device_type': deviceType,
      },
      mapData: (raw) {
        final map = asMap(raw);
        if (map == null) throw ApiException('استجابة تسجيل الدخول غير صالحة.');
        return LoginResult.fromJson(map);
      },
    );
    if (!res.success || res.data == null || res.data!.token.isEmpty) {
      throw ApiException(res.message ?? 'تعذر تسجيل الدخول.');
    }
    return res.data!;
  }

  Future<void> logout() async {
    await _api.post('/auth/logout');
  }

  Future<({UserModel user, WorkspaceModel? workspace, List<WorkspaceModel> workspaces})> me() async {
    final res = await _api.get('/auth/me');
    final map = asMap(res.data);
    if (map == null) throw ApiException(res.message ?? 'تعذر جلب الملف الشخصي.');
    final workspaces = asMapList(map['workspaces']).map(WorkspaceModel.fromJson).toList();
    return (
      user: UserModel.fromJson(map['user'] as Map<String, dynamic>),
      workspace: map['workspace'] is Map<String, dynamic>
          ? WorkspaceModel.fromJson(map['workspace'] as Map<String, dynamic>)
          : null,
      workspaces: workspaces,
    );
  }
}
