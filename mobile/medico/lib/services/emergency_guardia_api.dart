// lib/services/emergency_guardia_api.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

/// Fila del tablero operativo de guardia (API v1 clinical/emergency-guardia/tablero).
class EmergencyBoardItem {
  final int id;
  final int idPersona;
  final String nombreCompleto;
  final String? documento;
  final String? tipoDocumento;
  final String? estado;
  final String? circuitoEstado;
  final String? circuitoEstadoLabel;
  final int? prioridadTriage;
  final int minutosEspera;
  final String? profesionalAsignado;
  final String? triageLevelLabel;
  final String? triageLevelColor;
  final String? triageReasonText;

  EmergencyBoardItem({
    required this.id,
    required this.idPersona,
    required this.nombreCompleto,
    this.documento,
    this.tipoDocumento,
    this.estado,
    this.circuitoEstado,
    this.circuitoEstadoLabel,
    this.prioridadTriage,
    this.minutosEspera = 0,
    this.profesionalAsignado,
    this.triageLevelLabel,
    this.triageLevelColor,
    this.triageReasonText,
  });

  bool get needsTriage =>
      circuitoEstado == 'espera_triage' || prioridadTriage == null;

  factory EmergencyBoardItem.fromJson(Map<String, dynamic> json) {
    final paciente = json['paciente'] as Map<String, dynamic>?;
    final triage = json['triage'] as Map<String, dynamic>?;
    return EmergencyBoardItem(
      id: (json['id'] as int?) ?? 0,
      idPersona: (json['id_persona'] as int?) ??
          (paciente?['id'] as int?) ??
          0,
      nombreCompleto: (paciente?['nombre_completo'] as String?) ??
          (json['nombre_completo'] as String?) ??
          'Sin nombre',
      documento: paciente?['documento'] as String? ?? json['documento'] as String?,
      tipoDocumento: paciente?['tipo_documento'] as String? ??
          json['tipo_documento'] as String?,
      estado: json['estado'] as String?,
      circuitoEstado: json['circuito_estado'] as String?,
      circuitoEstadoLabel: json['circuito_estado_label'] as String?,
      prioridadTriage: json['prioridad_triage'] as int?,
      minutosEspera: (json['minutos_espera'] as int?) ?? 0,
      profesionalAsignado: json['profesional_asignado'] as String?,
      triageLevelLabel: triage?['level_label'] as String?,
      triageLevelColor: triage?['level_color'] as String?,
      triageReasonText: triage?['reason_text'] as String?,
    );
  }
}

class EfectorDerivacionItem {
  final int idEfector;
  final String nombre;

  EfectorDerivacionItem({required this.idEfector, required this.nombre});

  factory EfectorDerivacionItem.fromJson(Map<String, dynamic> json) {
    return EfectorDerivacionItem(
      idEfector: (json['id_efector'] as int?) ?? 0,
      nombre: (json['nombre'] as String?) ?? '',
    );
  }
}

class EmergencyGuardiaApi {
  String? authToken;
  String? userId;

  EmergencyGuardiaApi({this.authToken, this.userId});

  Map<String, String> get _headers {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (authToken != null && authToken!.isNotEmpty) {
      headers['Authorization'] = 'Bearer $authToken';
    }
    return headers;
  }

