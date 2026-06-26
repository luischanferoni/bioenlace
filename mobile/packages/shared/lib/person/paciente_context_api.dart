import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config/api_config.dart';

/// Cliente HTTP para `/api/v1/paciente-contexto/*`.
class PacienteContextApi {
  final String? authToken;
  final String appClient;

  PacienteContextApi({
    this.authToken,
    this.appClient = 'paciente-flutter',
  });

  Future<String?> _token() async {
    if (authToken != null && authToken!.isNotEmpty) return authToken;
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('auth_token');
  }

  Future<Map<String, dynamic>> _request(
    String path, {
    String method = 'GET',
    Map<String, dynamic>? body,
  }) async {
    final token = await _token();
    final uri = Uri.parse('${AppConfig.apiUrl}$path');
    final headers = AppConfig.jsonHeaders(
      bearerToken: token,
      appClient: appClient,
    );
    final timeout = Duration(seconds: AppConfig.httpTimeoutSeconds);
    final http.Response response;
    if (method == 'POST') {
      response = await http
          .post(uri, headers: headers, body: json.encode(body ?? {}))
          .timeout(timeout);
    } else {
      response = await http.get(uri, headers: headers).timeout(timeout);
    }
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
          'message': raw['message'] ??
              'Error de contexto paciente (${response.statusCode})',
        };
      }
    } catch (_) {}
    return {
      'success': false,
      'message': 'Respuesta inválida (${response.statusCode})',
    };
  }

  Future<Map<String, dynamic>> fetchContexto() {
    return _request('/paciente-contexto/obtener-como-paciente');
  }

  Future<Map<String, dynamic>> actualizarContexto({
    String? sectorSalud,
    int? idProvinciaContexto,
  }) {
    final body = <String, dynamic>{};
    if (sectorSalud != null) body['sector_salud'] = sectorSalud;
    if (idProvinciaContexto != null) {
      body['id_provincia_contexto'] = idProvinciaContexto;
    }
    return _request(
      '/paciente-contexto/actualizar-como-paciente',
      method: 'POST',
      body: body,
    );
  }

  Future<Map<String, dynamic>> sugerirProvincias() {
    return _request('/paciente-contexto/sugerir-provincias-como-paciente');
  }

  Future<Map<String, dynamic>> buscarRecursoProvincial({
    String? query,
    String? tipo,
  }) {
    final body = <String, dynamic>{};
    if (query != null && query.isNotEmpty) body['q'] = query;
    if (tipo != null && tipo.isNotEmpty) body['tipo'] = tipo;
    return _request(
      '/paciente-contexto/buscar-recurso-provincial-como-paciente',
      method: 'POST',
      body: body,
    );
  }
}
