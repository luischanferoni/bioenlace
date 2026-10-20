import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config/api_config.dart';
import '../http/api_contract.dart';

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

      final data = unwrapApiContract(json.decode(response.body), response.statusCode);
      return {'success': true, 'data': data, 'message': data['message']};
    } on ApiContractException catch (e) {
      return {'success': false, 'data': null, 'message': e.message};
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

      final data = unwrapApiContract(json.decode(response.body), response.statusCode);
      return {'success': true, 'data': data};
    } on ApiContractException catch (e) {
      return {'success': false, 'data': null, 'message': e.message};
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

      final data = unwrapApiContract(json.decode(response.body), response.statusCode);
      return {'success': true, 'data': data, 'message': data['message']};
    } on ApiContractException catch (e) {
      return {'success': false, 'message': e.message};
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }
}
