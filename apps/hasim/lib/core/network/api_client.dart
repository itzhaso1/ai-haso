import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:hasim/core/config/app_config.dart';
import 'package:hasim/core/network/api_exception.dart';
import 'package:hasim/core/network/api_response.dart';
import 'package:hasim/core/storage/prefs_store.dart';
import 'package:hasim/core/storage/secure_store.dart';
import 'package:hasim/core/utils/idempotency.dart';

typedef TokenReader = Future<String?> Function();
typedef WorkspaceReader = int? Function();

class ApiClient {
  ApiClient({
    required SecureStore secureStore,
    required PrefsStore prefsStore,
    Dio? dio,
  })  : _secureStore = secureStore,
        _prefsStore = prefsStore {
    final base = prefsStore.apiBaseOverride ?? AppConfig.apiBase;
    _dio = dio ??
        Dio(
          BaseOptions(
            baseUrl: '$base/api/mobile/v1',
            connectTimeout: const Duration(seconds: 20),
            receiveTimeout: const Duration(seconds: 30),
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
          ),
        );

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _secureStore.readToken();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          final workspaceId = _prefsStore.workspaceId;
          if (workspaceId != null) {
            options.headers['X-Workspace-Id'] = workspaceId.toString();
          }
          return handler.next(options);
        },
        onError: (error, handler) {
          return handler.next(error);
        },
      ),
    );
  }

  final SecureStore _secureStore;
  final PrefsStore _prefsStore;
  late final Dio _dio;

  Dio get raw => _dio;

  void updateBaseUrl(String apiBase) {
    _dio.options.baseUrl = '$apiBase/api/mobile/v1';
  }

  Future<ApiResponse<T>> get<T>(
    String path, {
    Map<String, dynamic>? query,
    T Function(dynamic raw)? mapData,
  }) async {
    try {
      final res = await _dio.get<Map<String, dynamic>>(path, queryParameters: query);
      return ApiResponse.fromJson(res.data ?? {}, mapData);
    } on DioException catch (e) {
      throw _mapDio(e);
    }
  }

  Future<ApiResponse<T>> post<T>(
    String path, {
    Object? body,
    Map<String, dynamic>? query,
    bool idempotent = false,
    T Function(dynamic raw)? mapData,
  }) async {
    try {
      final headers = <String, dynamic>{};
      if (idempotent) {
        headers['Idempotency-Key'] = newIdempotencyKey();
      }
      final res = await _dio.post<Map<String, dynamic>>(
        path,
        data: body,
        queryParameters: query,
        options: Options(headers: headers),
      );
      return ApiResponse.fromJson(res.data ?? {}, mapData);
    } on DioException catch (e) {
      throw _mapDio(e);
    }
  }

  Future<ApiResponse<T>> put<T>(
    String path, {
    Object? body,
    T Function(dynamic raw)? mapData,
  }) async {
    try {
      final res = await _dio.put<Map<String, dynamic>>(path, data: body);
      return ApiResponse.fromJson(res.data ?? {}, mapData);
    } on DioException catch (e) {
      throw _mapDio(e);
    }
  }

  Future<ApiResponse<T>> delete<T>(
    String path, {
    T Function(dynamic raw)? mapData,
  }) async {
    try {
      final res = await _dio.delete<Map<String, dynamic>>(path);
      return ApiResponse.fromJson(res.data ?? {}, mapData);
    } on DioException catch (e) {
      throw _mapDio(e);
    }
  }

  Future<ApiResponse<T>> upload<T>(
    String path, {
    required FormData formData,
    bool idempotent = true,
    T Function(dynamic raw)? mapData,
  }) async {
    try {
      final headers = <String, dynamic>{};
      if (idempotent) {
        headers['Idempotency-Key'] = newIdempotencyKey();
      }
      final res = await _dio.post<Map<String, dynamic>>(
        path,
        data: formData,
        options: Options(headers: headers, contentType: 'multipart/form-data'),
      );
      return ApiResponse.fromJson(res.data ?? {}, mapData);
    } on DioException catch (e) {
      throw _mapDio(e);
    }
  }

  ApiException _mapDio(DioException e) {
    final status = e.response?.statusCode;
    final data = e.response?.data;
    if (data is Map<String, dynamic>) {
      final message = data['message']?.toString();
      if (message != null && message.isNotEmpty) {
        return ApiException(
          message,
          statusCode: status,
          errors: data['errors'] is Map<String, dynamic>
              ? data['errors'] as Map<String, dynamic>
              : null,
        );
      }
    }

    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout ||
        e.type == DioExceptionType.connectionError) {
      return ApiException('تعذر الاتصال بالخادم. تحقق من الشبكة.', statusCode: status);
    }

    if (status == 401) {
      return ApiException('انتهت جلسة تسجيل الدخول.', statusCode: status);
    }
    if (status == 403) {
      return ApiException('لا تملك صلاحية تنفيذ هذا الإجراء.', statusCode: status);
    }
    if (status == 404) {
      return ApiException('العنصر المطلوب غير موجود.', statusCode: status);
    }
    if (status == 422) {
      return ApiException('بيانات غير صالحة.', statusCode: status);
    }
    if (status == 429) {
      return ApiException('محاولات كثيرة. حاول لاحقاً.', statusCode: status);
    }

    debugPrint('API error: $e');
    return ApiException('حدث خطأ غير متوقع.', statusCode: status);
  }
}
