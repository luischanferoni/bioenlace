import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

class PacientesListadoResponse {
  final String kind; // turnos | internados | guardias
  final List<dynamic> data;

  PacientesListadoResponse({required this.kind, required this.data});
}

class PacientesService {
  String? authToken;
  String? userId; // desarrollo/simulación

  PacientesService({this.authToken, this.userId});

  Map<String, String> get _headers {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (authToken != null) {
      headers['Authorization'] = 'Bearer $authToken';
    }
    return headers;
  }

  Future<PacientesListadoResponse> getListado({required String fecha}) async {
    final queryParams = <String, String>{'fecha': fecha};
    if (userId != null &&
        (authToken == null || authToken!.isEmpty || authToken!.startsWith('simulated_'))) {
      queryParams['user_id'] = userId!;
    }

    final uri = Uri.parse('${AppConfig.apiUrl}/pacientes').replace(queryParameters: queryParams);
    final response = await http.get(uri, headers: _headers);

    final bodyTrimmed = response.body.trim();
    if (bodyTrimmed.startsWith('<!DOCTYPE') || bodyTrimmed.startsWith('<html')) {
      throw Exception('La API devolvió HTML en lugar de JSON. Verifique autenticación y endpoint: $uri');
    }

    if (response.statusCode != 200) {
      try {
        final errorData = json.decode(response.body);
        throw Exception(errorData['message'] ?? 'Error al obtener pacientes (${response.statusCode})');
      } catch (_) {
        throw Exception('Error ${response.statusCode}: ${response.body}');
      }
    }

    final decoded = json.decode(response.body);
    if (decoded is! Map<String, dynamic>) {
      throw Exception('Respuesta inválida de la API');
    }
    if (decoded['success'] != true) {
      throw Exception(decoded['message'] ?? 'Error al obtener pacientes');
    }

    final kind = (decoded['kind'] as String?) ?? '';
    final data = (decoded['data'] as List<dynamic>?) ?? [];
    if (kind.isEmpty) {
      throw Exception('La API no devolvió kind');
    }
    return PacientesListadoResponse(kind: kind, data: data);
  }
}

