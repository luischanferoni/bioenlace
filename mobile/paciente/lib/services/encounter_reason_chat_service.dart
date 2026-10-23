import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

/// Servicio para la conversación de motivos de consulta (texto, audio, fotos).
/// Endpoints: encounter-reasons/mensajes, enviar, subir.
class EncounterReasonChatService {
  final String? authToken;
  final String userId;
  final String? userName;

  EncounterReasonChatService({
    required this.userId,
    this.authToken,
    this.userName,
  });

  ApiClient get _api =>
      ApiClient(authToken: authToken, appClient: 'paciente-flutter');

  Map<String, String> get _headers => AppConfig.jsonHeaders(
    bearerToken: authToken,
    appClient: 'paciente-flutter',
  );

  /// Multipart: sin Content-Type (lo fija el boundary).
  Map<String, String> get _multipartHeaders {
    final h = Map<String, String>.from(_headers);
    h.remove('Content-Type');
    return h;
  }

  Future<SttClientConfig> fetchSttConfig() async {
    final config = await _api.getData('/audio/stt-config');
    if (config['device_enabled'] is bool && config['server_enabled'] is bool) {
      return SttClientConfig.fromJson(config);
    }
    throw const FormatException(
      'Falta una configuración STT válida en la respuesta.',
    );
  }

  Future<Map<String, dynamic>> getMessages(int encounterId) async {
    try {
      final payload = await _api.getData(
        '/encounter-reasons/mensajes/$encounterId',
      );
      final rawMsgs = payload['messages'] as List<dynamic>? ?? [];
      return {
        'success': true,
        'data': payload,
        'messages': normalizeChatMediaMessages(
          rawMsgs,
          mediaScope: 'encounter-reasons',
          encounterId: encounterId,
        ),
        'input_abierto': payload['input_abierto'] == true,
        'motivos_resumen': payload['motivos_resumen'],
        'cierre_en': payload['cierre_en'],
        'chat_guide': payload['chat_guide'],
      };
    } catch (e) {
      return {'success': false, 'message': e.toString(), 'messages': []};
    }
  }

  Future<Map<String, dynamic>> sendMessage(int encounterId, String text) async {
    try {
      final data = await _api.postData(
        '/encounter-reasons/enviar',
        body: {'encounter_id': encounterId, 'message': text},
      );
      return {'success': true, 'data': data};
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }

  /// Sube imagen o audio. [messageType] debe ser 'imagen' o 'audio'.
  Future<Map<String, dynamic>> uploadFile(
    int encounterId,
    XFile file, {
    required String messageType,
    String? transcript,
    Map<String, dynamic>? sttMeta,
  }) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/encounter-reasons/subir');
      final request = http.MultipartRequest('POST', uri);
      request.headers.addAll(_multipartHeaders);
      request.fields['encounter_id'] = encounterId.toString();
      request.fields['message_type'] = messageType;
      if (transcript != null && transcript.trim().isNotEmpty) {
        request.fields['transcript'] = transcript.trim();
      }
      if (sttMeta != null && sttMeta.isNotEmpty) {
        request.fields['stt_meta'] = json.encode(sttMeta);
      }
      request.files.add(await multipartFileFromXFile(file));

      final streamed = await request.send().timeout(Duration(seconds: 60));
      final response = await http.Response.fromStream(streamed);
      final payload = unwrapApiContract(
        json.decode(response.body),
        response.statusCode,
      );
      normalizeChatMediaMessage(
        payload,
        mediaScope: 'encounter-reasons',
        encounterId: encounterId,
      );
      return {'success': true, 'data': payload};
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }
}
