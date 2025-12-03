/// Configuración centralizada de la API para todas las aplicaciones móviles
class AppConfig {
  // URL base de la API
  // Para desarrollo local:
  // static const String apiUrl = 'http://localhost/bioenlace/api/v1';
  
  // Para servidor de producción:
  static const String apiUrl = 'http://190.30.242.228:60000/bioenlace/web/api/v1';
  
  // Timeout para las peticiones HTTP (en segundos)
  static const int httpTimeoutSeconds = 30;
}

