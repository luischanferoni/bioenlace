import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared/assistant/assistant_chat_client.dart';

void main() {
  test('loads assistant messages from the API contract', () async {
    late http.Request received;
    final timestamp = DateTime.utc(2026, 10, 22);
    final client = MockClient((request) async {
      received = request;
      return http.Response.bytes(
        utf8.encode(
          json.encode({
            'data': {
              'mensajes': [
                {
                  'id': 'm-1',
                  'senderId': 'patient',
                  'senderName': 'Paciente',
                  'content': 'Hola',
                  'timestamp': timestamp.toIso8601String(),
                },
              ],
            },
          }),
        ),
        200,
      );
    });
    final service = AssistantChatClient(
      currentUserId: 'patient-7',
      currentUserName: 'Paciente',
      appClient: 'paciente-flutter',
      client: client,
    );

    final messages = await service.getMessages();

    expect(received.url.path, endsWith('/asistente/estado'));
    expect(received.headers['X-App-Client'], 'paciente-flutter');
    expect(messages.single.id, 'm-1');
    expect(messages.single.content, 'Hola');
    expect(messages.single.timestamp, timestamp);
    client.close();
  });

  test('sends assistant content and returns a bot message', () async {
    late http.Request received;
    final client = MockClient((request) async {
      received = request;
      return http.Response.bytes(
        utf8.encode(
          json.encode({
            'data': {'text': 'Respuesta'},
          }),
        ),
        200,
      );
    });
    final service = AssistantChatClient(
      currentUserId: 'staff-4',
      currentUserName: 'Profesional',
      appClient: 'personalsalud-flutter',
      client: client,
    );

    final message = await service.sendMessage('Consulta');

    expect(received.url.path, endsWith('/asistente/enviar'));
    expect(received.headers['X-App-Client'], 'personalsalud-flutter');
    expect(json.decode(received.body), {'content': 'Consulta'});
    expect(message.senderId, 'BOT');
    expect(message.content, 'Respuesta');
    expect(message.isResent, isFalse);
    client.close();
  });
}
