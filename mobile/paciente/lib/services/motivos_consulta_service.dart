import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

/// Servicio para la conversación de motivos de consulta (texto, audio, fotos).
/// Endpoints: motivos-consulta/mensajes, enviar, subir.
class MotivosConsultaService {
  final String? authToken;
  final String userId;
  final String? userName;

  MotivosConsultaService({required this.userId, this.authToken, this.userName});

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
    final uri = Uri.parse('${AppConfig.apiUrl}/audio/stt-config');
    final response = await http
        .get(uri, headers: _headers)
        .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
    final data = json.decode(response.body);
    if (response.statusCode != 200) {
      final error = data is Map ? data['error'] : null;
      throw Exception(
        (error is Map ? error['message']?.toString() : null) ??
            'Error de comunicación (${response.statusCode}).',
      );
    }
    final stt = data is Map ? data['data'] : null;
    if (stt is Map) {
      final config = Map<String, dynamic>.from(stt);
      if (config['device_enabled'] is bool &&
          config['server_enabled'] is bool) {
        return SttClientConfig.fromJson(config);
      }
    }
    throw const FormatException(
      'Falta una configuración STT válida en la respuesta.',
    );
  }

  Future<Map<String, dynamic>> getMessages(int consultaId) async {
    try {
      final uri = Uri.parse(
        '${AppConfig.apiUrl}/motivos-consulta/mensajes/$consultaId',
      );
      final response = await http
          .get(uri, headers: _headers)
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      final payload = unwrapApiContract(
        json.decode(response.body),
        response.statusCode,
      );
      final rawMsgs = payload['messages'] as List<dynamic>? ?? [];
      return {
        'success': true,
        'data': payload,
        'messages': normalizeChatMediaMessages(
          rawMsgs,
          mediaScope: 'motivos-consulta',
          encounterId: consultaId,
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

  Future<Map<String, dynamic>> sendMessage(int consultaId, String text) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/motivos-consulta/enviar');
      final body = {'encounter_id': consultaId, 'message': text};
      final response = await http
          .post(
            uri,
            headers: {..._headers, 'Content-Type': 'application/json'},
            body: json.encode(body),
          )
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      final data = unwrapApiContract(
        json.decode(response.body),
        response.statusCode,
      );
      return {'success': true, 'data': data};
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }

  /// Sube imagen o audio. [messageType] debe ser 'imagen' o 'audio'.
  Future<Map<String, dynamic>> uploadFile(
    int consultaId,
    XFile file, {
    required String messageType,
  }) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/motivos-consulta/subir');
      final request = http.MultipartRequest('POST', uri);
      request.headers.addAll(_multipartHeaders);
      request.fields['encounter_id'] = consultaId.toString();
      request.fields['message_type'] = messageType;
      request.files.add(await multipartFileFromXFile(file));

      final streamed = await request.send().timeout(Duration(seconds: 60));
      final response = await http.Response.fromStream(streamed);
      final payload = unwrapApiContract(
        json.decode(response.body),
        response.statusCode,
      );
      normalizeChatMediaMessage(
        payload,
        mediaScope: 'motivos-consulta',
        encounterId: consultaId,
      );
      return {'success': true, 'data': payload};
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }
}
