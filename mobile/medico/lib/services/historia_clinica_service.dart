// lib/services/historia_clinica_service.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

import '../models/timeline_event.dart';

class HistoriaClinicaService {
  String? authToken;

  HistoriaClinicaService({this.authToken});

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

  /// GET /api/v1/personas/{id}/historia-clinica
  Future<HistoriaClinicaResponse> getHistoriaClinica(int personaId) async {
    try {
      final response = await http.get(
        Uri.parse('${AppConfig.apiUrl}/personas/$personaId/historia-clinica'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true && data['data'] != null) {
          return HistoriaClinicaResponse.fromJson(
              data['data'] as Map<String, dynamic>);
        } else {
          throw Exception(
              data['message'] ?? 'Error al obtener historia clínica');
        }
      } else {
        final errorData = json.decode(response.body);
        throw Exception(
            errorData['message'] ?? 'Error al obtener historia clínica');
      }
    } catch (e) {
      print('Error fetching historia clínica: $e');
      rethrow;
    }
  }

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

class HistoriaClinicaResponse {
  final PersonaData persona;
  final InformacionMedica informacionMedica;
  final List<TimelineEvent> historiaClinica;
  final int totalHistoriaClinica;

  HistoriaClinicaResponse({
    required this.persona,
    required this.informacionMedica,
    required this.historiaClinica,
    required this.totalHistoriaClinica,
  });

  factory HistoriaClinicaResponse.fromJson(Map<String, dynamic> json) {
    int _parseInt(dynamic value, {int defaultValue = 0}) {
      if (value == null) return defaultValue;
      if (value is int) return value;
      if (value is String) {
        final parsed = int.tryParse(value);
        return parsed ?? defaultValue;
      }
      return defaultValue;
    }

    final rawList = json['historia_clinica'] as List<dynamic>? ??
        json['timeline'] as List<dynamic>?;
    final total = json['total_historia_clinica'] ?? json['total_eventos'];

    return HistoriaClinicaResponse(
      persona: PersonaData.fromJson(json['persona'] as Map<String, dynamic>),
      informacionMedica: InformacionMedica.fromJson(
        json['informacion_medica'] as Map<String, dynamic>,
      ),
      historiaClinica: rawList
              ?.map((e) => TimelineEvent.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      totalHistoriaClinica: _parseInt(total, defaultValue: 0),
    );
  }
}

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
      throw FormatException(
          'Expected int or String representation of int, got: $value');
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
