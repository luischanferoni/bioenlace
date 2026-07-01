import 'package:shared_preferences/shared_preferences.dart';

/// Claves de sesión operativa del personal de salud (efector, PES, encounter, token).
abstract final class PersonalsaludSessionPrefs {
  static const staffMobileLoginEstablishedKey = 'staff_mobile_login_established';

  static const operationalKeys = [
    'auth_token',
    'config_completed',
    'encounter_class',
    'encounter_class_label',
    'id_profesional_efector_servicio',
    'id_efector',
    'id_persona',
    'efector_id',
    'efector_nombre',
    'servicio_id',
    'servicio_nombre',
    'dni_detected',
  ];

  /// Borra identidad y contexto operativo; conserva `device_id` (push).
  static Future<void> clearOnLogout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('is_logged_in', false);
    await prefs.remove('user_id');
    await prefs.remove('user_name');
    await prefs.remove(staffMobileLoginEstablishedKey);
    for (final key in operationalKeys) {
      await prefs.remove(key);
    }
  }

  /// Quita contexto operativo (efector/PES/encounter). Opcionalmente conserva JWT de login.
  static Future<void> clearOperationalContext({bool keepAuthToken = true}) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('config_completed', false);
    for (final key in operationalKeys) {
      if (keepAuthToken && key == 'auth_token') continue;
      await prefs.remove(key);
    }
  }

  static Future<bool> hasCompleteOperationalContext() async {
    final prefs = await SharedPreferences.getInstance();
    return (prefs.getBool('config_completed') ?? false) &&
        (prefs.getString('encounter_class') ?? '').isNotEmpty &&
        (prefs.getString('auth_token') ?? '').isNotEmpty;
  }
}
