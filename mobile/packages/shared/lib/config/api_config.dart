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
  
  // Timeout para las peticiones HTTP (en segundos) — 3 minutos para evitar fallos por demora de la API
  static const int httpTimeoutSeconds = 180;

  // IDs de workflow de Didit (configurar en entorno seguro / build flavors)
  // Estos valores son placeholders y deben reemplazarse por los reales desde Didit Console.
  static const String diditPacienteKycWorkflowId = 'DIDIT_WORKFLOW_PACIENTE_KYC';
  static const String diditPacienteBiometricWorkflowId = 'DIDIT_WORKFLOW_PACIENTE_BIOMETRIC';
  static const String diditMedicoKycWorkflowId = 'DIDIT_WORKFLOW_MEDICO_KYC';
  static const String diditMedicoBiometricWorkflowId = 'DIDIT_WORKFLOW_MEDICO_BIOMETRIC';
}

