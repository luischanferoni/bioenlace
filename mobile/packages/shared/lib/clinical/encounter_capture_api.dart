import 'dart:convert';

import 'package:cross_file/cross_file.dart';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import '../http/bioenlace_http_trace.dart';
import '../media/chat_media_upload.dart';
import 'stt_client_config.dart';

/// API captura clínica: pipeline por etapas + analizar/guardar legacy.
class EncounterCaptureApi {
  EncounterCaptureApi({this.authToken});

  String? authToken;

  Map<String, String> get _jsonHeaders => AppConfig.jsonHeaders(
        bearerToken: authToken,
        appClient: 'personalsalud-flutter',
      );

  /// Multipart: sin Content-Type (lo fija el boundary).
  Map<String, String> get _multipartHeaders {
    final h = Map<String, String>.from(_jsonHeaders);
    h.remove('Content-Type');
    return h;
  }

  Map<String, dynamic> _decodeMap(http.Response response, String label) {
    BioenlaceHttpTrace.logResponse(label, response);
    final decoded = json.decode(response.body);
    if (decoded is! Map<String, dynamic>) {
      throw Exception('Respuesta inválida ($label)');
    }
    if (response.statusCode >= 400 || decoded['success'] != true) {
      throw Exception(
        decoded['message']?.toString() ?? 'Error en $label',
      );
    }
    return decoded;
  }

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

  /// POST /api/v1/clinical/encounter/captura/crear-o-subir
  Future<Map<String, dynamic>> capturaCrearOSubir({
    required String clientCaptureId,
    required int idPersona,
    String? parent,
    int? parentId,
    String? texto,
    Map<String, dynamic>? stt,
    String? audioPath,
    bool sttForceServer = false,
    Map<String, dynamic>? userPerTabConfig,
  }) async {
    final uri =
        Uri.parse('${AppConfig.apiUrl}/clinical/encounter/captura/crear-o-subir');
    final request = http.MultipartRequest('POST', uri);
    request.headers.addAll(_multipartHeaders);
    request.fields['client_capture_id'] = clientCaptureId;
    request.fields['id_persona'] = idPersona.toString();
    if (parent != null) request.fields['parent'] = parent;
    if (parentId != null) request.fields['parent_id'] = parentId.toString();
    if (texto != null && texto.isNotEmpty) {
      request.fields['consulta'] = texto;
    }
    if (sttForceServer) request.fields['stt_force_server'] = '1';
    if (stt != null && stt.isNotEmpty) {
      request.fields['stt'] = json.encode(stt);
    }
    if (userPerTabConfig != null && userPerTabConfig.isNotEmpty) {
      request.fields['userPerTabConfig'] = json.encode(userPerTabConfig);
    }
    if (audioPath != null && audioPath.isNotEmpty) {
      request.files.add(
        await multipartFileFromXFile(XFile(audioPath), field: 'file'),
      );
    }
    debugPrint(
      '[HTTP captura/crear-o-subir] POST $uri client=$clientCaptureId '
      'has_audio=${audioPath != null}',
    );
    final streamed = await request
        .send()
        .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
    final response = await http.Response.fromStream(streamed);
    return _decodeMap(response, 'captura/crear-o-subir');
  }

  /// POST /api/v1/clinical/encounter/captura/transcribir
  Future<Map<String, dynamic>> capturaTranscribir({
    String? clientCaptureId,
    int? captureId,
    bool force = false,
    Map<String, dynamic>? userPerTabConfig,
  }) async {
    final body = <String, dynamic>{
      if (clientCaptureId != null) 'client_capture_id': clientCaptureId,
      if (captureId != null) 'capture_id': captureId,
      if (force) 'force': true,
      if (userPerTabConfig != null && userPerTabConfig.isNotEmpty)
        'userPerTabConfig': userPerTabConfig,
    };
    final uri =
        Uri.parse('${AppConfig.apiUrl}/clinical/encounter/captura/transcribir');
    final response = await http
        .post(uri, headers: _jsonHeaders, body: json.encode(body))
        .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
    return _decodeMap(response, 'captura/transcribir');
  }

