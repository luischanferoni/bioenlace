import 'package:flutter/foundation.dart';

/// Acceso de prueba «Ir al inicio» (solo desarrollo).
abstract final class MedicoDevConfig {
  static const String _fromEnv = String.fromEnvironment('DEV_TEST_USER_ID');

  /// user_id de Yii. Prioridad: `--dart-define` → default solo en debug.
  static String get testUserId {
    if (_fromEnv.isNotEmpty) return _fromEnv;
    if (kDebugMode) return '5748';
    return '';
  }

  static bool get showDevHomeButton => testUserId.isNotEmpty;
}
