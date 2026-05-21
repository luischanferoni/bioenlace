import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config/api_config.dart';

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
  Future<Map<String, dynamic>> fetchActivePlans({bool includeActivities = true}) async {
    try {
      final token = await _effectiveToken();
      final q = includeActivities ? '?includeActivities=1' : '?includeActivities=0';
      final uri = Uri.parse('${AppConfig.apiUrl}/clinical/care-plans/active$q');
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
        return {'success': false, 'data': <Map<String, dynamic>>[], 'message': 'Respuesta inválida'};
      }

      if (response.statusCode == 200 && decoded['success'] == true) {
        final raw = decoded['data'];
        final list = raw is List
            ? raw.map((e) => Map<String, dynamic>.from(e as Map)).toList()
            : <Map<String, dynamic>>[];
        return {'success': true, 'data': list, 'message': decoded['message']};
      }

      return {
        'success': false,
        'data': <Map<String, dynamic>>[],
        'message': decoded['message'] ?? 'No se pudieron cargar los tratamientos',
      };
    } catch (e) {
      return {
        'success': false,
        'data': <Map<String, dynamic>>[],
        'message': e.toString(),
      };
    }
  }
}
