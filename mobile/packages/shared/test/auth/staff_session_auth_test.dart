import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:shared/auth/staff_session_auth.dart';

void main() {
  group('isMobileRoleUnavailableBody', () {
    test('detects the mobile_role_unavailable contract error', () {
      final body = jsonEncode({
        'error': {
          'code': 'mobile_role_unavailable',
          'message': 'Tu cuenta tiene el perfil Paciente...',
          'intent': 'error',
          'retryable': false,
        },
      });

      expect(BearerSessionAuth.isMobileRoleUnavailableBody(body), isTrue);
    });

    test('ignores other 403 contract errors', () {
      final body = jsonEncode({
        'error': {'code': 'forbidden', 'message': 'No tenés permisos.'},
      });

      expect(BearerSessionAuth.isMobileRoleUnavailableBody(body), isFalse);
    });

    test('ignores bodies without an error object', () {
      expect(
        BearerSessionAuth.isMobileRoleUnavailableBody(
          jsonEncode({
            'data': {'id': 1},
          }),
        ),
        isFalse,
      );
      expect(BearerSessionAuth.isMobileRoleUnavailableBody(''), isFalse);
      expect(
        BearerSessionAuth.isMobileRoleUnavailableBody('not json'),
        isFalse,
      );
    });
  });
}
