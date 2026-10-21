import 'dart:convert';

import 'package:cross_file/cross_file.dart';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

/// Service for patient encounter chat messages and media uploads.
class EncounterChatService {
  final String? authToken;
  final String userId;
  final String? userName;

  EncounterChatService({
    required this.userId,
    this.authToken,
    this.userName,
  });

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        if (authToken != null && authToken!.isNotEmpty) 'Authorization': 'Bearer $authToken',
      };

  Future<Map<String, dynamic>> getMessages(int encounterId) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/consulta-chat/mensajes/$encounterId');
      final response = await http.get(uri, headers: _headers)
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      final payload = unwrapApiContract(json.decode(response.body), response.statusCode);
      final raw = payload['messages'] as List<dynamic>? ?? [];
      return {
        'success': true,
        'data': payload,
        'messages': normalizeChatMediaMessages(
          raw,
          mediaScope: 'consulta-chat',
          encounterId: encounterId,
        ),
      };
    } catch (e) {
      return {'success': false, 'message': e.toString(), 'messages': []};
    }
  }

  Future<Map<String, dynamic>> sendMessage(int encounterId, String text, {String userRole = 'medico'}) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/consulta-chat/enviar');
      final body = {
        'encounter_id': encounterId,
        'message': text,
        'user_id': userId,
        'user_role': userRole,
      };
      final response = await http.post(
        uri,
        headers: {..._headers, 'Content-Type': 'application/json'},
        body: json.encode(body),
      ).timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      final data = unwrapApiContract(json.decode(response.body), response.statusCode);
      return {'success': true, 'data': data};
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }

  Future<Map<String, dynamic>> uploadFile(
    int encounterId,
    XFile file, {
    required String messageType,
  }) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/consulta-chat/subir');
      final request = http.MultipartRequest('POST', uri);
      request.headers.addAll(_headers);
      request.fields['encounter_id'] = encounterId.toString();
      request.fields['user_id'] = userId;
      request.fields['message_type'] = messageType;
      request.files.add(await multipartFileFromXFile(file));

      final streamed = await request.send().timeout(Duration(seconds: 60));
      final response = await http.Response.fromStream(streamed);
      final payload = unwrapApiContract(json.decode(response.body), response.statusCode);
      normalizeChatMediaMessage(
        payload,
        mediaScope: 'consulta-chat',
        encounterId: encounterId,
      );
      return {'success': true, 'data': payload};
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }

  Future<Map<String, dynamic>> uploadBytes(
    int encounterId,
    List<int> bytes, {
    required String filename,
    required String messageType,
  }) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/consulta-chat/subir');
      final request = http.MultipartRequest('POST', uri);
      request.headers.addAll(_headers);
      request.fields['encounter_id'] = encounterId.toString();
      request.fields['user_id'] = userId;
      request.fields['message_type'] = messageType;
      request.files.add(await multipartFileFromBytes(bytes, filename: filename));

      final streamed = await request.send().timeout(const Duration(seconds: 60));
      final response = await http.Response.fromStream(streamed);
      final payload = unwrapApiContract(json.decode(response.body), response.statusCode);
      normalizeChatMediaMessage(
        payload,
        mediaScope: 'consulta-chat',
        encounterId: encounterId,
      );
      return {'success': true, 'data': payload};
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }
}
