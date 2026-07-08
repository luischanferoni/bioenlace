import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import '../config/api_config.dart';

/// Resultado de comprobar el JWT de staff contra la API.
enum StaffSessionCheckResult {
  valid,
  invalid,
  networkError,
}

/// Validación de sesión staff (Personal de Salud).
abstract final class StaffSessionAuth {
  static const _appClient = 'bioenlace-personalsalud';

  /// GET /auth/yo — confirma que el Bearer sigue siendo aceptado.
  static Future<StaffSessionCheckResult> checkBearerToken(
    String token, {
    String appClient = _appClient,
  }) async {
    final bearer = token.trim();
    if (bearer.isEmpty) {
      return StaffSessionCheckResult.invalid;
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

      if (response.statusCode == 401 || response.statusCode == 403) {
        return StaffSessionCheckResult.invalid;
      }

      if (response.statusCode >= 200 && response.statusCode < 300) {
        final decoded = jsonDecode(response.body);
        if (decoded is Map && decoded['success'] == true) {
          return StaffSessionCheckResult.valid;
        }
        return StaffSessionCheckResult.invalid;
      }

      if (response.statusCode >= 500) {
        return StaffSessionCheckResult.networkError;
      }

      return StaffSessionCheckResult.invalid;
    } on SocketException {
      return StaffSessionCheckResult.networkError;
    } on http.ClientException {
      return StaffSessionCheckResult.networkError;
    } on FormatException {
      return StaffSessionCheckResult.invalid;
    } catch (_) {
      return StaffSessionCheckResult.networkError;
    }
  }

  /// Detecta errores HTTP de sesión expirada / token inválido en respuestas de la app.
  static bool isAuthSessionError(Object error) {
    final lower = error.toString().toLowerCase();
    return lower.contains('credenciales inválidas') ||
        lower.contains('token de autenticación') ||
        lower.contains('token inválido') ||
        lower.contains('usuario no autenticado') ||
        lower.contains('sesión expiró') ||
        lower.contains(' 401 ') ||
        lower.contains('http 401') ||
        lower.contains('http 403');
  }
}
