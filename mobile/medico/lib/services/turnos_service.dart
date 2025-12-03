// lib/services/turnos_service.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

import '../models/turno.dart';

class TurnosService {
  String? authToken;
  String? userId; // Para desarrollo/simulación

  TurnosService({this.authToken, this.userId});

  // Obtener headers con autenticación
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

  // Obtener turnos por fecha
  Future<List<Turno>> getTurnosPorFecha(String fecha, {String? rrhhId}) async {
    try {
      final queryParams = <String, String>{
        'fecha': fecha,
      };
      if (rrhhId != null) {
        queryParams['rrhh_id'] = rrhhId;
      }
      
      // Para desarrollo/simulación: incluir user_id como parámetro si no hay token válido
      if (userId != null && (authToken == null || authToken!.isEmpty || authToken!.startsWith('simulated_'))) {
        queryParams['user_id'] = userId!;
      }

      final uri = Uri.parse('${AppConfig.apiUrl}/turnos').replace(
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
          if (data['success'] == true && data['data'] != null) {
            // La estructura es: { success: true, data: { turnos: [...] } }
            final dataObj = data['data'] as Map<String, dynamic>;
            final List<dynamic> jsonTurnos = dataObj['turnos'] as List<dynamic>;
            return jsonTurnos
                .map((json) => Turno.fromJson(json as Map<String, dynamic>))
                .toList();
          } else {
            throw Exception(data['message'] ?? 'Error al obtener turnos');
          }
        } catch (e) {
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

  // Obtener detalle de un turno
  Future<Turno> getTurno(int id) async {
    try {
      final response = await http.get(
        Uri.parse('${AppConfig.apiUrl}/turnos/$id'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true && data['data'] != null) {
          return Turno.fromJson(data['data'] as Map<String, dynamic>);
        } else {
          throw Exception(data['message'] ?? 'Error al obtener turno');
        }
      } else {
        final errorData = json.decode(response.body);
        throw Exception(errorData['message'] ?? 'Error al obtener turno');
      }
    } catch (e) {
      print('Error fetching turno: $e');
      rethrow;
    }
  }

  // Crear nuevo turno
  Future<Turno> crearTurno(Map<String, dynamic> datosTurno) async {
    try {
      final response = await http.post(
        Uri.parse('${AppConfig.apiUrl}/turnos'),
        headers: _headers,
        body: json.encode(datosTurno),
      );

      if (response.statusCode == 201) {
        final data = json.decode(response.body);
        if (data['success'] == true && data['data'] != null) {
          // Obtener el turno completo después de crearlo
          final turnoId = data['data']['id'] as int;
          return await getTurno(turnoId);
        } else {
          throw Exception(data['message'] ?? 'Error al crear turno');
        }
      } else {
        final errorData = json.decode(response.body);
        throw Exception(errorData['message'] ?? 'Error al crear turno');
      }
    } catch (e) {
      print('Error creating turno: $e');
      rethrow;
    }
  }

  // Actualizar turno
  Future<Turno> actualizarTurno(int id, Map<String, dynamic> datosTurno) async {
    try {
      final response = await http.put(
        Uri.parse('${AppConfig.apiUrl}/turnos/$id'),
        headers: _headers,
        body: json.encode(datosTurno),
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true && data['data'] != null) {
          // Obtener el turno completo después de actualizarlo
          return await getTurno(id);
        } else {
          throw Exception(data['message'] ?? 'Error al actualizar turno');
        }
      } else {
        final errorData = json.decode(response.body);
        throw Exception(errorData['message'] ?? 'Error al actualizar turno');
      }
    } catch (e) {
      print('Error updating turno: $e');
      rethrow;
    }
  }
}

