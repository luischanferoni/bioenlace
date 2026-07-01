import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/api_config.dart';

/// Activación de cuenta staff con código presencial (POST /auth/activar-cuenta).
class StaffAccountActivation {
  static Future<void> activate({
    required String username,
    required String activationCode,
    required String password,
    String appClient = 'bioenlace-personalsalud',
  }) async {
    final uri = Uri.parse('${AppConfig.apiUrl}/auth/activar-cuenta');
    final response = await http
        .post(
          uri,
          headers: AppConfig.jsonHeaders(appClient: appClient),
          body: jsonEncode({
            'username': username.trim(),
            'activation_code': activationCode.trim(),
            'password': password,
          }),
        )
        .timeout(const Duration(seconds: AppConfig.httpTimeoutSeconds));

    final data = jsonDecode(response.body);
    if (response.statusCode >= 200 &&
        response.statusCode < 300 &&
        data is Map &&
        data['success'] == true) {
      return;
    }

    final message = data is Map
        ? (data['message'] ?? 'No se pudo activar la cuenta')
        : 'No se pudo activar la cuenta';
    throw Exception(message.toString());
  }
}
