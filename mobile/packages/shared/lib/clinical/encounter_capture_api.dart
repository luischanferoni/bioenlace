import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import '../http/bioenlace_http_trace.dart';
import 'stt_client_config.dart';

/// API captura clínica: analizar y guardar encounter.
class EncounterCaptureApi {
  EncounterCaptureApi({this.authToken});

  String? authToken;

  Map<String, String> get _jsonHeaders => AppConfig.jsonHeaders(
        bearerToken: authToken,
        appClient: 'personalsalud-flutter',
      );

  /// GET /api/v1/audio/stt-config
  Future<SttClientConfig> fetchSttConfig() async {
    final uri = Uri.parse('${AppConfig.apiUrl}/audio/stt-config');
    final response = await http
        .get(uri, headers: _jsonHeaders)
        .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
    BioenlaceHttpTrace.logResponse('audio/stt-config', response);
    final decoded = json.decode(response.body);
    if (decoded is! Map<String, dynamic> || decoded['success'] != true) {
      return SttClientConfig.defaults;
    }
    final stt = decoded['stt'];
    if (stt is Map<String, dynamic>) {
      return SttClientConfig.fromJson(stt);
    }
    return SttClientConfig.defaults;
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
    Map<String, dynamic>? userPerTabConfig,
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
      if (userPerTabConfig != null && userPerTabConfig.isNotEmpty)
        'userPerTabConfig': userPerTabConfig,
    };

    final uri = Uri.parse('${AppConfig.apiUrl}/clinical/encounter/analizar');
    debugPrint(
      '[HTTP encounter/analizar] POST $uri id_persona=$idPersona '
      'parent=$parent/$parentId consulta_len=${consulta.length}',
    );
    final response = await http
        .post(uri, headers: _jsonHeaders, body: json.encode(body))
        .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
    BioenlaceHttpTrace.logResponse('encounter/analizar', response);

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
    final response = await http
        .post(
          uri,
          headers: _jsonHeaders,
          body: json.encode({
            'audio': audioBase64,
            'stt': {'force_server': forceServer, 'provenance': 'device'},
          }),
        )
        .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
    BioenlaceHttpTrace.logResponse('audio/transcribir', response);
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
    Map<String, dynamic>? analisisDatosExtraidos,
    String? analysisCacheToken,
    String? parent,
    int? parentId,
    int? idConfiguracion,
    int? encounterId,
    required String textoOriginal,
    required String textoProcesado,
    Map<String, dynamic>? userPerTabConfig,
  }) async {
    final body = <String, dynamic>{
      'id_persona': idPersona,
      'subject_persona_id': idPersona,
      'texto_original': textoOriginal,
      'texto_procesado': textoProcesado,
      'datosExtraidos': datosExtraidos,
      if (analisisDatosExtraidos != null && analisisDatosExtraidos.isNotEmpty)
        'analisis_datos_extraidos': analisisDatosExtraidos,
      if (analysisCacheToken != null && analysisCacheToken.isNotEmpty)
        'analysis_cache_token': analysisCacheToken,
      if (parent != null) 'parent': parent,
      if (parentId != null) 'parent_id': parentId,
      if (idConfiguracion != null) 'id_configuracion': idConfiguracion,
      if (encounterId != null) 'id_consulta': encounterId,
      if (userPerTabConfig != null && userPerTabConfig.isNotEmpty)
        'userPerTabConfig': userPerTabConfig,
    };

    final uri = Uri.parse('${AppConfig.apiUrl}/clinical/encounter/guardar');
    debugPrint(
      '[HTTP encounter/guardar] POST $uri id_persona=$idPersona '
      'encounter_id=$encounterId parent=$parent/$parentId '
      'staged_keys=${datosExtraidos.keys.toList()} '
      'backup_keys=${analisisDatosExtraidos?.keys.toList() ?? []} '
      'cache_token=${analysisCacheToken != null && analysisCacheToken.isNotEmpty ? 'si' : 'no'}',
    );
    final response = await http
        .post(uri, headers: _jsonHeaders, body: json.encode(body))
        .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
    BioenlaceHttpTrace.logResponse('encounter/guardar', response, maxBodyChars: 8000);

    Map<String, dynamic>? decoded;
    try {
      final raw = json.decode(response.body);
      if (raw is Map<String, dynamic>) {
        decoded = raw;
      } else if (raw is Map) {
        decoded = Map<String, dynamic>.from(raw);
      }
    } catch (_) {
      decoded = null;
    }
    if (decoded == null) {
      throw Exception(
        'Respuesta inválida al guardar (HTTP ${response.statusCode})',
      );
    }
    if (response.statusCode >= 400 || decoded['success'] != true) {
      final diag = decoded['diagnostico_guardar'];
      debugPrint('[HTTP encounter/guardar FAIL] diagnostico=$diag');
      throw Exception(decoded['message']?.toString() ?? 'Error al guardar');
    }
    debugPrint(
      '[HTTP encounter/guardar OK] encounter_id=${decoded['encounter_id']} '
      'persistido=${decoded['persistido']} '
      'diagnostico_guardar=${decoded['diagnostico_guardar']} '
      'log_id=${decoded['log_id']}',
    );
    return decoded;
  }
}
