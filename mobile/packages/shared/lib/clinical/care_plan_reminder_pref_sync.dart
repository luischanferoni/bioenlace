import 'care_plan_reminder_api.dart';
import 'care_plan_reminder_preferences.dart';

/// Sincroniza preferencias locales con el servidor (servidor gana al descargar).
class CarePlanReminderPrefSync {
  /// Descarga preferencias del servidor y las aplica en SharedPreferences.
  static Future<void> pullFromServer({String? authToken}) async {
    final api = CarePlanReminderApi(authToken: authToken);
    final r = await api.fetchPreferences();
    if (r['success'] != true || r['data'] is! Map) {
      return;
    }
    await applyServer(Map<String, dynamic>.from(r['data'] as Map));
  }

  /// Sube el estado local completo (última escritura en servidor).
  static Future<void> pushToServer({String? authToken}) async {
    final api = CarePlanReminderApi(authToken: authToken);
    await api.savePreferences(await exportLocal());
  }

  static Future<Map<String, dynamic>> exportLocal() async {
    final global = await CarePlanReminderPreferences.isGlobalEnabled();
    return {
      'globalEnabled': global,
      // Planes e ítems se envían cuando el panel/detalle los persiste vía push parcial.
    };
  }

  static Future<void> applyServer(Map<String, dynamic> data) async {
    if (data.containsKey('globalEnabled')) {
      await CarePlanReminderPreferences.setGlobalEnabled(
        data['globalEnabled'] == true,
      );
    }

    final plans = data['plans'];
    if (plans is Map) {
      for (final entry in plans.entries) {
        final planId = int.tryParse(entry.key.toString());
        if (planId != null && planId > 0) {
          await CarePlanReminderPreferences.setPlanEnabled(
            planId,
            entry.value == true,
          );
        }
      }
    }

    final items = data['items'];
    if (items is Map) {
      for (final entry in items.entries) {
        final activityId = int.tryParse(entry.key.toString());
        if (activityId == null || activityId <= 0) {
          continue;
        }
        if (entry.value is! Map) {
          continue;
        }
        final item = Map<String, dynamic>.from(entry.value as Map);
        await CarePlanReminderPreferences.setItemEnabled(
          activityId,
          item['enabled'] != false,
        );
        final times = item['customTimes'];
        if (times is List && times.isNotEmpty) {
          await CarePlanReminderPreferences.setCustomTimes(
            activityId,
            times.map((e) => e.toString()).toList(),
          );
        }
      }
    }
  }

  /// Envía un fragmento (p. ej. tras cambiar un ítem en detalle).
  static Future<void> pushPartial(
    Map<String, dynamic> body, {
    String? authToken,
  }) async {
    final api = CarePlanReminderApi(authToken: authToken);
    await api.savePreferences(body);
  }
}
