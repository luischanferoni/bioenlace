// lib/services/chat_service.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

import '../models/message.dart';

class ChatService {
  final String currentUserId;
  final String currentUserName;
  /// Si está definido, se envía en `asistente/enviar` (API v1 con Bearer).
  final String? authToken;

  ChatService({
    required this.currentUserId,
    required this.currentUserName,
    this.authToken,
  });

  Map<String, String> _jsonHeaders() {
    final h = <String, String>{
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (authToken != null && authToken!.isNotEmpty) {
      h['Authorization'] = 'Bearer $authToken';
    }
    return h;
  }

  Future<List<Message>> getMessages() async {
    try {
      final response = await http.get(
        Uri.parse('${AppConfig.apiUrl}/asistente/estado'),
        headers: _jsonHeaders(),
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true) {
          final raw = data['mensajes'];
          if (raw == null) return [];
          if (raw is String) {
            final List<dynamic> jsonMessages = json.decode(raw);
            return jsonMessages.map((json) => Message.fromJson(json)).toList();
          }
          if (raw is List) {
            return raw.map((json) => Message.fromJson(json)).toList();
          }
          return [];
        } else {
          throw Exception('Failed to load messages');
        }
      } else {
        throw Exception('Failed to load messages');
      }
    } catch (e) {
      print('Error fetching messages: $e');
      rethrow;
    }
  }

  /// Misma tubería que el asistente en web/app: POST `asistente/enviar` con cuerpo `{ "content": ... }`.
  Future<Message> sendMessage(String content) async {
    try {
      final response = await http.post(
        Uri.parse('${AppConfig.apiUrl}/asistente/enviar'),
        headers: _jsonHeaders(),
        body: json.encode({
          'content': content,
        }),
      );

      if (response.statusCode == 200) {
        final body = json.decode(response.body) as Map<String, dynamic>;
        if (body['success'] == true && body['data'] is Map<String, dynamic>) {
          final data = body['data'] as Map<String, dynamic>;
          final explanation =
              data['explanation']?.toString() ?? 'Consulta procesada';
          return Message(
            id: DateTime.now().millisecondsSinceEpoch.toString(),
            senderId: 'BOT',
            senderName: 'Bot',
            content: explanation,
            timestamp: DateTime.now(),
          );
        }
        throw Exception(body['message']?.toString() ?? 'Failed to send message');
      } else {
        throw Exception('Failed to send message');
      }
    } catch (e) {
      print('Error sending message: $e');
      rethrow;
    }
  }

  Future<Message> resendMessage(String messageId) async {
    try {
      final originalMessage = await _getMessageById(messageId);

      final response = await http.post(
        Uri.parse('${AppConfig.apiUrl}/asistente/enviar'),
        headers: _jsonHeaders(),
        body: json.encode({
          'content': originalMessage.content,
          'isResent': true,
        }),
      );

      if (response.statusCode == 200) {
        final body = json.decode(response.body) as Map<String, dynamic>;
        if (body['success'] == true && body['data'] is Map<String, dynamic>) {
          final data = body['data'] as Map<String, dynamic>;
          final explanation =
              data['explanation']?.toString() ?? 'Consulta procesada';
          return Message(
            id: DateTime.now().millisecondsSinceEpoch.toString(),
            senderId: 'BOT',
            senderName: 'Bot',
            content: explanation,
            timestamp: DateTime.now(),
            isResent: true,
          );
        }
        throw Exception(body['message']?.toString() ?? 'Failed to resend message');
      } else {
        throw Exception('Failed to resend message');
      }
    } catch (e) {
      print('Error resending message: $e');
      rethrow;
    }
  }

  Future<Message> _getMessageById(String id) async {
    throw UnimplementedError('Sin retrocompatibilidad: /messages/{id} fue removido y este método requiere un endpoint específico.');
  }
}
