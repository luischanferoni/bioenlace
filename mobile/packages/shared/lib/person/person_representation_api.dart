import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config/api_config.dart';

/// Cliente HTTP para `/api/v1/person-representation/*`.
class PersonRepresentationApi {
  final String? authToken;
  final String appClient;

  PersonRepresentationApi({
    this.authToken,
    this.appClient = 'paciente-flutter',
  });

  Future<String?> _token() async {
    if (authToken != null && authToken!.isNotEmpty) return authToken;
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('auth_token');
  }

  Future<Map<String, dynamic>> _post(
    String path, {
    Map<String, dynamic>? body,
  }) async {
    final token = await _token();
    final uri = Uri.parse('${AppConfig.apiUrl}$path');
    final response = await http
        .post(
          uri,
          headers: AppConfig.jsonHeaders(bearerToken: token, appClient: appClient),
          body: json.encode(body ?? {}),
        )
        .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
    return _decode(response);
  }

  Future<Map<String, dynamic>> _getPost(
    String path, {
    Map<String, dynamic>? body,
  }) async {
    final token = await _token();
    final uri = Uri.parse('${AppConfig.apiUrl}$path');
    final response = await http
        .post(
          uri,
          headers: AppConfig.jsonHeaders(bearerToken: token, appClient: appClient),
          body: json.encode(body ?? {}),
        )
        .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
    return _decode(response);
  }

  Map<String, dynamic> _decode(http.Response response) {
    try {
      final raw = json.decode(response.body);
      if (raw is Map<String, dynamic>) {
        if (response.statusCode >= 200 && response.statusCode < 300) {
          return raw;
        }
        return {
          'success': false,
          'message': raw['message'] ?? 'Error en representación (${response.statusCode})',
        };
      }
    } catch (_) {}
    return {
      'success': false,
      'message': 'Respuesta inválida (${response.statusCode})',
    };
  }

  Future<Map<String, dynamic>> fetchPacientesACargo() {
    return _getPost('/person-representation/pacientes-a-cargo');
  }

  Future<Map<String, dynamic>> fetchVinculosComoTutor({String? status}) {
    return _getPost(
      '/person-representation/mis-vinculos-como-tutor',
      body: status != null ? {'status': status} : null,
    );
  }

  Future<Map<String, dynamic>> fetchMisRepresentantes() {
    return _getPost('/person-representation/mis-representantes');
  }

  Future<Map<String, dynamic>> solicitarMenorComoTutor(Map<String, dynamic> body) {
    return _post('/person-representation/solicitar-menor-como-tutor', body: body);
  }

  Future<Map<String, dynamic>> designarRepresentante(Map<String, dynamic> body) {
    return _post('/person-representation/designar-representante', body: body);
  }

  Future<Map<String, dynamic>> revocarRepresentante(Map<String, dynamic> body) {
    return _post('/person-representation/revocar-representante', body: body);
  }

  Future<Map<String, dynamic>> establecerSujetoPaciente(int? subjectPersonaId) {
    return _post(
      '/person-representation/establecer-sujeto-paciente',
      body: subjectPersonaId != null && subjectPersonaId > 0
          ? {'subject_persona_id': subjectPersonaId}
          : {},
    );
  }

  Future<Map<String, dynamic>> fetchPreferencias() {
    return _getPost('/person-representation/preferencias-como-paciente');
  }

  Future<Map<String, dynamic>> guardarPreferencias({required bool notify}) {
    return _post(
      '/person-representation/preferencias-como-paciente',
      body: {'notify_on_representative_action': notify},
    );
  }
}
