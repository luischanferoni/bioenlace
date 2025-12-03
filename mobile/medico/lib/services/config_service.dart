// lib/services/config_service.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

class ConfigService {
  String? authToken;

  ConfigService({this.authToken});

  Map<String, String> get _headers {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (authToken != null) {
      headers['Authorization'] = 'Bearer $authToken';
    }
    return headers;
  }

  /// Obtener efectores del usuario autenticado
  Future<List<Efector>> getEfectores({String? userId}) async {
    try {
      // Para desarrollo/simulación: incluir user_id como parámetro si no hay token válido
      final queryParams = <String, String>{};
      if (userId != null && (authToken == null || authToken!.isEmpty || authToken!.startsWith('simulated_'))) {
        queryParams['user_id'] = userId;
      }
      
      final uri = Uri.parse('${AppConfig.apiUrl}/config/efectores').replace(
        queryParameters: queryParams.isNotEmpty ? queryParams : null,
      );
      
      print('Request URL: $uri');
      print('Headers: $_headers');
      
      final response = await http.get(
        uri,
        headers: _headers,
      );

      print('Response status: ${response.statusCode}');
      print('Response headers: ${response.headers}');
      final bodyPreview = response.body.length > 200 
          ? response.body.substring(0, 200) 
          : response.body;
      print('Response body (first 200 chars): $bodyPreview');

      // Verificar que la respuesta sea JSON, no HTML
      final bodyTrimmed = response.body.trim();
      
      if (bodyTrimmed.startsWith('<!DOCTYPE') || bodyTrimmed.startsWith('<html')) {
        throw Exception('La API devolvió HTML en lugar de JSON. Esto puede indicar:\n'
            '1. La URL está incorrecta: ${AppConfig.apiUrl}/config/efectores\n'
            '2. Falta autenticación o el token es inválido\n'
            '3. El servidor está redirigiendo a una página de login\n\n'
            'Verifique que:\n'
            '- La URL base sea correcta: ${AppConfig.apiUrl}\n'
            '- El token de autenticación esté configurado\n'
            '- El endpoint exista en el servidor');
      }

      if (response.statusCode == 200) {
        try {
          final data = json.decode(response.body);
          if (data['success'] == true && data['data'] != null) {
            final List<dynamic> jsonEfectores = data['data']['efectores'] as List<dynamic>;
            return jsonEfectores
                .map((json) => Efector.fromJson(json as Map<String, dynamic>))
                .toList();
          } else {
            throw Exception(data['message'] ?? 'Error al obtener efectores');
          }
        } catch (e) {
          if (e is FormatException) {
            throw Exception('Error al parsear respuesta JSON. La API puede estar devolviendo HTML. Respuesta: $bodyPreview');
          }
          rethrow;
        }
      } else {
        // Intentar parsear el error como JSON, pero si falla, mostrar el cuerpo completo
        try {
          final errorData = json.decode(response.body);
          throw Exception(errorData['message'] ?? 'Error al obtener efectores (${response.statusCode})');
        } catch (e) {
          final bodyPreview = response.body.length > 200 
              ? response.body.substring(0, 200) 
              : response.body;
          throw Exception('Error ${response.statusCode}: $bodyPreview');
        }
      }
    } catch (e) {
      print('Error fetching efectores: $e');
      rethrow;
    }
  }

