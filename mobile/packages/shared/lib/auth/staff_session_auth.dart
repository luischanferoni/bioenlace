import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config/api_config.dart';

/// Resultado de comprobar el JWT contra la API.
enum BearerSessionCheckResult {
  valid,
  invalid,
  networkError,
}

@Deprecated('Use BearerSessionCheckResult')
typedef StaffSessionCheckResult = BearerSessionCheckResult;

/// Validación / refresh de sesión JWT (GET /auth/yo, POST /auth/refrescar-token).
abstract final class BearerSessionAuth {
  static const appClientPersonalsalud = 'bioenlace-personalsalud';
  static const appClientPaciente = 'paciente-flutter';

  /// Renovar si faltan menos de este margen para `exp`.
  static const refreshSkew = Duration(hours: 2);

  /// GET /auth/yo — confirma que el Bearer sigue siendo aceptado.
  ///
  /// Solo [BearerSessionCheckResult.invalid] implica borrar el JWT.
  /// 403 / 4xx / 5xx / red / cuerpo raro → [BearerSessionCheckResult.networkError]
  /// para no desalojar una sesión válida por un fallo parcial.
  static Future<BearerSessionCheckResult> checkBearerToken(
    String token, {
    String appClient = appClientPersonalsalud,
  }) async {
    final bearer = token.trim();
    if (bearer.isEmpty) {
      return BearerSessionCheckResult.invalid;
    }

    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/auth/yo');
      final response = await http
          .get(
            uri,
            headers: AppConfig.jsonHeaders(
              bearerToken: bearer,
              appClient: appClient,
            ),
          )
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));

      // Autenticación fallida de verdad (JWT rechazado).
      if (response.statusCode == 401) {
        return BearerSessionCheckResult.invalid;
      }

      // Permiso / ruta / contexto: no borrar sesión.
      if (response.statusCode == 403) {
        return BearerSessionCheckResult.networkError;
      }

      if (response.statusCode >= 200 && response.statusCode < 300) {
        try {
          final decoded = jsonDecode(response.body);
          if (decoded is Map && decoded['success'] == true) {
            return BearerSessionCheckResult.valid;
          }
        } on FormatException {
          return BearerSessionCheckResult.networkError;
        }
        // 2xx con body inesperado: conservar token.
        return BearerSessionCheckResult.networkError;
      }

      if (response.statusCode >= 500) {
        return BearerSessionCheckResult.networkError;
      }

      // Otros 4xx (404, 429, …): no invalidar sesión.
      return BearerSessionCheckResult.networkError;
    } on SocketException {
      return BearerSessionCheckResult.networkError;
    } on http.ClientException {
      return BearerSessionCheckResult.networkError;
    } on FormatException {
      return BearerSessionCheckResult.networkError;
    } catch (_) {
      return BearerSessionCheckResult.networkError;
    }
  }

  /// Detecta errores HTTP de **autenticación** (JWT). No incluye 403 de permisos.
  static bool isAuthSessionError(Object error) {
    final lower = error.toString().toLowerCase();

    // 403 = permiso/contexto, no sesión. Antes provocaba logout falso.
    if (lower.contains('http 403') ||
        lower.contains(' 403 ') ||
        lower.contains('status: 403') ||
        lower.contains('statuscode: 403')) {
      return false;
    }

    // Mensaje de permiso genérico (post-separación 401/403).
    if (lower.contains('no tenés permiso') ||
        lower.contains('no tenes permiso')) {
      return false;
    }

    return lower.contains('credenciales inválidas') ||
        lower.contains('credenciales invalidas') ||
        lower.contains('token de autenticación') ||
        lower.contains('token de autenticacion') ||
        lower.contains('token inválido') ||
        lower.contains('token invalido') ||
        lower.contains('usuario no autenticado') ||
        lower.contains('sesión expiró') ||
        lower.contains('sesion expiro') ||
        lower.contains('http 401') ||
        lower.contains(' 401 ') ||
        lower.contains('status: 401') ||
        lower.contains('statuscode: 401');
  }

  /// Lee `exp` del JWT sin verificar firma (solo para renovación preventiva).
  static DateTime? readExpiry(String token) {
    try {
      final parts = token.trim().split('.');
      if (parts.length < 2) return null;
      final normalized = base64Url.normalize(parts[1]);
      final payload =
          jsonDecode(utf8.decode(base64Url.decode(normalized)));
      if (payload is! Map) return null;
      final exp = payload['exp'];
      final seconds = exp is int
          ? exp
          : (exp is num ? exp.toInt() : int.tryParse('$exp'));
      if (seconds == null || seconds <= 0) return null;
      return DateTime.fromMillisecondsSinceEpoch(seconds * 1000, isUtc: true);
    } catch (_) {
      return null;
    }
  }

  static bool shouldRefreshProactively(String token) {
    final exp = readExpiry(token);
    if (exp == null) return false;
    final remaining = exp.difference(DateTime.now().toUtc());
    return remaining <= refreshSkew;
  }

  /// POST /auth/refrescar-token. Devuelve el nuevo JWT o null si no se pudo renovar.
  static Future<String?> refreshBearerToken(
    String token, {
    String appClient = appClientPersonalsalud,
  }) async {
    final bearer = token.trim();
    if (bearer.isEmpty) return null;

    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/auth/refrescar-token');
      final response = await http
          .post(
            uri,
            headers: AppConfig.jsonHeaders(
              bearerToken: bearer,
              appClient: appClient,
            ),
            body: jsonEncode({'token': bearer}),
          )
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));

      if (response.statusCode < 200 || response.statusCode >= 300) {
        return null;
      }
      final decoded = jsonDecode(response.body);
      if (decoded is! Map || decoded['success'] != true) {
        return null;
      }
      final data = decoded['data'];
      if (data is Map && data['token'] is String) {
        final next = (data['token'] as String).trim();
        return next.isEmpty ? null : next;
      }
      if (decoded['token'] is String) {
        final next = (decoded['token'] as String).trim();
        return next.isEmpty ? null : next;
      }
      return null;
    } catch (_) {
      return null;
    }
  }

  /// Si el JWT está cerca de expirar, renueva y persiste en `auth_token`.
  /// Devuelve el token vigente (nuevo o el mismo).
  static Future<String> ensureFreshBearerToken(
    String token, {
    String appClient = appClientPersonalsalud,
    String prefsKey = 'auth_token',
  }) async {
    final bearer = token.trim();
    if (bearer.isEmpty) return bearer;
    if (!shouldRefreshProactively(bearer)) {
      return bearer;
    }
    final next = await refreshBearerToken(bearer, appClient: appClient);
    if (next == null || next.isEmpty) {
      return bearer;
    }
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(prefsKey, next);
    return next;
  }
}

@Deprecated('Use BearerSessionAuth')
typedef StaffSessionAuth = BearerSessionAuth;
