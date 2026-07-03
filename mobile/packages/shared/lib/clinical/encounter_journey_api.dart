import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config/api_config.dart';

/// Estado del recorrido pre/post consulta (`GET|POST /api/v1/encounter-journey/estado`).
class EncounterJourneyApi {
  final String? authToken;
  final String appClient;

  EncounterJourneyApi({
    this.authToken,
    this.appClient = 'paciente-flutter',
  });

  Future<String?> _effectiveToken() async {
    if (authToken != null && authToken!.isNotEmpty) {
      return authToken;
    }
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('auth_token');
  }

  Future<Map<String, dynamic>?> fetchEstado({
    required int turnoId,
    int? subjectPersonaId,
  }) async {
    final token = await _effectiveToken();
    if (token == null || token.isEmpty) return null;

    final params = <String, String>{
      'turno_id': '$turnoId',
      if (subjectPersonaId != null && subjectPersonaId > 0)
        'subject_persona_id': '$subjectPersonaId',
    };
    final uri = Uri.parse('${AppConfig.apiUrl}/encounter-journey/estado')
        .replace(queryParameters: params);

    final response = await http
        .get(
          uri,
          headers: AppConfig.jsonHeaders(
            bearerToken: token,
            appClient: appClient,
          ),
        )
        .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));

    if (response.statusCode != 200) return null;
    final data = json.decode(response.body);
    if (data is! Map<String, dynamic> || data['success'] != true) {
      return null;
    }
    return data;
  }
}
