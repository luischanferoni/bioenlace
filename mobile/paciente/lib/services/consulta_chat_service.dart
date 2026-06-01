import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

/// Servicio para chat con médico (consulta-chat API: mensajes, envío texto, upload archivos).
class ConsultaChatService {
  final String? authToken;
  final String userId;
  final String? userName;

  ConsultaChatService({
    required this.userId,
    this.authToken,
    this.userName,
  });

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        if (authToken != null && authToken!.isNotEmpty) 'Authorization': 'Bearer $authToken',
      };

  Future<Map<String, dynamic>> getMessages(int consultaId) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/consulta-chat/mensajes/$consultaId');
      final response = await http.get(uri, headers: _headers)
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      final data = json.decode(response.body);
      if (response.statusCode == 200 && data['success'] == true) {
        final raw = data['data']?['messages'] as List<dynamic>? ?? [];
        return {
          'success': true,
          'data': data['data'],
          'messages': normalizeChatMediaMessages(
            raw,
            mediaScope: 'consulta-chat',
            encounterId: consultaId,
          ),
        };
      }
      return {'success': false, 'message': data['message'] ?? 'Error al cargar mensajes', 'messages': []};
    } catch (e) {
      return {'success': false, 'message': e.toString(), 'messages': []};
    }
  }

  Future<Map<String, dynamic>> sendMessage(int consultaId, String text, {String userRole = 'paciente'}) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/consulta-chat/enviar');
      final body = {
        'consulta_id': consultaId,
        'message': text,
        'user_id': userId,
        'user_role': userRole,
      };
      final response = await http.post(
        uri,
        headers: {..._headers, 'Content-Type': 'application/json'},
        body: json.encode(body),
      ).timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      final data = json.decode(response.body);
      if (response.statusCode == 200 && data['success'] == true) {
        return {'success': true, 'data': data['data']};
      }
      return {'success': false, 'message': data['message'] ?? 'Error al enviar mensaje'};
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }

  /// Sube un archivo (imagen, audio, video, documento).
  /// [messageType] debe ser: imagen, audio, video, documento.
  Future<Map<String, dynamic>> uploadFile(
    int consultaId,
    XFile file, {
    required String messageType,
  }) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/consulta-chat/subir');
      final request = http.MultipartRequest('POST', uri);
      request.headers.addAll(_headers);
      request.fields['consulta_id'] = consultaId.toString();
      request.fields['user_id'] = userId;
      request.fields['message_type'] = messageType;
      request.files.add(await multipartFileFromXFile(file));

      final streamed = await request.send().timeout(Duration(seconds: 60));
      final response = await http.Response.fromStream(streamed);
      final data = json.decode(response.body);

      if (response.statusCode == 200 && data['success'] == true) {
        final payload = data['data'];
        if (payload is Map<String, dynamic>) {
          normalizeChatMediaMessage(
            payload,
            mediaScope: 'consulta-chat',
            encounterId: consultaId,
          );
        } else if (payload is Map) {
          final copy = Map<String, dynamic>.from(payload);
          normalizeChatMediaMessage(
            copy,
            mediaScope: 'consulta-chat',
            encounterId: consultaId,
          );
          data['data'] = copy;
        }
        return {'success': true, 'data': data['data']};
      }
      return {'success': false, 'message': data['message'] ?? 'Error al subir archivo'};
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }
}
