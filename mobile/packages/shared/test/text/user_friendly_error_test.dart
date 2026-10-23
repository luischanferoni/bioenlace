import 'dart:async';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

void main() {
  test('muestra el mensaje del contrato sin reinterpretarlo', () {
    final error = ApiContractException(message: 'Turno no disponible', code: 'x', retryable: true);
    expect(userFriendlyErrorMessage(error), 'Turno no disponible');
    expect(isRetryableNetworkError(error), isTrue);
    expect(isRetryableNetworkError(ApiContractException(message: 'No')), isFalse);
  });

  test('clasifica los fallos de transporte por tipo', () {
    expect(isRetryableNetworkError(TimeoutException('t')), isTrue);
    expect(isRetryableNetworkError(const SocketException('s')), isTrue);
    expect(isRetryableNetworkError(http.ClientException('c')), isTrue);
    expect(isRetryableNetworkError(Exception('failed to fetch')), isFalse);
    expect(userFriendlyErrorMessage(http.ClientException('c')), contains('conectar'));
    expect(userFriendlyErrorMessage(const FormatException('f')), contains('inesperada'));
  });

  test('no filtra excepciones técnicas al usuario', () {
    expect(userFriendlyErrorMessage(Exception('HTTP 500')), contains('error inesperado'));
  });
}
