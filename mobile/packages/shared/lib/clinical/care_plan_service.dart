import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config/api_config.dart';
import '../http/api_contract.dart';

/// Planes de tratamiento activos del paciente (`GET /clinical/care-plans/active`).
class CarePlanService {
  final String? authToken;

  CarePlanService({this.authToken});

  Future<String?> _effectiveToken() async {
    if (authToken != null && authToken!.isNotEmpty) return authToken;
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('auth_token');
  }

  /// Devuelve `{ success, data: List<Map>, message? }`.
  Future<Map<String, dynamic>> fetchActivePlans({
    bool includeActivities = true,
    int? subjectPersonaId,
  }) async {
    try {
      final token = await _effectiveToken();
      final params = <String, String>{
        'includeActivities': includeActivities ? '1' : '0',
        if (subjectPersonaId != null && subjectPersonaId > 0)
          'subject_persona_id': '$subjectPersonaId',
      };
      final uri = Uri.parse('${AppConfig.apiUrl}/clinical/care-plans/active')
          .replace(queryParameters: params);
      final response = await http
          .get(
            uri,
            headers: AppConfig.jsonHeaders(
              bearerToken: token,
              appClient: 'paciente-flutter',
            ),
          )
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));

      final list = unwrapApiContractList(json.decode(response.body), response.statusCode);
      return {'success': true, 'data': list};
    } on ApiContractException catch (e) {
      return {'success': false, 'data': <Map<String, dynamic>>[], 'message': e.message};
    } catch (e) {
      return {
        'success': false,
        'data': <Map<String, dynamic>>[],
        'message': e.toString(),
      };
    }
  }

  /// Detalle de un plan (`GET /clinical/care-plans/<id>`). Paciente recibe resumen con indicaciones.
  Future<Map<String, dynamic>> fetchById(int id) async {
    if (id <= 0) {
      return {'success': false, 'data': null, 'message': 'Plan inválido'};
    }
    try {
      final token = await _effectiveToken();
      final uri = Uri.parse('${AppConfig.apiUrl}/clinical/care-plans/$id');
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
}
