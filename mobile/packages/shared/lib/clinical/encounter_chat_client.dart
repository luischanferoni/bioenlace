import 'dart:convert';

import 'package:cross_file/cross_file.dart';
import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import '../http/api_contract.dart';
import '../media/chat_media_upload.dart';
import '../media/media_url.dart';

class EncounterChatClient {
  final String? authToken;
  final String userId;
  final String? userName;
  final String defaultUserRole;
  final http.Client? client;

  EncounterChatClient({
    required this.userId,
    this.authToken,
    this.userName,
    required this.defaultUserRole,
    this.client,
  });

  Map<String, String> get _headers => {
    'Accept': 'application/json',
    if (authToken != null && authToken!.isNotEmpty) 'Authorization': '******',
  };

  Future<Map<String, dynamic>> getMessages(int encounterId) async {
    try {
      final uri = Uri.parse(
        '${AppConfig.apiUrl}/encounter-chat/mensajes/$encounterId',
      );
      final response = await _get(
        uri,
        headers: _headers,
      ).timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      final payload = unwrapApiContract(
        json.decode(utf8.decode(response.bodyBytes)),
        response.statusCode,
      );
      final raw = payload['messages'] as List<dynamic>? ?? [];
      return {
        'success': true,
        'data': payload,
        'messages': normalizeChatMediaMessages(
          raw,
          mediaScope: 'encounter-chat',
          encounterId: encounterId,
        ),
      };
    } on ApiContractException catch (e) {
      return {
        'success': false,
        'message': e.message,
        'error': e.toJson(),
        'messages': [],
      };
    } catch (e) {
      return {'success': false, 'message': e.toString(), 'messages': []};
    }
  }

  Future<Map<String, dynamic>> sendMessage(
    int encounterId,
    String text, {
    String? userRole,
  }) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/encounter-chat/enviar');
      final body = {
        'encounter_id': encounterId,
        'message': text,
        'user_id': userId,
        'user_role': userRole ?? defaultUserRole,
      };
      final response = await _post(
        uri,
        headers: {..._headers, 'Content-Type': 'application/json'},
        body: json.encode(body),
      ).timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      final data = unwrapApiContract(
        json.decode(utf8.decode(response.bodyBytes)),
        response.statusCode,
      );
      return {'success': true, 'data': data};
    } on ApiContractException catch (e) {
      return {'success': false, 'message': e.message, 'error': e.toJson()};
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
      final request = _uploadRequest(encounterId, messageType);
      request.files.add(await multipartFileFromXFile(file));
      return await _sendUpload(request, encounterId);
    } on ApiContractException catch (e) {
      return {'success': false, 'message': e.message, 'error': e.toJson()};
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }

  Future<Map<String, dynamic>> uploadBytes(
    int encounterId,
    List<int> bytes, {
    required String filename,
    required String messageType,
    String? userRole,
  }) async {
    try {
      final request = _uploadRequest(encounterId, messageType);
      request.files.add(
        await multipartFileFromBytes(bytes, filename: filename),
      );
      return await _sendUpload(request, encounterId);
    } on ApiContractException catch (e) {
      return {'success': false, 'message': e.message, 'error': e.toJson()};
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }

  http.MultipartRequest _uploadRequest(int encounterId, String messageType) {
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('${AppConfig.apiUrl}/encounter-chat/subir'),
    );
    request.headers.addAll(_headers);
    request.fields['encounter_id'] = encounterId.toString();
    request.fields['user_id'] = userId;
    request.fields['message_type'] = messageType;
    return request;
  }

  Future<Map<String, dynamic>> _sendUpload(
    http.MultipartRequest request,
    int encounterId,
  ) async {
    final streamed = await _send(request).timeout(const Duration(seconds: 60));
    final response = await http.Response.fromStream(streamed);
    final payload = unwrapApiContract(
      json.decode(utf8.decode(response.bodyBytes)),
      response.statusCode,
    );
    normalizeChatMediaMessage(
      payload,
      mediaScope: 'encounter-chat',
      encounterId: encounterId,
    );
    return {'success': true, 'data': payload};
  }

  Future<http.Response> _get(Uri uri, {required Map<String, String> headers}) =>
      client == null
          ? http.get(uri, headers: headers)
          : client!.get(uri, headers: headers);

  Future<http.Response> _post(
    Uri uri, {
    required Map<String, String> headers,
    required String body,
  }) =>
      client == null
          ? http.post(uri, headers: headers, body: body)
          : client!.post(uri, headers: headers, body: body);

  Future<http.StreamedResponse> _send(http.MultipartRequest request) =>
      client == null ? request.send() : client!.send(request);
}
