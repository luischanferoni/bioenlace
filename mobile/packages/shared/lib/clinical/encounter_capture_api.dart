import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/api_config.dart';

/// API captura clínica: analizar y guardar encounter.
class EncounterCaptureApi {
  EncounterCaptureApi({this.authToken});

  String? authToken;

  Map<String, String> get _jsonHeaders {
    final h = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
    if (authToken != null && authToken!.isNotEmpty) {
      h['Authorization'] = 'Bearer $authToken';
    }
    return h;
  }

  /// POST /api/v1/clinical/encounter/analizar
  Future<Map<String, dynamic>> analizar({
    required String consulta,
    int? idConfiguracion,
    int? idPersona,
    int? idConsulta,
    String? parent,
    int? parentId,
    Map<String, dynamic>? stt,
    String? audioBase64,
    bool sttForceServer = false,
  }) async {
    final body = <String, dynamic>{
      'consulta': consulta,
      if (idConfiguracion != null) 'id_configuracion': idConfiguracion,
      if (idPersona != null) 'id_persona': idPersona,
      if (idConsulta != null) 'id_consulta': idConsulta,
      if (parent != null) 'parent': parent,
      if (parentId != null) 'parent_id': parentId,
      if (stt != null) 'stt': stt,
      if (audioBase64 != null && audioBase64.isNotEmpty) 'audio': audioBase64,
      if (sttForceServer) 'stt_force_server': true,
    };

    final uri = Uri.parse('${AppConfig.apiUrl}/clinical/encounter/analizar');
    final response = await http.post(
      uri,
      headers: _jsonHeaders,
      body: json.encode(body),
    );
    final decoded = json.decode(response.body);
    if (decoded is! Map<String, dynamic>) {
      throw Exception('Respuesta inválida al analizar');
    }
    if (response.statusCode >= 400 || decoded['success'] != true) {
      throw Exception(
        decoded['message']?.toString() ?? 'No se pudo analizar la consulta',
      );
    }
    return decoded;
  }

  /// POST /api/v1/audio/transcribir
  Future<String> transcribirServidor({
    required String audioBase64,
    bool forceServer = true,
  }) async {
    final uri = Uri.parse('${AppConfig.apiUrl}/audio/transcribir');
    final response = await http.post(
      uri,
      headers: _jsonHeaders,
      body: json.encode({
        'audio': audioBase64,
        'stt': {'force_server': forceServer, 'provenance': 'device'},
      }),
    );
    final decoded = json.decode(response.body);
    if (decoded is! Map<String, dynamic>) {
      throw Exception('Respuesta inválida al transcribir');
    }
    if (decoded['success'] != true) {
      throw Exception(decoded['error']?.toString() ?? 'Transcripción fallida');
    }
    return (decoded['texto_transcrito'] ?? '').toString();
  }

  /// POST /api/v1/clinical/encounter/guardar
  Future<Map<String, dynamic>> guardar({
    required int idPersona,
    required Map<String, dynamic> datosExtraidos,
    String? parent,
    int? parentId,
    int? idConfiguracion,
    int? encounterId,
    required String textoOriginal,
    required String textoProcesado,
  }) async {
    final body = <String, String>{
      'id_persona': '$idPersona',
      'texto_original': textoOriginal,
      'texto_procesado': textoProcesado,
      'datosExtraidos': json.encode(datosExtraidos),
      if (parent != null) 'parent': parent,
      if (parentId != null) 'parent_id': '$parentId',
      if (idConfiguracion != null) 'id_configuracion': '$idConfiguracion',
      if (encounterId != null) 'id_consulta': '$encounterId',
    };

    final uri = Uri.parse('${AppConfig.apiUrl}/clinical/encounter/guardar');
    final response = await http.post(uri, headers: {
      ..._jsonHeaders,
      'Content-Type': 'application/x-www-form-urlencoded',
    }, body: body);

    final decoded = json.decode(response.body);
    if (decoded is! Map<String, dynamic>) {
      throw Exception('Respuesta inválida al guardar');
    }
    if (response.statusCode >= 400 || decoded['success'] != true) {
      throw Exception(decoded['message']?.toString() ?? 'Error al guardar');
    }
    return decoded;
  }
}
