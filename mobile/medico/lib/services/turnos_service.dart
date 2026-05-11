// lib/services/turnos_service.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

import '../models/turno.dart';

class TurnosService {
  String? authToken;
  String? userId; // Para desarrollo/simulación

  TurnosService({this.authToken, this.userId});

  Map<String, String> get _headers =>
      AppConfig.jsonHeaders(bearerToken: authToken, appClient: 'medico-flutter');

  // Obtener turnos por fecha
  Future<List<Turno>> getTurnosPorFecha(String fecha,
      {String? idProfesionalEfectorServicio}) async {
    try {
      final queryParams = <String, String>{
        'fecha': fecha,
      };
      if (idProfesionalEfectorServicio != null) {
        queryParams['id_profesional_efector_servicio'] = idProfesionalEfectorServicio;
      }
      
      // Para desarrollo/simulación: incluir user_id como parámetro si no hay token válido
      if (userId != null && (authToken == null || authToken!.isEmpty || authToken!.startsWith('simulated_'))) {
        queryParams['user_id'] = userId!;
      }

      final uri = Uri.parse('${AppConfig.apiUrl}/profesional-agenda/dia').replace(
        queryParameters: queryParams,
      );

      print('Request URL: $uri');
      print('Headers: $_headers');
      
      final response = await http.get(uri, headers: _headers);

      print('Response status: ${response.statusCode}');
      print('Response headers: ${response.headers}');
      final bodyPreview = response.body.length > 200 
          ? response.body.substring(0, 200) 
          : response.body;
      print('Response body (first 200 chars): $bodyPreview');

      // Verificar que la respuesta sea JSON, no HTML
      final contentType = response.headers['content-type'] ?? '';
      final bodyTrimmed = response.body.trim();
      
      if (bodyTrimmed.startsWith('<!DOCTYPE') || bodyTrimmed.startsWith('<html')) {
        throw Exception('La API devolvió HTML en lugar de JSON. Esto puede indicar:\n'
            '1. La URL está incorrecta: $uri\n'
            '2. Falta autenticación o el token es inválido\n'
            '3. El servidor está redirigiendo a una página de login\n\n'
            'Verifique que:\n'
            '- La URL base sea correcta: ${AppConfig.apiUrl}\n'
            '- El token de autenticación esté configurado\n'
            '- El endpoint exista en el servidor');
      }
      
      if (!contentType.contains('application/json') && !contentType.contains('json')) {
        if (response.statusCode != 200) {
          throw Exception('Error ${response.statusCode}: La respuesta no es JSON. Content-Type: $contentType');
        }
      }

      if (response.statusCode == 200) {
        try {
          final data = json.decode(response.body);
          print('[DEBUG] getTurnosPorFecha - Respuesta parseada. Keys: ${data.keys.toList()}');
          
          // Manejar diferentes estructuras de respuesta
          List<dynamic> jsonTurnos = [];
          
          // Estructura 1: { success: true, data: { turnos: [...] } }
          if (data['success'] == true && data['data'] != null) {
            print('[DEBUG] getTurnosPorFecha - Estructura 1 detectada (success + data)');
            final dataObj = data['data'] as Map<String, dynamic>;
            if (dataObj['turnos'] != null) {
              jsonTurnos = dataObj['turnos'] as List<dynamic>;
              print('[DEBUG] getTurnosPorFecha - Turnos encontrados en data.turnos: ${jsonTurnos.length}');
            }
          }
          // Estructura 2: { items: [...], _links: {...}, _meta: {...} } (Yii REST serializer)
          else if (data['items'] != null) {
            print('[DEBUG] getTurnosPorFecha - Estructura 2 detectada (items)');
            jsonTurnos = data['items'] as List<dynamic>;
            print('[DEBUG] getTurnosPorFecha - Turnos encontrados en items: ${jsonTurnos.length}');
          }
          // Estructura 3: Array directo (poco probable pero posible)
          else if (data is List) {
            print('[DEBUG] getTurnosPorFecha - Estructura 3 detectada (array directo)');
            jsonTurnos = data;
            print('[DEBUG] getTurnosPorFecha - Turnos encontrados en array: ${jsonTurnos.length}');
          }
          // Si no hay turnos pero la respuesta es válida, retornar lista vacía
          else {
            print('[DEBUG] getTurnosPorFecha - No se encontró estructura conocida. Retornando lista vacía.');
            print('[DEBUG] getTurnosPorFecha - Data completo: $data');
            return [];
          }
          
          print('[DEBUG] getTurnosPorFecha - Procesando ${jsonTurnos.length} turnos...');
          
          // Convertir los turnos a objetos Turno
          final turnos = jsonTurnos
              .map((json) {
                try {
                  return Turno.fromJson(json as Map<String, dynamic>);
                } catch (e) {
                  print('[ERROR] getTurnosPorFecha - Error al parsear turno: $e');
                  print('[ERROR] getTurnosPorFecha - JSON del turno: $json');
                  rethrow;
                }
              })
              .toList();
          
          print('[DEBUG] getTurnosPorFecha - ${turnos.length} turnos procesados exitosamente');
          return turnos;
        } catch (e) {
          print('[ERROR] getTurnosPorFecha - Error en el procesamiento: $e');
          if (e is FormatException) {
            final bodyPreview = response.body.length > 200 
                ? response.body.substring(0, 200) 
                : response.body;
            throw Exception('Error al parsear respuesta JSON. La API puede estar devolviendo HTML. Respuesta: $bodyPreview');
          }
          rethrow;
        }
      } else {
        // Intentar parsear el error como JSON, pero si falla, mostrar el cuerpo completo
        try {
          final errorData = json.decode(response.body);
          throw Exception(errorData['message'] ?? 'Error al obtener turnos (${response.statusCode})');
        } catch (e) {
          final bodyPreview = response.body.length > 200 
              ? response.body.substring(0, 200) 
              : response.body;
          throw Exception('Error ${response.statusCode}: $bodyPreview');
        }
      }
    } catch (e) {
      print('Error fetching turnos: $e');
      rethrow;
    }
  }