  /// Obtener servicios de un efector
  Future<List<Servicio>> getServicios(int efectorId, {String? userId}) async {
    try {
      // Para desarrollo/simulación: incluir user_id como parámetro si no hay token válido
      final queryParams = <String, String>{
        'efector_id': efectorId.toString(),
      };
      if (userId != null && (authToken == null || authToken!.isEmpty || authToken!.startsWith('simulated_'))) {
        queryParams['user_id'] = userId;
      }
      
      final uri = Uri.parse('${AppConfig.apiUrl}/config/servicios').replace(
        queryParameters: queryParams,
      );

      print('Request URL: $uri');
      print('Headers: $_headers');
      
      final response = await http.get(uri, headers: _headers);

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true && data['data'] != null) {
          final List<dynamic> jsonServicios = data['data']['servicios'] as List<dynamic>;
          return jsonServicios
              .map((json) => Servicio.fromJson(json as Map<String, dynamic>))
              .toList();
        } else {
          throw Exception(data['message'] ?? 'Error al obtener servicios');
        }
      } else {
        final errorData = json.decode(response.body);
        throw Exception(errorData['message'] ?? 'Error al obtener servicios');
      }
    } catch (e) {
      print('Error fetching servicios: $e');
      rethrow;
    }
  }

  /// Obtener encounter classes disponibles
  Future<List<EncounterClass>> getEncounterClasses() async {
    try {
      final response = await http.get(
        Uri.parse('${AppConfig.apiUrl}/config/encounter-classes'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true && data['data'] != null) {
          final List<dynamic> jsonClasses = data['data']['encounter_classes'] as List<dynamic>;
          return jsonClasses
              .map((json) => EncounterClass.fromJson(json as Map<String, dynamic>))
              .toList();
        } else {
          throw Exception(data['message'] ?? 'Error al obtener encounter classes');
        }
      } else {
        final errorData = json.decode(response.body);
        throw Exception(errorData['message'] ?? 'Error al obtener encounter classes');
      }
    } catch (e) {
      print('Error fetching encounter classes: $e');
      rethrow;
    }
  }

  /// Establecer configuración de sesión
  Future<SessionConfig> setSession({
    required int efectorId,
    required int servicioId,
    required String encounterClass,
    String? userId,
  }) async {
    try {
      final body = {
        'efector_id': efectorId,
        'servicio_id': servicioId,
        'encounter_class': encounterClass,
      };
      
      // Para desarrollo/simulación: incluir user_id si no hay token válido
      if (userId != null && (authToken == null || authToken!.isEmpty || authToken!.startsWith('simulated_'))) {
        body['user_id'] = userId;
      }
      
      print('Request URL: ${AppConfig.apiUrl}/config/set-session');
      print('Request body: $body');
      print('Headers: $_headers');
      
      final response = await http.post(
        Uri.parse('${AppConfig.apiUrl}/config/set-session'),
        headers: _headers,
        body: json.encode(body),
      );

      print('Response status: ${response.statusCode}');
      print('Response body: ${response.body}');
      
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        print('Parsed data: $data');
        
        if (data['success'] == true && data['data'] != null) {
          try {
            return SessionConfig.fromJson(data['data'] as Map<String, dynamic>);
          } catch (e) {
            print('Error parsing SessionConfig: $e');
            print('Data received: ${data['data']}');
            rethrow;
          }
        } else {
          throw Exception(data['message'] ?? 'Error al establecer configuración');
        }
      } else {
        final errorData = json.decode(response.body);
        throw Exception(errorData['message'] ?? 'Error al establecer configuración');
      }
    } catch (e) {
      print('Error setting session: $e');
      rethrow;
    }
  }
}

// Modelos
class Efector {
  final int id;
  final String nombre;
  final int? idLocalidad;

  Efector({
    required this.id,
    required this.nombre,
    this.idLocalidad,
  });

  factory Efector.fromJson(Map<String, dynamic> json) {
    // Manejar el caso donde nombre puede ser un String o un Map
    String nombreStr;
    if (json['nombre'] is String) {
      nombreStr = json['nombre'] as String;
    } else if (json['nombre'] is Map) {
      // Si nombre es un objeto, extraer el campo 'nombre' del objeto
      nombreStr = (json['nombre'] as Map)['nombre'] as String? ?? 'Sin nombre';
    } else {
      nombreStr = 'Sin nombre';
    }
    
    return Efector(
      id: json['id_efector'] as int? ?? json['id'] as int? ?? 0,
      nombre: nombreStr,
      idLocalidad: json['id_localidad'] as int?,
    );
  }
}

class Servicio {
  final int id;
  final String nombre;
  final int idRrhhServicio;

  Servicio({
    required this.id,
    required this.nombre,
    required this.idRrhhServicio,
  });

  factory Servicio.fromJson(Map<String, dynamic> json) {
    return Servicio(
      id: (json['id'] as int?) ?? 0,
      nombre: json['nombre'] as String? ?? 'Sin nombre',
      idRrhhServicio: (json['id_rrhh_servicio'] as int?) ?? 0,
    );
  }
}

class EncounterClass {
  final String code;
  final String label;

  EncounterClass({
    required this.code,
    required this.label,
  });

  factory EncounterClass.fromJson(Map<String, dynamic> json) {
    return EncounterClass(
      code: json['code'] as String? ?? '',
      label: json['label'] as String? ?? '',
    );
  }
}

class SessionConfig {
  final Efector efector;
  final Servicio servicio;
  final EncounterClass encounterClass;
  final int rrhhId;

  SessionConfig({
    required this.efector,
    required this.servicio,
    required this.encounterClass,
    required this.rrhhId,
  });

  factory SessionConfig.fromJson(Map<String, dynamic> json) {
    return SessionConfig(
      efector: Efector.fromJson(json['efector'] as Map<String, dynamic>),
      servicio: Servicio.fromJson(json['servicio'] as Map<String, dynamic>),
      encounterClass: EncounterClass.fromJson(json['encounter_class'] as Map<String, dynamic>),
      rrhhId: (json['rrhh_id'] as int?) ?? 0,
    );
  }
}

