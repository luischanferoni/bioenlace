// lib/services/acciones_service.dart
import 'dart:async';
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

/// Mensaje amigable cuando la API tarda demasiado o hay error de conexión
const String _timeoutErrorMessage =
    'Hubo un error, por favor intente enviar el mensaje de nuevo en unos minutos.';

/// Servicio para procesar consultas en lenguaje natural y obtener acciones
/// 
/// Este servicio se conecta al mismo endpoint que usa:
/// - La web (site/acciones.php) 
/// - La app móvil del médico
/// - El chatbot del paciente
///
/// Endpoint: /api/v1/asistente/enviar
/// El backend procesa la consulta con UniversalQueryAgent (`web/common/components/Actions/`) y devuelve
/// acciones relevantes que el usuario puede realizar (solicitar turnos, 
/// ver historia clínica, etc.)
class AsistenteService {
  String userId;
  final String? authToken;
  String? currentIntentId;
  String? currentSubintentId;
  Map<String, dynamic> draft = {};

  AsistenteService({
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
  /// ${AppConfig.apiUrl}/asistente/enviar
  /// 
  /// [actionId] es opcional: si se proporciona, el backend intentará buscar la acción por ID primero
  Future<Map<String, dynamic>> procesarInteraccion(String textoInteraccionUsuario, {String? actionId}) async {
    try {
      // Mismo endpoint que usa site/acciones.php en la web y la app del médico
      final uri = Uri.parse('${AppConfig.apiUrl}/asistente/enviar');
      
      final headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      };

      // Agregar token de autenticación si existe
      if (authToken != null) {
        headers['Authorization'] = 'Bearer $authToken';
      }

      final body = <String, dynamic>{};

      // Modo intent: si hay intent activo, enviar snapshot.
      if (currentIntentId != null && currentIntentId!.isNotEmpty) {
        body['intent_id'] = currentIntentId;
        if (currentSubintentId != null && currentSubintentId!.isNotEmpty) {
          body['subintent_id'] = currentSubintentId;
        }
        body['draft'] = draft;
        body['content'] = textoInteraccionUsuario;
        // SubIntentEngine también lee claves planas del body (merge al draft).
        final idServ = draft['id_servicio_asignado'] ?? draft['id_servicio'];
        if (idServ != null && idServ.toString().trim().isNotEmpty) {
          body['id_servicio_asignado'] = idServ;
        }
      } else {
        body['content'] = textoInteraccionUsuario;
      }
      
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

        // Si viene respuesta modo intent, sincronizar estado local.
        if (data is Map<String, dynamic>) {
          final iid = data['intent_id']?.toString();
          final sid = data['subintent_id']?.toString();
          if (iid != null && iid.isNotEmpty) {
            currentIntentId = iid;
          }
          if (sid != null && sid.isNotEmpty) {
            currentSubintentId = sid;
          }
          final dd = data['draft_delta'];
          if (dd is Map) {
            draft = {...draft, ...Map<String, dynamic>.from(dd)};
          }
        }
        
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
        'message': e is TimeoutException ? _timeoutErrorMessage : 'Error de conexión: ${e.toString()}',
      };
    }
  }

