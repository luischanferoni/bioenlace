import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import '../http/api_contract.dart';

/// Login usuario/contraseña para revisión de tiendas (POST /auth/login-revision).
class PlayReviewAuth {
  static Future<Map<String, dynamic>> login({
    required String username,
    required String password,
    String appClient = 'bioenlace-flutter',
  }) async {
    final uri = Uri.parse('${AppConfig.apiUrl}/auth/login-revision');
    final response = await http
        .post(
          uri,
          headers: AppConfig.jsonHeaders(appClient: appClient),
          body: jsonEncode({'username': username.trim(), 'password': password}),
        )
        .timeout(const Duration(seconds: AppConfig.httpTimeoutSeconds));

    final data = unwrapApiContract(
      jsonDecode(response.body),
      response.statusCode,
    );
    if (data['token'] is! String || (data['token'] as String).isEmpty) {
      throw const FormatException();
    }
    return data;
  }
}
