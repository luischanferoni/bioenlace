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

  /// URL pública de la política de privacidad (Google Play / App Store).
  /// Override: `--dart-define=PRIVACY_POLICY_URL=https://...`
  static const String privacyPolicyUrl = String.fromEnvironment(
    'PRIVACY_POLICY_URL',
    defaultValue: 'https://bioenlace.io/privacidad.html',
  );

  /// Alta institucional de consultorio unipersonal (opción A).
  /// Override: `--dart-define=INSTITUTIONAL_SIGNUP_URL=https://...`
  static const String institutionalSignupUrl = String.fromEnvironment(
    'INSTITUTIONAL_SIGNUP_URL',
    defaultValue: 'https://bioenlace.io/alta.html?perfil=consultorio',
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
    defaultValue: '1.0.2',
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

  /// Timeout para GET de descriptores UI JSON embebidos (evita spinner congelado minutos).
  static const int uiJsonHttpTimeoutSeconds = 60;

  // IDs de workflow Didit.
  // Override en CI/release: --dart-define=DIDIT_PACIENTE_KYC_WORKFLOW_ID=<uuid>
  // Si no vienen en build, la app los obtiene de GET /api/v1/registro/config-movil.
  static const String diditPacienteKycWorkflowId = String.fromEnvironment(
    'DIDIT_PACIENTE_KYC_WORKFLOW_ID',
    defaultValue: '',
  );
  static const String diditPacienteBiometricWorkflowId = String.fromEnvironment(
    'DIDIT_PACIENTE_BIOMETRIC_WORKFLOW_ID',
    defaultValue: '',
  );
  static const String diditMedicoKycWorkflowId = String.fromEnvironment(
    'DIDIT_MEDICO_KYC_WORKFLOW_ID',
    defaultValue: '',
  );
  static const String diditMedicoBiometricWorkflowId = String.fromEnvironment(
    'DIDIT_MEDICO_BIOMETRIC_WORKFLOW_ID',
    defaultValue: '',
  );

  /// Placeholder de repo o valor vacío → hay que resolver vía API o dart-define.
  static bool isDiditWorkflowPlaceholder(String workflowId) {
    final id = workflowId.trim();
    if (id.isEmpty) return true;
    return id.startsWith('DIDIT_WORKFLOW_');
  }
}