  /// POST /api/v1/clinical/encounter/captura/analizar
  Future<Map<String, dynamic>> capturaAnalizar({
    String? clientCaptureId,
    int? captureId,
    String? consulta,
    bool force = false,
    Map<String, dynamic>? userPerTabConfig,
  }) async {
    final body = <String, dynamic>{
      if (clientCaptureId != null) 'client_capture_id': clientCaptureId,
      if (captureId != null) 'capture_id': captureId,
      if (consulta != null && consulta.isNotEmpty) 'consulta': consulta,
      if (force) 'force': true,
      if (userPerTabConfig != null && userPerTabConfig.isNotEmpty)
        'userPerTabConfig': userPerTabConfig,
    };
    final uri =
        Uri.parse('${AppConfig.apiUrl}/clinical/encounter/captura/analizar');
    final response = await http
        .post(uri, headers: _jsonHeaders, body: json.encode(body))
        .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
    return _decodeMap(response, 'captura/analizar');
  }

  /// POST /api/v1/clinical/encounter/captura/guardar
  Future<Map<String, dynamic>> capturaGuardar({
    String? clientCaptureId,
    int? captureId,
    required Map<String, dynamic> datosExtraidos,
    Map<String, dynamic>? analisisDatosExtraidos,
    List<String>? stagedItemIds,
    String? textoOriginal,
    String? textoProcesado,
    Map<String, dynamic>? userPerTabConfig,
  }) async {
    final body = <String, dynamic>{
      if (clientCaptureId != null) 'client_capture_id': clientCaptureId,
      if (captureId != null) 'capture_id': captureId,
      'datosExtraidos': datosExtraidos,
      if (analisisDatosExtraidos != null && analisisDatosExtraidos.isNotEmpty)
        'analisis_datos_extraidos': analisisDatosExtraidos,
      if (stagedItemIds != null) 'staged_item_ids': stagedItemIds,
      if (textoOriginal != null) 'texto_original': textoOriginal,
      if (textoProcesado != null) 'texto_procesado': textoProcesado,
      if (userPerTabConfig != null && userPerTabConfig.isNotEmpty)
        'userPerTabConfig': userPerTabConfig,
    };
    final uri =
        Uri.parse('${AppConfig.apiUrl}/clinical/encounter/captura/guardar');
    final response = await http
        .post(uri, headers: _jsonHeaders, body: json.encode(body))
        .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
    return _decodeMap(response, 'captura/guardar');
  }

  /// GET /api/v1/clinical/encounter/captura/listar
  Future<List<Map<String, dynamic>>> capturaListar({
    required int idPersona,
    String? parent,
    int? parentId,
  }) async {
    final qs = <String, String>{
      'id_persona': idPersona.toString(),
      if (parent != null) 'parent': parent,
      if (parentId != null) 'parent_id': parentId.toString(),
    };
    final uri = Uri.parse('${AppConfig.apiUrl}/clinical/encounter/captura/listar')
        .replace(queryParameters: qs);
    final response = await http
        .get(uri, headers: _jsonHeaders)
        .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
    final decoded = _decodeMap(response, 'captura/listar');
    final items = decoded['items'];
    if (items is! List) return const [];
    return items
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }

  /// GET /api/v1/clinical/encounter/captura/ver
  Future<Map<String, dynamic>> capturaVer({
    String? clientCaptureId,
    int? captureId,
  }) async {
    final qs = <String, String>{
      if (clientCaptureId != null) 'client_capture_id': clientCaptureId,
      if (captureId != null) 'capture_id': captureId.toString(),
    };
    final uri = Uri.parse('${AppConfig.apiUrl}/clinical/encounter/captura/ver')
        .replace(queryParameters: qs);
    final response = await http
        .get(uri, headers: _jsonHeaders)
        .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
    return _decodeMap(response, 'captura/ver');
  }

  /// POST /api/v1/clinical/encounter/captura/descartar
  Future<void> capturaDescartar({
    String? clientCaptureId,
    int? captureId,
  }) async {
    final body = <String, dynamic>{
      if (clientCaptureId != null) 'client_capture_id': clientCaptureId,
      if (captureId != null) 'capture_id': captureId,
    };
    final uri =
        Uri.parse('${AppConfig.apiUrl}/clinical/encounter/captura/descartar');
    final response = await http
        .post(uri, headers: _jsonHeaders, body: json.encode(body))
        .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
    _decodeMap(response, 'captura/descartar');
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
    return _decodeMap(response, 'encounter/analizar');
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
    final decoded = _decodeMap(response, 'audio/transcribir');
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
