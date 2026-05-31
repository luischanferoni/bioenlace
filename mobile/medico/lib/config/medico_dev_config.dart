import 'package:flutter/foundation.dart';

/// Acceso de prueba «Ir al inicio» (token `/auth/generar-token-prueba`).
abstract final class MedicoDevConfig {
  static const String _fromEnv = String.fromEnvironment('DEV_TEST_USER_ID');

  /// Por defecto `true` para APK release de prueba en dispositivo.
  /// Builds de tienda: `--dart-define=ENABLE_DEV_TEST_LOGIN=false`
  static const bool _allowDevLogin = bool.fromEnvironment(
    'ENABLE_DEV_TEST_LOGIN',
    defaultValue: true,
  );

  /// user_id de Yii. Prioridad: `--dart-define=DEV_TEST_USER_ID` → default si dev login habilitado.
  static String get testUserId {
    if (_fromEnv.isNotEmpty) return _fromEnv;
    if (kDebugMode || _allowDevLogin) return '5748';
    return '';
  }

  static bool get showDevHomeButton => testUserId.isNotEmpty;
}
