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

/// Bloque `signos_vitales` de GET /personas/{id}/historia-clinica (misma forma que signos-vitales).
class SignosVitalesClinica {
  final List<Map<String, dynamic>> datosSv;
  final Map<String, dynamic>? ultimosSv;
  final int totalSv;
  final bool tieneMasSv;
  final String fechaTitulo;

  SignosVitalesClinica({
    required this.datosSv,
    this.ultimosSv,
    required this.totalSv,
    required this.tieneMasSv,
    required this.fechaTitulo,
  });

  factory SignosVitalesClinica.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return SignosVitalesClinica(
        datosSv: [],
        ultimosSv: null,
        totalSv: 0,
        tieneMasSv: false,
        fechaTitulo: '',
      );
    }
    int parseInt(dynamic value, {int defaultValue = 0}) {
      if (value == null) return defaultValue;
      if (value is int) return value;
      if (value is String) {
        final parsed = int.tryParse(value);
        return parsed ?? defaultValue;
      }
      return defaultValue;
    }

    final raw = json['datos_sv'] as List<dynamic>? ?? [];
    final list = <Map<String, dynamic>>[];
    for (final e in raw) {
      if (e is Map) {
        list.add(Map<String, dynamic>.from(
            e.map((k, v) => MapEntry(k.toString(), v))));
      }
    }
    Map<String, dynamic>? ultimos;
    final u = json['ultimos_sv'];
    if (u is Map) {
      ultimos = Map<String, dynamic>.from(
          u.map((k, v) => MapEntry(k.toString(), v)));
    }

    return SignosVitalesClinica(
      datosSv: list,
      ultimosSv: ultimos,
      totalSv: parseInt(json['total_sv']),
      tieneMasSv: json['tiene_mas_sv'] == true,
      fechaTitulo: json['fecha_titulo'] as String? ?? '',
    );
  }
}

/// `motivos_consulta_paciente` de la API — mensajes enviados desde la app del paciente.
class MotivoConsultaMensajeApi {
  final int id;
  final String content;
  final int userId;
  final String userName;
  final String messageType;
  final String createdAt;

  MotivoConsultaMensajeApi({
    required this.id,
    required this.content,
    required this.userId,
    required this.userName,
    required this.messageType,
    required this.createdAt,
  });

  factory MotivoConsultaMensajeApi.fromJson(Map<String, dynamic> json) {
    int asInt(dynamic v) {
      if (v is int) return v;
      if (v is String) return int.tryParse(v) ?? 0;
      return 0;
    }

    return MotivoConsultaMensajeApi(
      id: asInt(json['id']),
      content: json['content'] as String? ?? '',
      userId: asInt(json['user_id']),
      userName: json['user_name'] as String? ?? '',
      messageType: json['message_type'] as String? ?? 'texto',
      createdAt: json['created_at'] as String? ?? '',
    );
  }
}

class MotivosConsultaPaciente {
  final int? consultaId;
  final List<MotivoConsultaMensajeApi> messages;

  MotivosConsultaPaciente({
    required this.consultaId,
    required this.messages,
  });

  factory MotivosConsultaPaciente.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return MotivosConsultaPaciente(consultaId: null, messages: []);
    }
    final raw = json['messages'] as List<dynamic>? ?? [];
    int? cid;
    final c = json['consulta_id'];
    if (c is int) {
      cid = c;
    } else if (c != null) {
      cid = int.tryParse(c.toString());
    }
    return MotivosConsultaPaciente(
      consultaId: cid,
      messages: raw
          .map((e) =>
              MotivoConsultaMensajeApi.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}

class HistoriaClinicaResponse {
  final PersonaData persona;
  final InformacionMedica informacionMedica;
  final SignosVitalesClinica signosVitales;
  final MotivosConsultaPaciente motivosConsultaPaciente;
  final List<TimelineEvent> historiaClinica;
  final int totalHistoriaClinica;

  HistoriaClinicaResponse({
    required this.persona,
    required this.informacionMedica,
    required this.signosVitales,
    required this.motivosConsultaPaciente,
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
      signosVitales:
          SignosVitalesClinica.fromJson(json['signos_vitales'] as Map<String, dynamic>?),
      motivosConsultaPaciente: MotivosConsultaPaciente.fromJson(
          json['motivos_consulta_paciente'] as Map<String, dynamic>?),
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
