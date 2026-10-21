import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config/api_config.dart';
import '../http/api_contract.dart';

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

      final data = unwrapApiContract(
        json.decode(response.body),
        response.statusCode,
      );
      return {'success': true, 'data': data};
    } catch (e) {
      return {'success': false, 'data': null, 'message': e.toString()};
    }
  }

  Future<Map<String, dynamic>> save({
    required bool usaResumenHcEnAsistente,
  }) async {
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

      final data = unwrapApiContract(
        json.decode(response.body),
        response.statusCode,
      );
      return {
        'success': true,
        'data': data,
        if (data['message'] != null) 'message': data['message'],
      };
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }
}
