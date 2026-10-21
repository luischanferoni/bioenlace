import 'package:bioenlace_paciente/services/push_notification_service.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('resolves encounter IDs from patient async lifecycle notifications', () {
    expect(
      PushNotificationService.asyncEncounterIdDesdePush({
        'type': 'CONSULTA_ASYNC_TOMADA_PATIENT',
        'encounter_id': '42',
      }),
      42,
    );
    expect(
      PushNotificationService.asyncEncounterIdDesdePush({
        'type': 'ENCOUNTER_SUMMARY_READY',
        'encounter_id': 42,
      }),
      isNull,
    );
  });
}
