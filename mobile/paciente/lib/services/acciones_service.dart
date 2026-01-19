// lib/services/acciones_service.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

/// Servicio para procesar consultas en lenguaje natural y obtener acciones
/// 
/// Este servicio se conecta al mismo endpoint que usa:
/// - La web (site/acciones.php) 
/// - La app móvil del médico
/// - El chatbot del paciente
/// 
/// Endpoint: /api/v1/crud/process-query
/// El backend procesa la consulta usando UniversalQueryAgent y devuelve
/// acciones relevantes que el usuario puede realizar (solicitar turnos, 
/// ver historia clínica, etc.)
class AccionesService {
  String userId;
  final String? authToken;

  AccionesService({
    required this.userId,
    this.authToken,
  });

  /// Procesa una consulta en lenguaje natural y devuelve acciones
  /// 
  /// Ejemplos de consultas:
  /// - "Necesito solicitar un turno"
  /// - "Quiero ver mi historia clínica"
  /// - "¿Cuándo es mi próxima consulta?"
  /// 
  /// El endpoint es el mismo que usa la web y la app del médico:
  /// ${AppConfig.apiUrl}/crud/process-query
  /// 
  /// [actionId] es opcional: si se proporciona, el backend intentará buscar la acción por ID primero
  Future<Map<String, dynamic>> processQuery(String query, {String? actionId}) async {
    try {
      // Mismo endpoint que usa site/acciones.php en la web y la app del médico
      final uri = Uri.parse('${AppConfig.apiUrl}/crud/process-query');
      
      final headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      };

      // Agregar token de autenticación si existe
      if (authToken != null) {
        headers['Authorization'] = 'Bearer $authToken';
      }

      final body = <String, dynamic>{
        'query': query,
      };
      
      // Agregar action_id si se proporciona (opcional)
      if (actionId != null && actionId.isNotEmpty) {
        body['action_id'] = actionId;
      }

      final response = await http.post(
        uri,
        headers: headers,
        body: json.encode(body),
      ).timeout(
        Duration(seconds: AppConfig.httpTimeoutSeconds),
      );

      final responseData = json.decode(response.body);

      if (response.statusCode == 200) {
        // La respuesta puede venir envuelta en 'data' o directamente
        final data = responseData['data'] ?? responseData;
        
        // Si tiene explanation, es una respuesta válida aunque success sea false
        // (por compatibilidad con versiones anteriores de la API)
        if (responseData['success'] == true || data['explanation'] != null) {
          return {
            'success': true,
            'data': data,
          };
        } else {
          return {
            'success': false,
            'message': responseData['message'] ?? data['message'] ?? 'Error en la consulta',
            'data': responseData,
          };
        }
      } else {
        return {
          'success': false,
          'message': responseData['message'] ?? 'Error en la consulta',
          'errors': responseData['errors'] ?? null,
        };
      }
    } catch (e) {
      return {
        'success': false,
        'message': 'Error de conexión: ${e.toString()}',
      };
    }
  }

  /// Obtiene la configuración del formulario/wizard para una acción (GET)
  /// 
  /// Este endpoint devuelve el form_config necesario para armar el wizard
  /// GET /api/v1/crud/execute-action?action_id=...&param1=value1&param2=value2
  /// 
  /// Si todos los parámetros están presentes, devuelve el wizard en el último paso (confirmación)
  /// Si faltan parámetros, devuelve el wizard desde el principio
  Future<Map<String, dynamic>> getActionFormConfig(String actionId, {Map<String, dynamic>? params}) async {
    try {
      // Construir URI con query parameters
      final uri = Uri.parse('${AppConfig.apiUrl}/crud/execute-action').replace(
        queryParameters: {
          'action_id': actionId,
          ...?params?.map((key, value) => MapEntry(key, value.toString())),
        },
      );
      
      final headers = {
        'Accept': 'application/json',
      };

      // Agregar token de autenticación si existe
      if (authToken != null) {
        headers['Authorization'] = 'Bearer $authToken';
      }

      final response = await http.get(
        uri,
        headers: headers,
      ).timeout(
        Duration(seconds: AppConfig.httpTimeoutSeconds),
      );

      final responseData = json.decode(response.body);

      if (response.statusCode == 200) {
        if (responseData['success'] == true) {
          return {
            'success': true,
            'data': responseData['data'] ?? responseData,
          };
        } else {
          return {
            'success': false,
            'message': responseData['message'] ?? 'Error al obtener configuración del formulario',
            'data': responseData,
          };
        }
      } else {
        return {
          'success': false,
          'message': responseData['message'] ?? 'Error al obtener configuración del formulario',
          'errors': responseData['errors'] ?? null,
        };
      }
    } catch (e) {
      return {
        'success': false,
        'message': 'Error de conexión: ${e.toString()}',
      };
    }
  }

  /// Ejecuta una acción específica por su action_id (POST)
  /// 
  /// Este endpoint ejecuta la acción con los parámetros proporcionados
  /// POST /api/v1/crud/execute-action
  /// Body: {
  ///   "action_id": "efectores.indexuserefector",
  ///   "params": {} // opcional
  /// }
  Future<Map<String, dynamic>> executeAction(String actionId, {Map<String, dynamic>? params}) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/crud/execute-action');
      
      final headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      };

      // Agregar token de autenticación si existe
      if (authToken != null) {
        headers['Authorization'] = 'Bearer $authToken';
      }

      final response = await http.post(
        uri,
        headers: headers,
        body: json.encode({
          'action_id': actionId,
          'params': params ?? {},
        }),
      ).timeout(
        Duration(seconds: AppConfig.httpTimeoutSeconds),
      );

      final responseData = json.decode(response.body);

      if (response.statusCode == 200) {
        if (responseData['success'] == true) {
          return {
            'success': true,
            'data': responseData['data'] ?? responseData,
          };
        } else {
          return {
            'success': false,
            'message': responseData['message'] ?? 'Error al ejecutar la acción',
            'data': responseData,
          };
        }
      } else {
        return {
          'success': false,
          'message': responseData['message'] ?? 'Error al ejecutar la acción',
          'errors': responseData['errors'] ?? null,
        };
      }
    } catch (e) {
      return {
        'success': false,
        'message': 'Error de conexión: ${e.toString()}',
      };
    }
  }

  /// Obtiene acciones comunes disponibles
  Future<List<Map<String, dynamic>>> getCommonActions() async {
    try {
      // Por ahora retornamos acciones comunes predefinidas
      // En el futuro se puede obtener del backend
      return [
        {
          'title': 'Ver mis consultas',
          'description': 'Revisa tus consultas médicas',
          'icon': 'medical_services',
          'action': 'ver_consultas',
        },
        {
          'title': 'Agendar turno',
          'description': 'Solicita un turno médico',
          'icon': 'event',
          'action': 'agendar_turno',
        },
        {
          'title': 'Ver resultados',
          'description': 'Consulta tus estudios y resultados',
          'icon': 'description',
          'action': 'ver_resultados',
        },
        {
          'title': 'Contactar médico',
          'description': 'Comunícate con tu médico',
          'icon': 'chat',
          'action': 'contactar_medico',
        },
      ];
    } catch (e) {
      return [];
    }
  }
}

