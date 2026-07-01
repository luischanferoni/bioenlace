import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/api_config.dart';

/// Login usuario/contraseña staff (POST /auth/login-credenciales).
class StaffCredentialAuth {
  static Future<Map<String, dynamic>> login({
    required String username,
    required String password,
    String appClient = 'bioenlace-personalsalud',
  }) async {
    final uri = Uri.parse('${AppConfig.apiUrl}/auth/login-credenciales');
    final response = await http
        .post(
          uri,
          headers: AppConfig.jsonHeaders(appClient: appClient),
          body: jsonEncode({
            'username': username.trim(),
            'password': password,
          }),
        )
        .timeout(const Duration(seconds: AppConfig.httpTimeoutSeconds));

    final data = jsonDecode(response.body);
    if (response.statusCode >= 200 &&
        response.statusCode < 300 &&
        data is Map &&
        data['success'] == true) {
      return Map<String, dynamic>.from(data);
    }

    final message =
        data is Map ? (data['message'] ?? 'Error de acceso') : 'Error de acceso';
    throw Exception(message.toString());
  }
}
