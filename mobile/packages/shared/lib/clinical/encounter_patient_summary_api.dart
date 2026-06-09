import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config/api_config.dart';

/// API JSON de resúmenes de atención publicados (paciente).
class EncounterPatientSummaryApi {
  final String? authToken;

  EncounterPatientSummaryApi({this.authToken});

  Future<String?> _effectiveToken() async {
    if (authToken != null && authToken!.isNotEmpty) {
      return authToken;
    }
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('auth_token');
  }

  Future<Map<String, dynamic>> list({
    int limit = 20,
    int offset = 0,
    int? subjectPersonaId,
  }) async {
    final token = await _effectiveToken();
    final params = <String, String>{
      'limit': '$limit',
      'offset': '$offset',
      if (subjectPersonaId != null && subjectPersonaId > 0)
        'subject_persona_id': '$subjectPersonaId',
    };
    final uri = Uri.parse(
      '${AppConfig.apiUrl}/clinical/encounter/listar-atenciones-como-paciente',
    ).replace(queryParameters: params);
    final response = await http
        .get(
          uri,
          headers: AppConfig.jsonHeaders(
            bearerToken: token,
            appClient: 'paciente-flutter',
          ),
        )
        .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));

    return _decode(response);
  }

  Future<Map<String, dynamic>> fetchDetail(int encounterId) async {
    final token = await _effectiveToken();
    final uri = Uri.parse(
      '${AppConfig.apiUrl}/clinical/encounter/ver-resumen-como-paciente?encounter_id=$encounterId',
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

    return _decodeData(response);
  }

  Future<Map<String, dynamic>> fetchLatest() async {
    final token = await _effectiveToken();
    final uri = Uri.parse(
      '${AppConfig.apiUrl}/clinical/encounter/ultima-atencion-como-paciente',
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

    return _decodeData(response);
  }

  Map<String, dynamic> _decode(http.Response response) {
    final decoded = json.decode(response.body);
    if (decoded is! Map<String, dynamic>) {
      return {'success': false, 'message': 'Respuesta inválida'};
    }
    return decoded;
  }

  Map<String, dynamic> _decodeData(http.Response response) {
    final decoded = _decode(response);
    if (response.statusCode == 200 && decoded['success'] == true && decoded['data'] is Map) {
      return Map<String, dynamic>.from(decoded['data'] as Map);
    }
    throw Exception(decoded['message']?.toString() ?? 'No se pudo cargar el resumen');
  }
}
