import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared/shared.dart';

void main() {
  http.Response json200(Object body, [int status = 200]) => http.Response(
        jsonEncode(body),
        status,
        headers: {'content-type': 'application/json; charset=utf-8'},
      );

  test('envía headers y query, y devuelve data', () async {
    late http.Request captured;
    final api = ApiClient(
      authToken: 'tok',
      appClient: 'paciente-flutter',
      client: MockClient((req) async {
        captured = req;
        return json200({'data': {'id': 7}, 'meta': {'message': 'ok'}});
      }),
    );
    final data = await api.getData('/x', query: {'a': '1'});
    expect(data['id'], 7);
    expect(data['message'], 'ok');
    expect(captured.url.queryParameters['a'], '1');
    expect(captured.headers['X-App-Client'], 'paciente-flutter');
  });

  test('traceLabel registra la respuesta y no altera el resultado', () async {
    final logs = <String>[];
    final previous = debugPrint;
    debugPrint = (String? message, {int? wrapWidth}) {
      if (message != null) logs.add(message);
    };
    addTearDown(() => debugPrint = previous);
    final api = ApiClient(
      traceLabel: 'home/panel',
      client: MockClient((_) async => json200({'data': {'id': 3}})),
    );
    final data = await api.getData('/home/panel');
    expect(data['id'], 3);
    expect(logs.any((l) => l.contains('[HTTP home/panel] 200')), isTrue);
  });

  test('traduce el error del contrato con code, intent y retryable', () async {
    final api = ApiClient(
      client: MockClient((_) async => json200({
            'error': {'code': 'busy', 'message': 'Reintentá', 'intent': 'retry', 'retryable': true},
          }, 503)),
    );
    expect(
      api.getData('/x'),
      throwsA(isA<ApiContractException>()
          .having((e) => e.code, 'code', 'busy')
          .having((e) => e.retryable, 'retryable', isTrue)
          .having((e) => e.message, 'message', 'Reintentá')),
    );
  });

  test('un error HTTP sin JSON sale como fallo del contrato', () async {
    final api = ApiClient(client: MockClient((_) async => http.Response('<html>', 502)));
    expect(api.getList('/x'), throwsA(isA<ApiContractException>()));
  });

  test('CarePlanReminderApi guarda preferencias con PUT', () async {
    late http.Request captured;
    final api = CarePlanReminderApi(
      authToken: 't',
      httpClient: MockClient((req) async {
        captured = req;
        return json200({'data': {'ok': true}, 'meta': {'message': 'Guardado'}});
      }),
    );
    final r = await api.savePreferences({'enabled': true});
    expect(captured.method, 'PUT');
    expect(captured.url.path, endsWith('preferencias-recordatorios-como-paciente'));
    expect(r['success'], isTrue);
    expect(r['message'], 'Guardado');
  });

  test('CarePlanService usa el cliente inyectado', () async {
    final svc = CarePlanService(
      authToken: 't',
      httpClient: MockClient((_) async => json200({'data': [{'id': 1}]})),
    );
    final r = await svc.fetchActivePlans();
    expect(r['success'], isTrue);
    expect((r['data'] as List).length, 1);
  });
}
