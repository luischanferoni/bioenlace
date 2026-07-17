import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:timezone/timezone.dart' as tz;

import 'care_plan_reminder_api.dart';
import 'care_plan_reminder_pref_sync.dart';
import 'care_plan_reminder_preferences.dart';

typedef CarePlanReminderTapCallback = void Function({
  required int carePlanId,
  required int activityId,
});

/// Programa recordatorios locales de medicación a partir de la agenda API.
class CarePlanLocalReminderService {
  CarePlanLocalReminderService._();
  static final CarePlanLocalReminderService instance = CarePlanLocalReminderService._();

  /// Alarmas locales solo en Android/iOS (flutter_local_notifications no soporta web).
  static bool get isSupported => !kIsWeb;

  /// Registrado por la app host (paciente) para navegar al detalle del plan.
  static CarePlanReminderTapCallback? onNotificationTap;

  static const String channelId = 'care_plan_reminders';
  static const String channelName = 'Recordatorios de medicación';

  final FlutterLocalNotificationsPlugin _plugin = FlutterLocalNotificationsPlugin();
  bool _initialized = false;

  Future<void> ensureInitialized() async {
    if (!isSupported) {
      return;
    }
    if (_initialized) {
      return;
    }

    const android = AndroidInitializationSettings('@mipmap/ic_launcher');
    const ios = DarwinInitializationSettings();
    await _plugin.initialize(
      const InitializationSettings(android: android, iOS: ios),
      onDidReceiveNotificationResponse: _onNotificationTap,
    );

    final androidPlugin = _plugin.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();
    await androidPlugin?.createNotificationChannel(
      const AndroidNotificationChannel(
        channelId,
        channelName,
        description: 'Horarios de medicamentos del plan de tratamiento',
        importance: Importance.high,
      ),
    );

    _initialized = true;
  }

  void _onNotificationTap(NotificationResponse response) {
    final raw = response.payload;
    if (raw == null || raw.isEmpty) {
      return;
    }
    try {
      final map = json.decode(raw);
      if (map is! Map) {
        return;
      }
      final carePlanId = _asInt(map['carePlanId']);
      final activityId = _asInt(map['activityId']);
      if (carePlanId > 0 && activityId > 0) {
        onNotificationTap?.call(
          carePlanId: carePlanId,
          activityId: activityId,
        );
      }
    } catch (_) {
      debugPrint('[CarePlanReminders] tap payload inválido: $raw');
    }
  }

  Future<bool> requestPermissionIfNeeded() async {
    if (!isSupported) {
      return false;
    }
    final status = await Permission.notification.status;
    if (status.isGranted) {
      return true;
    }
    final req = await Permission.notification.request();
    return req.isGranted;
  }

  Future<void> cancelAll() async {
    if (!isSupported) {
      return;
    }
    await ensureInitialized();
    await _plugin.cancelAll();
  }

  /// Sincroniza alarmas si el switch global está activo.
  Future<String?> syncFromApi({String? authToken, bool pullPrefsFirst = true}) async {
    if (pullPrefsFirst) {
      await CarePlanReminderPrefSync.pullFromServer(authToken: authToken);
    }

    if (!isSupported) {
      return null;
    }

    await ensureInitialized();

    final globalOn = await CarePlanReminderPreferences.isGlobalEnabled();
    if (!globalOn) {
      await cancelAll();
      return null;
    }

    final api = CarePlanReminderApi(authToken: authToken);
    final r = await api.fetchSchedule();
    if (r['success'] != true || r['data'] is! Map) {
      return r['message']?.toString();
    }

    final data = Map<String, dynamic>.from(r['data'] as Map);
    final hash = _hashPayload(data);
    final prev = await CarePlanReminderPreferences.lastSyncHash();
    if (prev == hash) {
      return null;
    }

    await cancelAll();
    final items = data['items'];
    if (items is! List) {
      await CarePlanReminderPreferences.setLastSyncHash(hash);
      return null;
    }

    final lead = await CarePlanReminderPreferences.leadMinutes();
    var scheduled = 0;

    for (final raw in items) {
      if (raw is! Map) {
        continue;
      }
      final item = Map<String, dynamic>.from(raw);
      final scheduledOne = await _scheduleItem(item, leadMinutes: lead);
      scheduled += scheduledOne;
    }

    await CarePlanReminderPreferences.setLastSyncHash(hash);
    debugPrint('[CarePlanReminders] scheduled=$scheduled');
    return null;
  }

