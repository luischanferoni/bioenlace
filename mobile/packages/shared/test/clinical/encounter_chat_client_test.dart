import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared/clinical/encounter_chat_client.dart';

void main() {
  test('sends patient role by default in encounter messages', () async {
    late http.Request received;
    final client = MockClient((request) async {
      received = request;
      return http.Response.bytes(
        utf8.encode(
          json.encode({
            'data': {'id': 19},
          }),
        ),
        200,
      );
    });
    final service = EncounterChatClient(
      userId: 'patient-7',
      defaultUserRole: 'paciente',
      client: client,
    );

    final result = await service.sendMessage(19, 'Hola');

    expect(received.url.path, endsWith('/encounter-chat/enviar'));
    expect(json.decode(received.body), {
      'encounter_id': 19,
      'message': 'Hola',
      'user_id': 'patient-7',
      'user_role': 'paciente',
    });
    expect(result, {
      'success': true,
      'data': {'id': 19},
    });
    client.close();
  });

  test('sends staff role by default in encounter messages', () async {
    late http.Request received;
    final client = MockClient((request) async {
      received = request;
      return http.Response.bytes(
        utf8.encode(
          json.encode({
            'data': {'id': 19},
          }),
        ),
        200,
      );
    });
    final service = EncounterChatClient(
      userId: 'staff-4',
      defaultUserRole: 'medico',
      client: client,
    );

    await service.sendMessage(19, 'En seguimiento');

    expect(json.decode(received.body)['user_role'], 'medico');
    client.close();
  });

  test('returns structured backend errors when sending a message', () async {
    final client = MockClient(
      (_) async => http.Response.bytes(
        utf8.encode(
          json.encode({
            'error': {
              'code': 'chat_closed',
              'message': 'El chat está cerrado.',
              'intent': 'warning',
              'retryable': false,
            },
          }),
        ),
        409,
      ),
    );
    final service = EncounterChatClient(
      userId: 'staff-4',
      defaultUserRole: 'medico',
      client: client,
    );

    final result = await service.sendMessage(19, 'Mensaje');

    expect(result['success'], isFalse);
    expect(result['message'], 'El chat está cerrado.');
    expect(result['error'], {
      'code': 'chat_closed',
      'message': 'El chat está cerrado.',
      'intent': 'warning',
      'retryable': false,
    });
    client.close();
  });

  test(
    'uploads bytes as multipart and normalizes the returned media URL',
    () async {
      late http.Request received;
      final client = MockClient((request) async {
        received = request;
        return http.Response.bytes(
          utf8.encode(
            json.encode({
              'data': {
                'id': 23,
                'message_type': 'audio',
                'content': 'grabacion.webm',
              },
            }),
          ),
          200,
        );
      });
      final service = EncounterChatClient(
        userId: 'patient-7',
        defaultUserRole: 'paciente',
        client: client,
      );

      final result = await service.uploadBytes(
        19,
        [1, 2, 3],
        filename: 'grabacion.webm',
        messageType: 'audio',
      );

      expect(received.url.path, endsWith('/encounter-chat/subir'));
      expect(
        received.headers['content-type'],
        startsWith('multipart/form-data'),
      );
      expect(received.body, contains('encounter_id'));
      expect(received.body, contains('patient-7'));
      expect(received.body, contains('grabacion.webm'));
      expect(
        (result['data'] as Map<String, dynamic>)['content'],
        contains('/api/v1/media/encounter-chat/19/grabacion.webm'),
      );
      client.close();
    },
  );
}
