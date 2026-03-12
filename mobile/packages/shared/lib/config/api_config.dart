/// Configuración centralizada de la API para todas las aplicaciones móviles
class AppConfig {
  // URL base de la API
  // Para desarrollo local:
  // static const String apiUrl = 'http://localhost/bioenlace/api/v1';
  
  // Para servidor de producción:
  static const String apiUrl = 'https://app.bioenlace.io/api/v1';
  //static const String apiUrl = 'http://190.30.242.228:60000/bioenlace/web/api/v1';
  
  // Timeout para las peticiones HTTP (en segundos) — 3 minutos para evitar fallos por demora de la API
  static const int httpTimeoutSeconds = 180;

  // IDs de workflow de Didit (configurar en entorno seguro / build flavors)
  // Estos valores son placeholders y deben reemplazarse por los reales desde Didit Console.
  static const String diditPacienteKycWorkflowId = 'DIDIT_WORKFLOW_PACIENTE_KYC';
  static const String diditPacienteBiometricWorkflowId = 'DIDIT_WORKFLOW_PACIENTE_BIOMETRIC';
  static const String diditMedicoKycWorkflowId = 'DIDIT_WORKFLOW_MEDICO_KYC';
  static const String diditMedicoBiometricWorkflowId = 'DIDIT_WORKFLOW_MEDICO_BIOMETRIC';
}