  Future<int> _scheduleItem(Map<String, dynamic> item, {required int leadMinutes}) async {
    if (!isSupported) {
      return 0;
    }

    final carePlanId = _asInt(item['carePlanId']);
    final activityId = _asInt(item['activityId']);
    if (carePlanId <= 0 || activityId <= 0) {
      return 0;
    }

    final globalOn = await CarePlanReminderPreferences.isGlobalEnabled();
    if (!globalOn) {
      return 0;
    }

    final planOn = await CarePlanReminderPreferences.isPlanEnabled(
      carePlanId,
      globalDefault: true,
    );
    if (!planOn) {
      return 0;
    }

    final itemOn = await CarePlanReminderPreferences.isItemEnabled(
      activityId,
      planDefault: true,
    );
    if (!itemOn) {
      return 0;
    }

    List<String> times = [];
    final requiresSetup = item['requiresPatientSetup'] == true;
    if (requiresSetup) {
      times = await CarePlanReminderPreferences.getCustomTimes(activityId);
    } else {
      final schedule = item['schedule'];
      if (schedule is Map) {
        final raw = schedule['timeOfDay'];
        if (raw is List) {
          times = raw.map((e) => e.toString()).toList();
        }
      }
    }

    if (times.isEmpty) {
      return 0;
    }

    final kind = item['kind']?.toString() ?? '';
    final isStudy = kind.contains('service');
    final notifLabel = item['notificationLabel']?.toString()
        ?? (isStudy ? 'Recordatorio de estudio' : 'Medicación');
    final title = item['title']?.toString() ?? notifLabel;
    final subtitle = item['subtitle']?.toString() ?? '';
    final defaultBody = isStudy
        ? 'Recordatorio de estudio'
        : 'Es hora de tu medicación';
    final body = subtitle.isNotEmpty ? subtitle : defaultBody;
    final notifTitle = isStudy ? notifLabel : title;

    var count = 0;
    final now = tz.TZDateTime.now(tz.local);
    for (var day = 0; day < 14; day++) {
      final dayBase = tz.TZDateTime(
        tz.local,
        now.year,
        now.month,
        now.day,
      ).add(Duration(days: day));

      for (var slot = 0; slot < times.length; slot++) {
        final parts = times[slot].split(':');
        if (parts.length < 2) {
          continue;
        }
        final h = int.tryParse(parts[0]) ?? -1;
        final m = int.tryParse(parts[1]) ?? -1;
        if (h < 0 || h > 23 || m < 0 || m > 59) {
          continue;
        }

        var scheduledAt = tz.TZDateTime(
          tz.local,
          dayBase.year,
          dayBase.month,
          dayBase.day,
          h,
          m,
        ).subtract(Duration(minutes: leadMinutes));

        if (scheduledAt.isBefore(now)) {
          continue;
        }

        final notifId = _notificationId(activityId, day, slot);
        final payload = json.encode({
          'carePlanId': carePlanId,
          'activityId': activityId,
        });

        await _plugin.zonedSchedule(
          notifId,
          notifTitle,
          body,
          scheduledAt,
          const NotificationDetails(
            android: AndroidNotificationDetails(
              channelId,
              channelName,
              importance: Importance.high,
              priority: Priority.high,
            ),
            iOS: DarwinNotificationDetails(),
          ),
          androidScheduleMode: AndroidScheduleMode.inexactAllowWhileIdle,
          uiLocalNotificationDateInterpretation:
              UILocalNotificationDateInterpretation.absoluteTime,
          payload: payload,
        );
        count++;
      }
    }

    return count;
  }

  int _notificationId(int activityId, int dayOffset, int slot) {
    return (activityId % 50000) * 1000 + (dayOffset % 14) * 10 + (slot % 10);
  }

  int _asInt(dynamic v) {
    if (v is int) {
      return v;
    }
    return int.tryParse(v?.toString() ?? '') ?? 0;
  }

  String _hashPayload(Map<String, dynamic> data) {
    return data.toString().hashCode.toString();
  }
}
