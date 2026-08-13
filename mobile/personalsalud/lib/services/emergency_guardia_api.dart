// lib/services/emergency_guardia_api.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

/// Fila del tablero operativo de guardia (sección emergency_board en GET /api/v1/home/panel).
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
  final bool slaViolado;
  final String? slaTipo;
  /// Semáforo de espera a triage: `gris` | `naranja` | `rojo` (solo si needsTriage).
  final String? triageEsperaNivel;
  final bool internacionPendiente;
  final String? internacionIngresoUrl;
  final int ordersCount;
  final int ordersLabPending;
  final int laboratoryReportsCount;
  final int? encounterId;
  final String? encounterStatus;
  final bool identidadPendiente;

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
    this.slaViolado = false,
    this.slaTipo,
    this.triageEsperaNivel,
    this.internacionPendiente = false,
    this.internacionIngresoUrl,
    this.ordersCount = 0,
    this.ordersLabPending = 0,
    this.laboratoryReportsCount = 0,
    this.encounterId,
    this.encounterStatus,
    this.identidadPendiente = false,
  });

  bool get needsTriage =>
      circuitoEstado == 'espera_triage' || prioridadTriage == null;

  bool get puedeVerConsulta {
    final e = circuitoEstado ?? '';
    return encounterId != null &&
        encounterId! > 0 &&
        (e == 'atendido' || e == 'derivado');
  }

  factory EmergencyBoardItem.fromJson(Map<String, dynamic> json) {
    final paciente = json['paciente'] as Map<String, dynamic>?;
    final triage = json['triage'] as Map<String, dynamic>?;
    final clinical = json['clinical'] as Map<String, dynamic>? ?? {};
    final rootNombre = (json['nombre_completo'] as String?)?.trim();
    final nestedNombre = (paciente?['nombre_completo'] as String?)?.trim();
    final encId = json['encounter_id'];
    return EmergencyBoardItem(
      id: (json['id'] as int?) ?? 0,
      idPersona: (json['id_persona'] as int?) ??
          (paciente?['id'] as int?) ??
          0,
      nombreCompleto: (rootNombre != null && rootNombre.isNotEmpty)
          ? rootNombre
          : (nestedNombre != null && nestedNombre.isNotEmpty
              ? nestedNombre
              : 'Sin nombre'),
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
      slaViolado: json['sla_violado'] == true,
      slaTipo: json['sla_tipo'] as String?,
      triageEsperaNivel: json['triage_espera_nivel'] as String?,
      internacionPendiente: json['internacion_pendiente'] == true,
      internacionIngresoUrl: json['internacion_ingreso_url'] as String?,
      ordersCount: (clinical['orders_count'] as int?) ?? 0,
      ordersLabPending: (clinical['orders_lab_pending'] as int?) ?? 0,
      laboratoryReportsCount: (clinical['laboratory_reports_count'] as int?) ?? 0,
      encounterId: encId is int
          ? encId
          : int.tryParse(encId?.toString() ?? ''),
      encounterStatus: json['encounter_status'] as String?,
      identidadPendiente: json['identidad_pendiente'] == true,
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

  Future<void> solicitarInternacion(int guardiaId, {int? idEfectorInternacion}) async {
    final uri = Uri.parse(
      '${AppConfig.apiUrl}/clinical/emergency-guardia/$guardiaId/solicitar-internacion',
    );
    final response = await http.post(
      uri,
      headers: _headers,
      body: json.encode({
        if (idEfectorInternacion != null)
          'notificar_internacion_id_efector': idEfectorInternacion,
      }),
    );
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final decoded = json.decode(response.body);
      throw Exception(
        decoded is Map ? (decoded['message'] ?? 'Error internación') : 'Error',
      );
    }
  }

  Future<void> derivar({
    required int guardiaId,
    required int idEfectorDerivacion,
    String? condicionesDerivacion,
    bool solicitarInternacion = false,
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
        'solicitar_internacion': solicitarInternacion,
        if (solicitarInternacion)
          'notificar_internacion_id_efector': idEfectorDerivacion,
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
    final response = await http.post(uri, headers: _headers, body: '{}');
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final decoded = json.decode(response.body);
      throw Exception(
        decoded is Map ? (decoded['message'] ?? 'Error al egresar') : 'Error',
      );
    }
  }

  Future<List<Map<String, String>>> buscarPersonaIngreso(String q) async {
    final uri = Uri.parse(
      '${AppConfig.apiUrl}/clinical/emergency-guardia/buscar-persona-ingreso',
    ).replace(queryParameters: {'q': q});
    final response = await http.get(uri, headers: _headers);
    final decoded = json.decode(response.body);
    if (response.statusCode != 200 ||
        decoded is! Map<String, dynamic> ||
        decoded['success'] != true) {
      throw Exception(
        decoded is Map ? (decoded['message'] ?? 'Error al buscar') : 'Error al buscar',
      );
    }
    final data = decoded['data'];
    final rows = data is Map ? (data['results'] as List<dynamic>? ?? []) : [];
    return rows.map((e) {
      final m = Map<String, dynamic>.from(e as Map);
      return {
        'id': m['id']?.toString() ?? '',
        'text': m['text']?.toString() ?? '',
      };
    }).where((e) => e['id']!.isNotEmpty).toList();
  }

  /// Preview RENAPER / DNI before alta staff (`POST /registro/preview-renaper-como-staff`).
  Future<Map<String, dynamic>> previewRenaperComoStaff({
    String? documento,
    int? sexoBiologico,
    String? codigoBarras,
  }) async {
    final uri = Uri.parse(
      '${AppConfig.apiUrl}/registro/preview-renaper-como-staff',
    );
    final body = <String, dynamic>{
      if (codigoBarras != null && codigoBarras.isNotEmpty)
        'codigo_barras': codigoBarras,
      if (documento != null && documento.isNotEmpty) 'documento': documento,
      if (sexoBiologico != null) 'sexo_biologico': sexoBiologico,
    };
    final response = await http.post(
      uri,
      headers: _headers,
      body: json.encode(body),
    );
    final decoded = json.decode(response.body);
    if (response.statusCode < 200 ||
        response.statusCode >= 300 ||
        decoded is! Map<String, dynamic> ||
        decoded['success'] == false) {
      throw Exception(
        decoded is Map
            ? (decoded['message'] ?? 'No se pudo consultar la identidad')
            : 'No se pudo consultar la identidad',
      );
    }
    final data = decoded['data'];
    if (data is Map<String, dynamic>) {
      return data;
    }
    return decoded;
  }

  Future<void> ingresar(Map<String, dynamic> body) async {
    final uri = Uri.parse(
      '${AppConfig.apiUrl}/clinical/emergency-guardia/ingresar',
    );
    final response = await http.post(
      uri,
      headers: _headers,
      body: json.encode(body),
    );
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final decoded = json.decode(response.body);
      throw Exception(
        decoded is Map
            ? (decoded['message'] ?? 'Error al ingresar')
            : 'Error al ingresar',
      );
    }
  }

  Future<void> vincularIdentidad(int guardiaId, Map<String, dynamic> body) async {
    final uri = Uri.parse(
      '${AppConfig.apiUrl}/clinical/emergency-guardia/$guardiaId/vincular-identidad',
    );
    final response = await http.post(
      uri,
      headers: _headers,
      body: json.encode(body),
    );
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final decoded = json.decode(response.body);
      throw Exception(
        decoded is Map
            ? (decoded['message'] ?? 'Error al vincular identidad')
            : 'Error al vincular identidad',
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
