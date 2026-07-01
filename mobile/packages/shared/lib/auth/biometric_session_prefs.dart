import 'package:shared_preferences/shared_preferences.dart';

/// Preferencias para bloqueo por inactividad con biometría del dispositivo.
abstract final class BiometricSessionPrefs {
  static const lastActivityMsKey = 'biometric_last_activity_ms';
  static const unlockEnabledKey = 'biometric_unlock_enabled';

  /// El usuario vio el diálogo de enrolamiento y eligió «Ahora no».
  static const enrollmentDeclinedKey = 'biometric_enrollment_declined';

  /// Tiempo sin actividad antes de pedir huella al volver a la app.
  static const inactivityLockDuration = Duration(minutes: 5);

  static Future<void> touchActivity() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(
      lastActivityMsKey,
      DateTime.now().millisecondsSinceEpoch,
    );
  }

  static Future<bool> isUnlockEnabled() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool(unlockEnabledKey) ?? false;
  }

  static Future<void> setUnlockEnabled(bool value) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(unlockEnabledKey, value);
  }

  static Future<bool> shouldLock({
    required bool requireUnlockEnabled,
  }) async {
    if (requireUnlockEnabled && !await isUnlockEnabled()) {
      return false;
    }

    final prefs = await SharedPreferences.getInstance();
    final last = prefs.getInt(lastActivityMsKey);
    if (last == null || last <= 0) {
      return false;
    }

    final elapsedMs = DateTime.now().millisecondsSinceEpoch - last;
    return elapsedMs >= inactivityLockDuration.inMilliseconds;
  }

  static Future<void> clearOnLogout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(lastActivityMsKey);
    await prefs.remove(unlockEnabledKey);
    await prefs.remove(enrollmentDeclinedKey);
    await prefs.remove('biometric_enabled');
  }
}
