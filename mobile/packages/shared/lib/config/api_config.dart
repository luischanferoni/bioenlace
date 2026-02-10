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
}

