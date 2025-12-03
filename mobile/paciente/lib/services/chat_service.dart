// lib/services/chat_service.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

import '../models/message.dart';

class ChatService {  
  final String currentUserId;
  final String currentUserName;

  ChatService({
    required this.currentUserId,
    required this.currentUserName,
  });

  Future<List<Message>> getMessages() async {
    try {
      final response = await http.get(Uri.parse('${AppConfig.apiUrl}/messages'));
      
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true) {
          final List<dynamic> jsonMessages = json.decode(data['mensajes']);
          return jsonMessages.map((json) => Message.fromJson(json)).toList();
        }
        else {
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

  Future<Message> sendMessage(String content) async {
    try {
      final response = await http.post(
        Uri.parse('${AppConfig.apiUrl}/messages/enviar'),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'senderId': currentUserId,
          'senderName': currentUserName,
          'content': content,
        }),
      );

      if (response.statusCode == 201) {
        return Message.fromJson(json.decode(response.body));
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
        Uri.parse('${AppConfig.apiUrl}/messages'),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'senderId': currentUserId,
          'senderName': currentUserName,
          'content': originalMessage.content,
          'isResent': true,
        }),
      );

      if (response.statusCode == 201) {
        return Message.fromJson(json.decode(response.body));
      } else {
        throw Exception('Failed to resend message');
      }
    } catch (e) {
      print('Error resending message: $e');
      rethrow;
    }
  }

  Future<Message> _getMessageById(String id) async {
    final response = await http.get(Uri.parse('${AppConfig.apiUrl}/messages/$id'));
    
    if (response.statusCode == 200) {
      return Message.fromJson(json.decode(response.body));
    } else {
      throw Exception('Failed to load message');
    }
  }
}