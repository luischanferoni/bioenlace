import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

/// Sesión JWT del paciente persistida en el dispositivo.
abstract final class PacienteSessionPrefs {
  static Future<bool> hasRestorableSession() async {
    final prefs = await SharedPreferences.getInstance();
    if (!(prefs.getBool('is_logged_in') ?? false)) {
      return false;
    }
    final token = (prefs.getString('auth_token') ?? '').trim();
    final userId = (prefs.getString('user_id') ?? '').trim();
    return token.isNotEmpty && userId.isNotEmpty;
  }

  /// Limpia banderas de sesión si quedó `is_logged_in` sin JWT (p. ej. hot restart en debug).
  static Future<void> reconcileStaleSessionOnLaunch() async {
    final prefs = await SharedPreferences.getInstance();
    if (!(prefs.getBool('is_logged_in') ?? false)) {
      return;
    }
    final token = (prefs.getString('auth_token') ?? '').trim();
    if (token.isNotEmpty) {
      return;
    }
    await clearInvalidAuthSession();
  }

  /// Quita JWT e identidad local; conserva `device_id` y preferencias de huella.
  static Future<void> clearInvalidAuthSession() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('is_logged_in', false);
    await prefs.remove('user_id');
    await prefs.remove('user_name');
    await prefs.remove('auth_token');
    ClientDiagnosticApi.bindSession(
      authToken: null,
      appClient: BearerSessionAuth.appClientPaciente,
    );
  }
}
