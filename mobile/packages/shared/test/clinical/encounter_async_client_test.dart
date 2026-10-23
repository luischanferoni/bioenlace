import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared/clinical/encounter_async_client.dart';

void main() {
  test('posts patient cancellation with its app identity', () async {
    late http.Request received;
    final client = MockClient((request) async {
      received = request;
      return http.Response.bytes(
        utf8.encode(
          json.encode({
            'success': true,
            'data': {'encounter_id': 42},
          }),
        ),
        200,
      );
    });
    final api = EncounterAsyncClient(
      authToken: 'token',
      appClient: 'bioenlace-paciente',
      client: client,
    );

    final result = await api.cancelarComoPaciente(42);

    expect(
      received.url.path,
      endsWith('/encounter-async/cancelar-como-paciente'),
    );
    expect(received.headers['X-App-Client'], 'bioenlace-paciente');
    expect(json.decode(received.body), {'encounter_id': 42});
    expect(result, {
      'success': true,
      'data': {'encounter_id': 42},
    });
    client.close();
  });

  test('posts staff resolution with trimmed optional note', () async {
    late http.Request received;
    final client = MockClient((request) async {
      received = request;
      return http.Response.bytes(
        utf8.encode(
          json.encode({
            'success': true,
            'data': {'encounter_id': 81},
          }),
        ),
        200,
      );
    });
    final api = EncounterAsyncClient(
      appClient: 'personalsalud-flutter',
      client: client,
    );

    await api.cerrarComoStaff(81, 'resuelto', note: '  orientación  ');

    expect(received.url.path, endsWith('/encounter-async/cerrar-como-staff'));
    expect(received.headers['X-App-Client'], 'personalsalud-flutter');
    expect(json.decode(received.body), {
      'encounter_id': 81,
      'resolution_code': 'resuelto',
      'note': 'orientación',
    });
    client.close();
  });

  test('returns the backend error message for failed actions', () async {
    final client = MockClient(
      (_) async => http.Response.bytes(
        utf8.encode(
          json.encode({'success': false, 'message': 'La solicitud ya cambió.'}),
        ),
        409,
      ),
    );
    final api = EncounterAsyncClient(
      appClient: 'personalsalud-flutter',
      client: client,
    );

    final result = await api.tomarComoStaff(81);

    expect(result, {'success': false, 'message': 'La solicitud ya cambió.'});
    client.close();
  });

  test('preserves structured API error metadata for callers', () async {
    final client = MockClient(
      (_) async => http.Response.bytes(
        utf8.encode(
          json.encode({
            'error': {
              'code': 'encounter_conflict',
              'message': 'La solicitud ya cambió.',
              'intent': 'warning',
              'retryable': false,
              'field_errors': {'encounter_id': ['No disponible.']},
            },
          }),
        ),
        409,
      ),
    );
    final api = EncounterAsyncClient(
      appClient: 'personalsalud-flutter',
      client: client,
    );

    final result = await api.tomarComoStaff(81);

    expect(result['success'], isFalse);
    expect(result['message'], 'La solicitud ya cambió.');
    expect(result['error'], {
      'code': 'encounter_conflict',
      'message': 'La solicitud ya cambió.',
      'intent': 'warning',
      'retryable': false,
      'field_errors': {
        'encounter_id': ['No disponible.'],
      },
    });
    client.close();
  });

  test('rejects non-object responses', () async {
    final client = MockClient((_) async => http.Response('[]', 200));
    final api = EncounterAsyncClient(
      appClient: 'bioenlace-paciente',
      client: client,
    );

    final result = await api.cancelarComoPaciente(42);

    expect(result, {'success': false, 'message': 'Respuesta inválida'});
    client.close();
  });
}
