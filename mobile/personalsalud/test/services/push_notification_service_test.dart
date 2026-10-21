import 'package:flutter_test/flutter_test.dart';
import 'package:personalsalud/services/push_notification_service.dart';

void main() {
  test('resolves encounter IDs from staff async notifications', () {
    expect(
      PushNotificationService.asyncEncounterIdDesdePush({
        'type': 'CONSULTA_ASYNC_NUEVA_SOLICITUD_STAFF',
        'encounter_id': 42,
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
