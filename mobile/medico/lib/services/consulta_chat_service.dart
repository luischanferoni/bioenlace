import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

/// Servicio para chat con paciente (consulta-chat API) en la app médico.
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
      final uri = Uri.parse('${AppConfig.apiUrl}/consulta-chat/messages/$consultaId');
      final response = await http.get(uri, headers: _headers)
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      final data = json.decode(response.body);
      if (response.statusCode == 200 && data['success'] == true) {
        return {'success': true, 'data': data['data'], 'messages': data['data']?['messages'] ?? []};
      }
      return {'success': false, 'message': data['message'] ?? 'Error al cargar mensajes', 'messages': []};
    } catch (e) {
      return {'success': false, 'message': e.toString(), 'messages': []};
    }
  }

  Future<Map<String, dynamic>> sendMessage(int consultaId, String text, {String userRole = 'medico'}) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/consulta-chat/send');
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

  Future<Map<String, dynamic>> uploadFile(
    int consultaId,
    File file, {
    required String messageType,
  }) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/consulta-chat/upload');
      final request = http.MultipartRequest('POST', uri);
      request.headers.addAll(_headers);
      request.fields['consulta_id'] = consultaId.toString();
      request.fields['user_id'] = userId;
      request.fields['message_type'] = messageType;
      request.files.add(await http.MultipartFile.fromPath('file', file.path, filename: file.path.split(RegExp(r'[/\\]')).last));

      final streamed = await request.send().timeout(Duration(seconds: 60));
      final response = await http.Response.fromStream(streamed);
      final data = json.decode(response.body);

      if (response.statusCode == 200 && data['success'] == true) {
        return {'success': true, 'data': data['data']};
      }
      return {'success': false, 'message': data['message'] ?? 'Error al subir archivo'};
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }
}