  /// Obtiene la configuración del formulario/wizard para una acción (GET)
  /// 
  /// Este endpoint devuelve el form_config necesario para armar el wizard
  /// GET /api/v1/crud/ejecutar-accion?action_id=...&param1=value1&param2=value2
  /// 
  /// Si todos los parámetros están presentes, devuelve el wizard en el último paso (confirmación)
  /// Si faltan parámetros, devuelve el wizard desde el principio
  Future<Map<String, dynamic>> getActionFormConfig(String actionId, {Map<String, dynamic>? params}) async {
    try {
      // Construir query parameters
      final queryParams = <String, String>{
        'action_id': actionId,
      };
      
      // Agregar parámetros adicionales si existen
      if (params != null && params.isNotEmpty) {
        params.forEach((key, value) {
          if (value != null) {
            // Convertir el valor a string, manejando diferentes tipos
            String stringValue;
            if (value is String) {
              stringValue = value;
            } else if (value is num) {
              stringValue = value.toString();
            } else if (value is bool) {
              stringValue = value ? '1' : '0';
            } else {
              stringValue = value.toString();
            }
            queryParams[key] = stringValue;
          }
        });
      }
      
      // Construir URI con query parameters
      final uri = Uri.parse('${AppConfig.apiUrl}/crud/ejecutar-accion').replace(
        queryParameters: queryParams,
      );
      
      // Debug: imprimir URI completa (solo en desarrollo)
      print('GET execute-action URI: $uri');
      
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
        'message': e is TimeoutException ? _timeoutErrorMessage : 'Error de conexión: ${e.toString()}',
      };
    }
  }

  /// Ejecuta una acción específica por su action_id (POST)
  /// 
  /// Este endpoint ejecuta la acción con los parámetros proporcionados
  /// POST /api/v1/crud/ejecutar-accion
  /// Body: {
  ///   "action_id": "efectores.indexuserefector",
  ///   "params": {} // opcional
  /// }
  Future<Map<String, dynamic>> executeAction(String actionId, {Map<String, dynamic>? params}) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/crud/ejecutar-accion');
      
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
        'message': e is TimeoutException ? _timeoutErrorMessage : 'Error de conexión: ${e.toString()}',
      };
    }
  }

  /// GET /api/v1/acciones/comunes — mismas acciones que la SPA web (filtradas por permisos).
  Future<List<Map<String, dynamic>>> getCommonActions() async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/acciones/comunes');
      final headers = <String, String>{
        'Accept': 'application/json',
      };
      if (authToken != null) {
        headers['Authorization'] = 'Bearer $authToken';
      }

      final response = await http
          .get(uri, headers: headers)
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));

      final responseData = json.decode(response.body) as Map<String, dynamic>;
      if (response.statusCode != 200 || responseData['success'] != true) {
        return [];
      }

      final raw = responseData['actions'];
      if (raw is! List) {
        return [];
      }

      final List<Map<String, dynamic>> out = [];
      for (final item in raw) {
        if (item is! Map) {
          continue;
        }
        final m = Map<String, dynamic>.from(item);
        final name = m['name']?.toString() ?? '';
        out.add({
          'title': name,
          'description': m['description']?.toString() ?? '',
          'route': m['route']?.toString() ?? '',
          'action_id': m['action_id'],
          'icon': m['icon']?.toString() ?? 'touch_app',
          'action': m['action_id']?.toString() ?? m['route']?.toString() ?? '',
        });
      }
      return out;
    } catch (e) {
      return [];
    }
  }

  /// Enviar una interacción tipada (confirmación, chip_select, request_location, etc.)
  Future<Map<String, dynamic>> enviarInteraction(Map<String, dynamic> interaction) async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/asistente/enviar');
      final headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      };
      if (authToken != null) {
        headers['Authorization'] = 'Bearer $authToken';
      }
      final body = <String, dynamic>{
        'intent_id': currentIntentId,
        'subintent_id': currentSubintentId,
        'draft': draft,
        'interaction': interaction,
      };
      final idServ = draft['id_servicio_asignado'] ?? draft['id_servicio'];
      if (idServ != null && idServ.toString().trim().isNotEmpty) {
        body['id_servicio_asignado'] = idServ;
      }
      final response = await http.post(
        uri,
        headers: headers,
        body: json.encode(body),
      ).timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));

      final responseData = json.decode(response.body);
      final data = responseData['data'] ?? responseData;
      if (data is Map<String, dynamic>) {
        final dd = data['draft_delta'];
        if (dd is Map) {
          draft = {...draft, ...Map<String, dynamic>.from(dd)};
        }
      }
      return {
        'success': response.statusCode == 200 && responseData['success'] == true,
        'data': data,
        'message': responseData['message'],
      };
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }
}

