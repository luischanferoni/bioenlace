// lib/services/timeline_service.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

import '../models/timeline_event.dart';

class TimelineService {
  String? authToken;

  TimelineService({this.authToken});

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

  // Obtener timeline completo de una persona
  Future<TimelineResponse> getTimeline(int personaId) async {
    try {
      final response = await http.get(
        Uri.parse('${AppConfig.apiUrl}/personas/$personaId/timeline'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true && data['data'] != null) {
          return TimelineResponse.fromJson(data['data'] as Map<String, dynamic>);
        } else {
          throw Exception(data['message'] ?? 'Error al obtener timeline');
        }
      } else {
        final errorData = json.decode(response.body);
        throw Exception(errorData['message'] ?? 'Error al obtener timeline');
      }
    } catch (e) {
      print('Error fetching timeline: $e');
      rethrow;
    }
  }

  // Obtener datos de una persona
  Future<PersonaData> getPersona(int id) async {
    try {
      final response = await http.get(
        Uri.parse('${AppConfig.apiUrl}/personas/$id'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true && data['data'] != null) {
          return PersonaData.fromJson(data['data'] as Map<String, dynamic>);
        } else {
          throw Exception(data['message'] ?? 'Error al obtener persona');
        }
      } else {
        final errorData = json.decode(response.body);
        throw Exception(errorData['message'] ?? 'Error al obtener persona');
      }
    } catch (e) {
      print('Error fetching persona: $e');
      rethrow;
    }
  }
}

// Modelo para la respuesta completa del timeline
class TimelineResponse {
  final PersonaData persona;
  final InformacionMedica informacionMedica;
  final List<TimelineEvent> timeline;
  final int totalEventos;

  TimelineResponse({
    required this.persona,
    required this.informacionMedica,
    required this.timeline,
    required this.totalEventos,
  });

  factory TimelineResponse.fromJson(Map<String, dynamic> json) {
    // Helper para convertir String o int a int de forma segura
    int _parseInt(dynamic value, {int defaultValue = 0}) {
      if (value == null) return defaultValue;
      if (value is int) return value;
      if (value is String) {
        final parsed = int.tryParse(value);
        return parsed ?? defaultValue;
      }
      return defaultValue;
    }

    return TimelineResponse(
      persona: PersonaData.fromJson(json['persona'] as Map<String, dynamic>),
      informacionMedica: InformacionMedica.fromJson(
        json['informacion_medica'] as Map<String, dynamic>,
      ),
      timeline: (json['timeline'] as List<dynamic>?)
              ?.map((e) => TimelineEvent.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      totalEventos: _parseInt(json['total_eventos'], defaultValue: 0),
    );
  }
}

// Modelo para datos de persona
class PersonaData {
  final int id;
  final String nombreCompleto;
  final String? documento;
  final String? fechaNacimiento;
  final int? edad;
  final String? sexo;

  PersonaData({
    required this.id,
    required this.nombreCompleto,
    this.documento,
    this.fechaNacimiento,
    this.edad,
    this.sexo,
  });

  factory PersonaData.fromJson(Map<String, dynamic> json) {
    // Helper para convertir String o int a int de forma segura
    int? _parseInt(dynamic value) {
      if (value == null) return null;
      if (value is int) return value;
      if (value is String) return int.tryParse(value);
      return null;
    }

    int _parseIntRequired(dynamic value) {
      if (value is int) return value;
      if (value is String) {
        final parsed = int.tryParse(value);
        if (parsed != null) return parsed;
      }
      throw FormatException('Expected int or String representation of int, got: $value');
    }

    return PersonaData(
      id: _parseIntRequired(json['id']),
      nombreCompleto: json['nombre_completo'] as String? ?? 'Sin nombre',
      documento: json['documento'] as String?,
      fechaNacimiento: json['fecha_nacimiento'] as String?,
      edad: _parseInt(json['edad']),
      sexo: json['sexo'] as String?,
    );
  }
}

