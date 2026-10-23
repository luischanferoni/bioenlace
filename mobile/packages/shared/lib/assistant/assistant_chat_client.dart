import 'dart:convert';

import 'package:http/http.dart' as http;

import '../http/api_client.dart';
import '../models/message.dart';

class AssistantChatClient {
  final String currentUserId;
  final String currentUserName;
  final String? authToken;
  final String appClient;
  final http.Client? client;

  AssistantChatClient({
    required this.currentUserId,
    required this.currentUserName,
    this.authToken,
    required this.appClient,
    this.client,
  });

  ApiClient get _api =>
      ApiClient(authToken: authToken, appClient: appClient, client: client);

  Future<List<Message>> getMessages() async {
    try {
      final data = await _api.getData('/asistente/estado');
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
    } catch (e) {
      print('Error fetching messages: $e');
      rethrow;
    }
  }

  Future<Message> sendMessage(String content) async {
    try {
      final data = await _api.postData(
        '/asistente/enviar',
        body: {'content': content},
      );
      final text = data['text']?.toString() ?? data['explanation']?.toString();
      if (text == null) {
        throw const FormatException('Respuesta del asistente sin texto.');
      }
      return Message(
        id: DateTime.now().millisecondsSinceEpoch.toString(),
        senderId: 'BOT',
        senderName: 'Bot',
        content: text,
        timestamp: DateTime.now(),
      );
    } catch (e) {
      print('Error sending message: $e');
      rethrow;
    }
  }

  Future<Message> resendMessage(String messageId) async {
    try {
      final originalMessage = await _getMessageById(messageId);
      final data = await _api.postData(
        '/asistente/enviar',
        body: {'content': originalMessage.content, 'isResent': true},
      );
      final text = data['text']?.toString() ?? data['explanation']?.toString();
      if (text == null) {
        throw const FormatException('Respuesta del asistente sin texto.');
      }
      return Message(
        id: DateTime.now().millisecondsSinceEpoch.toString(),
        senderId: 'BOT',
        senderName: 'Bot',
        content: text,
        timestamp: DateTime.now(),
        isResent: true,
      );
    } catch (e) {
      print('Error resending message: $e');
      rethrow;
    }
  }

  Future<Message> _getMessageById(String id) async {
    throw UnimplementedError(
      'Sin retrocompatibilidad: /messages/{id} fue removido y este método requiere un endpoint específico.',
    );
  }
}
