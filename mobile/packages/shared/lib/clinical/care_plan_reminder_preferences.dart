import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Preferencias locales de recordatorios de care plan (opt-in paciente).
class CarePlanReminderPreferences {
  static const globalEnabledKey = 'care_plan_reminders_global_enabled';
  static const lastSyncHashKey = 'care_plan_reminders_last_sync_hash';
  static const leadMinutesKey = 'care_plan_reminders_lead_minutes';

  static String planKey(int carePlanId) => 'care_plan_reminders_plan_$carePlanId';
  static String itemKey(int activityId) => 'care_plan_reminders_item_$activityId';
  static String customTimesKey(int activityId) => 'care_plan_reminders_custom_times_$activityId';

  static Future<bool> isGlobalEnabled() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool(globalEnabledKey) ?? false;
  }

  static Future<void> setGlobalEnabled(bool value) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(globalEnabledKey, value);
  }

  static Future<bool> isPlanEnabled(int carePlanId, {required bool globalDefault}) async {
    final prefs = await SharedPreferences.getInstance();
    if (!prefs.containsKey(planKey(carePlanId))) {
      return globalDefault;
    }
    return prefs.getBool(planKey(carePlanId)) ?? globalDefault;
  }

  static Future<void> setPlanEnabled(int carePlanId, bool value) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(planKey(carePlanId), value);
  }

  static Future<bool> isItemEnabled(int activityId, {required bool planDefault}) async {
    final prefs = await SharedPreferences.getInstance();
    if (!prefs.containsKey(itemKey(activityId))) {
      return planDefault;
    }
    return prefs.getBool(itemKey(activityId)) ?? planDefault;
  }

  static Future<void> setItemEnabled(int activityId, bool value) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(itemKey(activityId), value);
  }

  static Future<List<String>> getCustomTimes(int activityId) async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(customTimesKey(activityId));
    if (raw == null || raw.isEmpty) {
      return [];
    }
    try {
      final decoded = json.decode(raw);
      if (decoded is! List) {
        return [];
      }
      return decoded.map((e) => e.toString()).where((t) => t.contains(':')).toList();
    } catch (_) {
      return [];
    }
  }

  static Future<void> setCustomTimes(int activityId, List<String> times) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(customTimesKey(activityId), json.encode(times));
  }

  static Future<int> leadMinutes() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getInt(leadMinutesKey) ?? 0;
  }

  static Future<void> setLastSyncHash(String hash) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(lastSyncHashKey, hash);
  }

  static Future<String?> lastSyncHash() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(lastSyncHashKey);
  }
}
