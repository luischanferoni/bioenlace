import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/api_config.dart';

/// Acceso demo médico efímero (POST /licencia/demo-acceso-mobile).
///
/// Misma plantilla DEV + seed que el CTA institucional web, pero responde JWT
/// con contexto AMB listo para Personal de Salud (sin password ni enter_url).
class DemoSandboxStaffAuth {
  /// true si el entorno tiene demo habilitada y perfil staff.
  static Future<bool> probeEnabled({
    String appClient = 'bioenlace-personalsalud',
  }) async {
    final uri = Uri.parse('${AppConfig.apiUrl}/licencia/demo-perfiles');
    try {
      final response = await http
          .get(
            uri,
            headers: AppConfig.jsonHeaders(appClient: appClient),
          )
          .timeout(const Duration(seconds: 15));
      final data = jsonDecode(response.body);
      if (response.statusCode < 200 ||
          response.statusCode >= 300 ||
          data is! Map ||
          data['success'] != true) {
        return false;
      }
      final payload = data['data'];
      if (payload is! Map) return false;
      if (payload['enabled'] != true) return false;
      final items = payload['items'];
      if (items is! List || items.isEmpty) return false;
      return items.any((it) {
        if (it is! Map) return false;
        return (it['role']?.toString() ?? '') == 'staff';
      });
    } catch (_) {
      return false;
    }
  }

  static Future<Map<String, dynamic>> enterAsMedico({
    String appClient = 'bioenlace-personalsalud',
    String? email,
  }) async {
    final uri = Uri.parse('${AppConfig.apiUrl}/licencia/demo-acceso-mobile');
    final body = <String, dynamic>{
      // Honeypot (debe ir vacío).
      'website': '',
      'role': 'staff',
    };
    final emailTrim = email?.trim() ?? '';
    if (emailTrim.isNotEmpty) {
      body['email'] = emailTrim;
    }

    final response = await http
        .post(
          uri,
          headers: AppConfig.jsonHeaders(appClient: appClient),
          body: jsonEncode(body),
        )
        .timeout(const Duration(seconds: AppConfig.httpTimeoutSeconds));

    final data = jsonDecode(response.body);
    if (response.statusCode >= 200 &&
        response.statusCode < 300 &&
        data is Map &&
        data['success'] == true) {
      return Map<String, dynamic>.from(data);
    }

    final message = data is Map
        ? (data['message'] ?? 'No se pudo abrir la demo')
        : 'No se pudo abrir la demo';
    throw Exception(message.toString());
  }
}