  Future<List<EmergencyBoardItem>> getTablero({int? idEfector}) async {
    final query = <String, String>{};
    if (idEfector != null) query['id_efector'] = idEfector.toString();
    if (userId != null &&
        (authToken == null ||
            authToken!.isEmpty ||
            authToken!.startsWith('simulated_'))) {
      query['user_id'] = userId!;
    }
    final uri = Uri.parse(
      '${AppConfig.apiUrl}/clinical/emergency-guardia/tablero',
    ).replace(queryParameters: query.isNotEmpty ? query : null);

    final response = await http.get(uri, headers: _headers);
    if (response.statusCode != 200) {
      throw Exception('Error al cargar tablero (${response.statusCode})');
    }
    final decoded = json.decode(response.body);
    if (decoded is! Map<String, dynamic> || decoded['success'] != true) {
      throw Exception(
        decoded is Map ? (decoded['message'] ?? 'Error tablero') : 'Error tablero',
      );
    }
    final data = decoded['data'] as Map<String, dynamic>? ?? {};
    final items = data['items'] as List<dynamic>? ?? [];
    return items
        .map((e) => EmergencyBoardItem.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<Map<String, dynamic>> iniciarAtencion(int guardiaId) async {
    final uri = Uri.parse(
      '${AppConfig.apiUrl}/clinical/emergency-guardia/$guardiaId/iniciar-atencion',
    );
    final response = await http.post(
      uri,
      headers: _headers,
      body: '{}',
    );
    final decoded = json.decode(response.body);
    if (response.statusCode < 200 ||
        response.statusCode >= 300 ||
        decoded is! Map<String, dynamic> ||
        decoded['success'] != true) {
      throw Exception(
        decoded is Map ? (decoded['message'] ?? 'Error al iniciar atención') : 'Error',
      );
    }
    return (decoded['data'] as Map<String, dynamic>?) ?? {};
  }

  Future<void> asignar({required int guardiaId, int? idPes}) async {
    final body = <String, dynamic>{
      if (idPes != null) 'id_profesional_efector_servicio': idPes,
    };
    final uri = Uri.parse(
      '${AppConfig.apiUrl}/clinical/emergency-guardia/$guardiaId/asignar',
    );
    final response = await http.post(
      uri,
      headers: _headers,
      body: json.encode(body),
    );
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final decoded = json.decode(response.body);
      throw Exception(
        decoded is Map ? (decoded['message'] ?? 'Error al asignar') : 'Error',
      );
    }
  }

  Future<List<EfectorDerivacionItem>> listarEfectoresDerivacion({int? idEfector}) async {
    final query = <String, String>{};
    if (idEfector != null) query['id_efector'] = idEfector.toString();
    final uri = Uri.parse(
      '${AppConfig.apiUrl}/clinical/emergency-guardia/listar-efectores-derivacion',
    ).replace(queryParameters: query.isNotEmpty ? query : null);
    final response = await http.get(uri, headers: _headers);
    final decoded = json.decode(response.body);
    if (response.statusCode != 200 ||
        decoded is! Map<String, dynamic> ||
        decoded['success'] != true) {
      throw Exception(
        decoded is Map ? (decoded['message'] ?? 'Error efectores') : 'Error efectores',
      );
    }
    final data = decoded['data'] as List<dynamic>? ?? [];
    return data
        .map((e) => EfectorDerivacionItem.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<void> derivar({
    required int guardiaId,
    required int idEfectorDerivacion,
    String? condicionesDerivacion,
  }) async {
    final uri = Uri.parse(
      '${AppConfig.apiUrl}/clinical/emergency-guardia/$guardiaId/derivar',
    );
    final response = await http.post(
      uri,
      headers: _headers,
      body: json.encode({
        'id_efector_derivacion': idEfectorDerivacion,
        if (condicionesDerivacion != null && condicionesDerivacion.isNotEmpty)
          'condiciones_derivacion': condicionesDerivacion,
      }),
    );
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final decoded = json.decode(response.body);
      throw Exception(
        decoded is Map ? (decoded['message'] ?? 'Error al derivar') : 'Error',
      );
    }
  }

  Future<void> finalizar(int guardiaId) async {
    final uri = Uri.parse(
      '${AppConfig.apiUrl}/clinical/emergency-guardia/$guardiaId/finalizar',
    );
    final response = await http.post(
      uri,
      headers: _headers,
      body: '{}',
    );
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final decoded = json.decode(response.body);
      throw Exception(
        decoded is Map ? (decoded['message'] ?? 'Error al egresar') : 'Error',
      );
    }
  }

  Future<void> registrarTriage({
    required int guardiaId,
    required int level,
    required String reasonText,
    String? reasonCode,
    Map<String, dynamic>? vitals,
    int? idEfector,
  }) async {
    final body = <String, dynamic>{
      'level': level,
      'reason_text': reasonText,
      if (reasonCode != null && reasonCode.isNotEmpty) 'reason_code': reasonCode,
      if (vitals != null) 'vitals': vitals,
      if (idEfector != null) 'id_efector': idEfector,
    };
    final uri = Uri.parse(
      '${AppConfig.apiUrl}/clinical/emergency-guardia/$guardiaId/registrar-triage',
    );
    final response = await http.post(
      uri,
      headers: _headers,
      body: json.encode(body),
    );
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final decoded = json.decode(response.body);
      throw Exception(
        decoded is Map ? (decoded['message'] ?? 'Error triage') : 'Error triage',
      );
    }
  }
}
