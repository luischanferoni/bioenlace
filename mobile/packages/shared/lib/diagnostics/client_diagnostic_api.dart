import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/api_config.dart';

/// Envío de logs de diagnóstico al backend (`POST /client-diagnostic/registrar`).
class ClientDiagnosticApi {
  ClientDiagnosticApi._();

  static String? authToken;
  static String appClient = 'flutter';

  static Future<bool> upload(List<Map<String, dynamic>> entries) async {
    final token = authToken?.trim() ?? '';
    if (token.isEmpty || entries.isEmpty) {
      return false;
    }
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/client-diagnostic/registrar');
      final res = await http
          .post(
            uri,
            headers: AppConfig.jsonHeaders(
              bearerToken: token,
              appClient: appClient,
            ),
            body: jsonEncode({
              'app_client': appClient,
              'entries': entries,
            }),
          )
          .timeout(const Duration(seconds: 25));
      if (res.statusCode < 200 || res.statusCode >= 300) {
        return false;
      }
      final decoded = jsonDecode(utf8.decode(res.bodyBytes));
      return decoded is Map && decoded['success'] == true;
    } catch (_) {
      return false;
    }
  }
}
