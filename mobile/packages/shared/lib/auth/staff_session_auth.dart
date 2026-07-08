import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import '../config/api_config.dart';

/// Resultado de comprobar el JWT contra la API.
enum BearerSessionCheckResult {
  valid,
  invalid,
  networkError,
}

@Deprecated('Use BearerSessionCheckResult')
typedef StaffSessionCheckResult = BearerSessionCheckResult;

/// Validación de sesión JWT (GET /auth/yo).
abstract final class BearerSessionAuth {
  static const appClientPersonalsalud = 'bioenlace-personalsalud';
  static const appClientPaciente = 'paciente-flutter';

  /// GET /auth/yo — confirma que el Bearer sigue siendo aceptado.
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

      if (response.statusCode == 401 || response.statusCode == 403) {
        return BearerSessionCheckResult.invalid;
      }

      if (response.statusCode >= 200 && response.statusCode < 300) {
        final decoded = jsonDecode(response.body);
        if (decoded is Map && decoded['success'] == true) {
          return BearerSessionCheckResult.valid;
        }
        return BearerSessionCheckResult.invalid;
      }

      if (response.statusCode >= 500) {
        return BearerSessionCheckResult.networkError;
      }

      return BearerSessionCheckResult.invalid;
    } on SocketException {
      return BearerSessionCheckResult.networkError;
    } on http.ClientException {
      return BearerSessionCheckResult.networkError;
    } on FormatException {
      return BearerSessionCheckResult.invalid;
    } catch (_) {
      return BearerSessionCheckResult.networkError;
    }
  }

  /// Detecta errores HTTP de sesión expirada / token inválido.
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

@Deprecated('Use BearerSessionAuth')
typedef StaffSessionAuth = BearerSessionAuth;
