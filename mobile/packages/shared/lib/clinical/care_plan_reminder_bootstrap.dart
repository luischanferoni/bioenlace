import 'package:timezone/data/latest.dart' as tz_data;
import 'package:timezone/timezone.dart' as tz;

import 'care_plan_local_reminder_service.dart';

/// Inicialización de zona horaria y plugin de notificaciones locales.
Future<void> bootstrapCarePlanReminders() async {
  tz_data.initializeTimeZones();
  try {
    tz.setLocalLocation(tz.getLocation('America/Argentina/Buenos_Aires'));
  } catch (_) {
    tz.setLocalLocation(tz.UTC);
  }
  await CarePlanLocalReminderService.instance.ensureInitialized();
}