  /// Detalle vía `POST …/turnos/ver-turno` (UiScreenService: el GET solo devuelve descriptor UI).
  Future<Turno> getTurno(int id) async {
    try {
      final response = await http.post(
        Uri.parse('${AppConfig.apiUrl}/turnos/ver-turno'),
        headers: _headers,
        body: json.encode({'id': id}),
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body) as Map<String, dynamic>;
        final payload = data['data'];
        if (data['success'] == true && payload is Map<String, dynamic>) {
          return Turno.fromJson(payload);
        }
        throw Exception(data['message']?.toString() ?? 'Error al obtener turno');
      } else {
        final errorData = json.decode(response.body) as Map<String, dynamic>;
        throw Exception(errorData['message'] ?? 'Error al obtener turno');
      }
    } catch (e) {
      print('Error fetching turno: $e');
      rethrow;
    }
  }

  /// Alta operativa: `POST …/turnos/para-paciente` (respuesta típica 200 + `kind: ui_submit_result`).
  Future<Turno> crearTurno(Map<String, dynamic> datosTurno) async {
    try {
      final response = await http.post(
        Uri.parse('${AppConfig.apiUrl}/turnos/para-paciente'),
        headers: _headers,
        body: json.encode(datosTurno),
      );

      final ok = response.statusCode == 200 || response.statusCode == 201;
      if (ok) {
        final data = json.decode(response.body) as Map<String, dynamic>;
        final inner = data['data'];
        if (data['success'] == true && inner is Map<String, dynamic>) {
          final idRaw = inner['id'];
          final turnoId = idRaw is int ? idRaw : int.tryParse('$idRaw');
          if (turnoId != null && turnoId > 0) {
            return await getTurno(turnoId);
          }
        }
        throw Exception(data['message']?.toString() ?? 'Error al crear turno');
      } else {
        final errorData = json.decode(response.body) as Map<String, dynamic>;
        throw Exception(errorData['message'] ?? 'Error al crear turno');
      }
    } catch (e) {
      print('Error creating turno: $e');
      rethrow;
    }
  }

  /// Actualización vía `POST …/turnos/actualizar-turno` (UiScreenService no ejecuta submit en PUT).
  Future<Turno> actualizarTurno(int id, Map<String, dynamic> datosTurno) async {
    try {
      final body = <String, dynamic>{'id': id, ...datosTurno};
      final response = await http.post(
        Uri.parse('${AppConfig.apiUrl}/turnos/actualizar-turno'),
        headers: _headers,
        body: json.encode(body),
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body) as Map<String, dynamic>;
        if (data['success'] == true) {
          return await getTurno(id);
        }
        throw Exception(data['message']?.toString() ?? 'Error al actualizar turno');
      } else {
        final errorData = json.decode(response.body) as Map<String, dynamic>;
        throw Exception(errorData['message'] ?? 'Error al actualizar turno');
      }
    } catch (e) {
      print('Error updating turno: $e');
      rethrow;
    }
  }
}

