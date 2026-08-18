import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config/api_config.dart';

/// GET/PUT /asistente/preferencias-como-paciente
class AssistantPatientPreferencesApi {
  final String? authToken;

  AssistantPatientPreferencesApi({this.authToken});

  Future<String?> _effectiveToken() async {
    if (authToken != null && authToken!.isNotEmpty) {
      return authToken;
    }
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('auth_token');
  }

  Future<Map<String, dynamic>> fetch() async {
    try {
      final token = await _effectiveToken();
      final uri = Uri.parse(
        '${AppConfig.apiUrl}/asistente/preferencias-como-paciente',
      );
      final response = await http
          .get(
            uri,
            headers: AppConfig.jsonHeaders(
              bearerToken: token,
              appClient: 'paciente-flutter',
            ),
          )
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));

      final decoded = json.decode(response.body);
      if (decoded is! Map<String, dynamic>) {
        return {'success': false, 'data': null};
      }
      if (response.statusCode == 200 && decoded['success'] == true) {
        return {'success': true, 'data': decoded['data']};
      }
      return {'success': false, 'data': null, 'message': decoded['message']};
    } catch (e) {
      return {'success': false, 'data': null, 'message': e.toString()};
    }
  }

  Future<Map<String, dynamic>> save({required bool usaResumenHcEnAsistente}) async {
    try {
      final token = await _effectiveToken();
      final uri = Uri.parse(
        '${AppConfig.apiUrl}/asistente/preferencias-como-paciente',
      );
      final response = await http
          .put(
            uri,
            headers: AppConfig.jsonHeaders(
              bearerToken: token,
              appClient: 'paciente-flutter',
            ),
            body: json.encode({
              'usa_resumen_hc_en_asistente': usaResumenHcEnAsistente,
            }),
          )
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));

      final decoded = json.decode(response.body);
      if (decoded is! Map<String, dynamic>) {
        return {'success': false};
      }
      return {
        'success': response.statusCode == 200 && decoded['success'] == true,
        'data': decoded['data'],
        'message': decoded['message'],
      };
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }
}
