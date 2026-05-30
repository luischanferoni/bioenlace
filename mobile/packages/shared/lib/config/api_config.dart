/// Configuración centralizada de la API para todas las aplicaciones móviles
class AppConfig {
  /// URL base de la API.
  ///
  /// Se puede sobreescribir en build/runtime con:
  /// `--dart-define=API_URL=https://.../api/v1`
  static const String apiUrl = String.fromEnvironment(
    'API_URL',
    defaultValue: 'https://app.bioenlace.io/api/v1',
  );

  /// `/api/clinical/...` (RBAC) → `/api/v1/clinical/...` para fetch HTTP.
  static String normalizeApiV1Path(String path) {
    var p = path.trim();
    if (p.isEmpty) return p;
    if (p.startsWith('http://') || p.startsWith('https://')) {
      return p;
    }
    if (!p.startsWith('/')) p = '/$p';
    if (RegExp(r'^/api/v\d+/').hasMatch(p)) {
      return p;
    }
    if (p.startsWith('/api/')) {
      return '/api/v1/${p.substring(5)}';
    }
    return '/api/v1/${p.replaceFirst(RegExp(r'^/+'), '')}';
  }

  /// Versión de app para compatibilidad de descriptores UI (`X-App-Version`), alineado con la web.
  static const String appVersion = String.fromEnvironment(
    'APP_VERSION',
    defaultValue: '1.0.0',
  );

  /// Cabeceras JSON estándar BioEnlace API v1 (CORS + compatibilidad `ui_meta.clients`).
  static Map<String, String> jsonHeaders({
    String? bearerToken,
    String appClient = 'flutter',
  }) {
    final h = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-Client': 'mobile',
      'X-App-Client': appClient,
      'X-App-Version': appVersion,
    };
    if (bearerToken != null && bearerToken.isNotEmpty) {
      h['Authorization'] = 'Bearer $bearerToken';
    }
    return h;
  }
  
  // Timeout para las peticiones HTTP (en segundos) — 3 minutos para evitar fallos por demora de la API
  static const int httpTimeoutSeconds = 180;

  // IDs de workflow de Didit (configurar en entorno seguro / build flavors)
  // Estos valores son placeholders y deben reemplazarse por los reales desde Didit Console.
  static const String diditPacienteKycWorkflowId = 'DIDIT_WORKFLOW_PACIENTE_KYC';
  static const String diditPacienteBiometricWorkflowId = 'DIDIT_WORKFLOW_PACIENTE_BIOMETRIC';
  static const String diditMedicoKycWorkflowId = 'DIDIT_WORKFLOW_MEDICO_KYC';
  static const String diditMedicoBiometricWorkflowId = 'DIDIT_WORKFLOW_MEDICO_BIOMETRIC';

  /// user_id de Yii para «Ir al inicio» / token de prueba (solo desarrollo).
  /// `--dart-define=DEV_TEST_USER_ID=5749` (paciente) o `5748` (médico).
  static const String devTestUserId = String.fromEnvironment(
    'DEV_TEST_USER_ID',
    defaultValue: '',
  );
}

