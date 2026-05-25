import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config/api_config.dart';

/// GET /clinical/care-plans/recordatorios-como-paciente
class CarePlanReminderApi {
  final String? authToken;

  CarePlanReminderApi({this.authToken});

  Future<String?> _effectiveToken() async {
    if (authToken != null && authToken!.isNotEmpty) {
      return authToken;
    }
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('auth_token');
  }

  Future<Map<String, dynamic>> fetchSchedule({int? carePlanId}) async {
    try {
      final token = await _effectiveToken();
      var path = '/clinical/care-plans/recordatorios-como-paciente';
      if (carePlanId != null && carePlanId > 0) {
        path += '?care_plan_id=$carePlanId';
      }
      final uri = Uri.parse('${AppConfig.apiUrl}$path');
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
        return {'success': false, 'data': null, 'message': 'Respuesta inválida'};
      }

      if (response.statusCode == 200 && decoded['success'] == true) {
        return {
          'success': true,
          'data': decoded['data'],
          'message': decoded['message'],
        };
      }

      return {
        'success': false,
        'data': null,
        'message': decoded['message'] ?? 'No se pudo cargar recordatorios',
      };
    } catch (e) {
      return {'success': false, 'data': null, 'message': e.toString()};
    }
  }

  Future<Map<String, dynamic>> fetchPreferences() async {
    try {
      final token = await _effectiveToken();
      final uri = Uri.parse(
        '${AppConfig.apiUrl}/clinical/care-plans/preferencias-recordatorios-como-paciente',
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

  Future<Map<String, dynamic>> savePreferences(Map<String, dynamic> body) async {
    try {
      final token = await _effectiveToken();
      final uri = Uri.parse(
        '${AppConfig.apiUrl}/clinical/care-plans/preferencias-recordatorios-como-paciente',
      );
      final response = await http
          .put(
            uri,
            headers: AppConfig.jsonHeaders(
              bearerToken: token,
              appClient: 'paciente-flutter',
            ),
            body: json.encode(body),
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
