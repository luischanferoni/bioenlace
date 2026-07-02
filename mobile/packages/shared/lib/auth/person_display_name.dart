import 'package:shared_preferences/shared_preferences.dart';

/// Nombre legible del paciente (nombre + apellido), no el username Yii.
class PersonDisplayName {
  PersonDisplayName._();

  static String fromPersonaMap(Map<String, dynamic>? persona) {
    if (persona == null) return '';
    return '${persona['nombre'] ?? ''} ${persona['apellido'] ?? ''}'.trim();
  }

  /// Tras login API: prioriza persona; evita `paciente_<dni>` u otros usernames de sistema.
  static String resolveForLogin({
    required Map<String, dynamic> user,
    required Map<String, dynamic> persona,
    String fallback = 'Usuario',
  }) {
    final fromPersona = fromPersonaMap(persona);
    if (fromPersona.isNotEmpty) return fromPersona;

    final fromUser = user['name']?.toString().trim() ?? '';
    if (fromUser.isNotEmpty && !looksLikeSystemUsername(fromUser)) {
      return fromUser;
    }
    return fromUser.isNotEmpty ? fromUser : fallback;
  }

  static bool looksLikeSystemUsername(String value) {
    final s = value.trim();
    if (s.isEmpty) return false;
    return RegExp(r'^(paciente|medico|play_review)[_\w\d]+$').hasMatch(s);
  }

  /// Para inicio: `name_detected` en prefs o userName si no es username de sistema.
  static Future<String> resolveForHome({required String userName}) async {
    final prefs = await SharedPreferences.getInstance();
    final stored = prefs.getString('name_detected')?.trim() ?? '';
    if (stored.isNotEmpty) return stored;

    final trimmed = userName.split(',').first.trim();
    if (trimmed.isNotEmpty && !looksLikeSystemUsername(trimmed)) {
      return trimmed;
    }
    return trimmed.isNotEmpty ? trimmed : 'Usuario';
  }
}
