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
  ///
  /// [turnoId] o [encounterId]: motivos del encounter de ese turno/consulta (no el turno más reciente).
  Future<HistoriaClinicaResponse> getHistoriaClinica(
    int personaId, {
    int? turnoId,
    int? encounterId,
  }) async {
    try {
      final q = <String, String>{};
      if (encounterId != null && encounterId > 0) {
        q['encounter_id'] = '$encounterId';
      } else if (turnoId != null && turnoId > 0) {
        q['turno_id'] = '$turnoId';
      }
      final uri = Uri.parse(
        '${AppConfig.apiUrl}/personas/$personaId/historia-clinica',
      ).replace(queryParameters: q.isEmpty ? null : q);

      final response = await http.get(uri, headers: _headers);

      final data = json.decode(response.body) as Map<String, dynamic>;
      if (response.statusCode == 200) {
        if (data['success'] == true && data['data'] != null) {
          return HistoriaClinicaResponse.fromJson(
              data['data'] as Map<String, dynamic>);
        }
        throw Exception(data['message'] ?? 'Error al obtener historia clínica');
      }
      if (response.statusCode == 403) {
        final extra = data['errors'];
        final codigo = extra is Map ? extra['codigo']?.toString() : null;
        if (codigo == 'HC_ANTES_DE_VENTANA') {
          throw HistoriaClinicaVentanaException(
            data['message']?.toString() ??
                'La historia clínica aún no está disponible.',
            ventanaMedico: extra is Map
                ? Map<String, dynamic>.from(
                    extra['ventana_medico'] as Map? ?? {},
                  )
                : const {},
          );
        }
      }
      throw Exception(data['message'] ?? 'Error al obtener historia clínica');
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

  factory MotivoConsultaMensajeApi.fromJson(
    Map<String, dynamic> json, {
    int? encounterId,
    String mediaScope = 'motivos-consulta',
  }) {
    int asInt(dynamic v) {
      if (v is int) return v;
      if (v is String) return int.tryParse(v) ?? 0;
      return 0;
    }

    var content = json['content'] as String? ?? '';
    final type = json['message_type'] as String? ?? 'texto';
    if (isImageMessageType(type) ||
        type == 'audio' ||
        type == 'video' ||
        type == 'documento') {
      if (content.isNotEmpty && !isLocalMediaFilePath(content)) {
        final id = encounterId ??
            int.tryParse(
              '${json['encounter_id'] ?? json['consulta_id'] ?? ''}',
            );
        content = resolveMediaContentUrl(
          content,
          mediaScope: mediaScope,
          encounterId: id,
        );
      }
    }

    return MotivoConsultaMensajeApi(
      id: asInt(json['id']),
      content: content,
      userId: asInt(json['user_id']),
      userName: json['user_name'] as String? ?? '',
      messageType: json['message_type'] as String? ?? 'texto',
      createdAt: json['created_at'] as String? ?? '',
    );
  }
}

class MotivoConsultaTurnoContext {
  final int? turnoId;
  final String? fecha;
  final String? hora;
  final String? estadoLabel;

  MotivoConsultaTurnoContext({
    this.turnoId,
    this.fecha,
    this.hora,
    this.estadoLabel,
  });

  factory MotivoConsultaTurnoContext.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return MotivoConsultaTurnoContext();
    }
    final idRaw = json['id'];
    final turnoId = idRaw is int ? idRaw : int.tryParse('$idRaw');
    return MotivoConsultaTurnoContext(
      turnoId: turnoId,
      fecha: json['fecha']?.toString(),
      hora: json['hora']?.toString(),
      estadoLabel: json['estado_label']?.toString(),
    );
  }

  String get etiquetaCorta {
    if (fecha == null || fecha!.isEmpty) return '';
    final h = hora != null && hora!.isNotEmpty ? ' $hora' : '';
    return '$fecha$h';
  }
}

class HistoriaClinicaVentanaException implements Exception {
  final String message;
  final Map<String, dynamic> ventanaMedico;

  HistoriaClinicaVentanaException(this.message, {this.ventanaMedico = const {}});

  @override
  String toString() => message;
}

class SugerenciaClinicaItem {
  final String termino;
  final String? justificacion;
  final String? tipo;

  SugerenciaClinicaItem({
    required this.termino,
    this.justificacion,
    this.tipo,
  });

  factory SugerenciaClinicaItem.fromJson(Map<String, dynamic> json) {
    return SugerenciaClinicaItem(
      termino: json['termino']?.toString() ?? '',
      justificacion: json['justificacion']?.toString(),
      tipo: json['tipo']?.toString(),
    );
  }
}

class SugerenciasClinicasMotivos {
  final List<SugerenciaClinicaItem> diagnosticos;
  final List<SugerenciaClinicaItem> practicas;

  SugerenciasClinicasMotivos({
    this.diagnosticos = const [],
    this.practicas = const [],
  });

  factory SugerenciasClinicasMotivos.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return SugerenciasClinicasMotivos();
    }
    List<SugerenciaClinicaItem> mapList(String key) {
      final raw = json[key];
      if (raw is! List) return [];
      return raw
          .whereType<Map>()
          .map((e) => SugerenciaClinicaItem.fromJson(Map<String, dynamic>.from(e)))
          .where((e) => e.termino.isNotEmpty)
          .toList();
    }

    return SugerenciasClinicasMotivos(
      diagnosticos: mapList('diagnosticos_sugeridos'),
      practicas: mapList('practicas_sugeridas'),
    );
  }

  bool get tieneContenido => diagnosticos.isNotEmpty || practicas.isNotEmpty;
}

class MotivoImagenAdjunta {
  final String ref;
  final String url;

  MotivoImagenAdjunta({required this.ref, required this.url});

  factory MotivoImagenAdjunta.fromJson(Map<String, dynamic> json) {
    return MotivoImagenAdjunta(
      ref: json['ref']?.toString() ?? '',
      url: json['url']?.toString() ?? '',
    );
  }
}

class MotivosIntakeStaff {
  final String status;
  final String? title;
  final String? notesForStaff;
  final List<CarePackAssistanceAnswer> answers;

  MotivosIntakeStaff({
    required this.status,
    this.title,
    this.notesForStaff,
    this.answers = const [],
  });

  bool get tieneContenido =>
      answers.isNotEmpty ||
      status == 'pending' ||
      (notesForStaff != null && notesForStaff!.trim().isNotEmpty);

  factory MotivosIntakeStaff.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return MotivosIntakeStaff(status: 'pending');
    }
    final rawAnswers = json['answers'] as List<dynamic>? ?? [];
    return MotivosIntakeStaff(
      status: json['status']?.toString() ?? 'pending',
      title: json['title']?.toString(),
      notesForStaff: json['notes_for_staff']?.toString(),
      answers: rawAnswers
          .whereType<Map>()
          .map((e) => CarePackAssistanceAnswer.fromJson(
                Map<String, dynamic>.from(e),
              ))
          .where((e) => e.question.isNotEmpty || e.answer.isNotEmpty)
          .toList(),
    );
  }
}

class MotivosConsultaPaciente {
  final int? consultaId;
  final int? turnoId;
  final MotivoConsultaTurnoContext? turno;
  final bool contextoExplicito;
  final String? resumen;
  final String? resumenIa;
  final bool resumenPendiente;
  final List<MotivoImagenAdjunta> imagenesAdjuntas;
  final SugerenciasClinicasMotivos? sugerenciasClinicas;
  final MotivosIntakeStaff? motivosIntake;
  final List<MotivoConsultaMensajeApi> messages;

  MotivosConsultaPaciente({
    required this.consultaId,
    this.turnoId,
    this.turno,
    this.contextoExplicito = false,
    this.resumen,
    this.resumenIa,
    this.resumenPendiente = false,
    this.imagenesAdjuntas = const [],
    this.sugerenciasClinicas,
    this.motivosIntake,
    required this.messages,
  });

  bool get resumenIaPendiente => resumenPendiente;

  factory MotivosConsultaPaciente.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return MotivosConsultaPaciente(consultaId: null, messages: []);
    }
    final raw = json['messages'] as List<dynamic>? ?? [];
    int? cid;
    final c = json['encounter_id'] ?? json['consulta_id'];
    if (c is int) {
      cid = c;
    } else if (c != null) {
      cid = int.tryParse(c.toString());
    }
    int? tid;
    final t = json['turno_id'];
    if (t is int) {
      tid = t;
    } else if (t != null) {
      tid = int.tryParse(t.toString());
    }
    final turnoMap = json['turno'];
    final sugMap = json['sugerencias_clinicas'];
    final imgsRaw = json['imagenes_adjuntas'];
    final imgs = imgsRaw is List
        ? imgsRaw
            .whereType<Map>()
            .map((e) => MotivoImagenAdjunta.fromJson(Map<String, dynamic>.from(e)))
            .where((e) => e.ref.isNotEmpty)
            .toList()
        : <MotivoImagenAdjunta>[];
    final resumenTxt = json['resumen']?.toString() ?? json['resumen_ia']?.toString();
    final intakeMap = json['motivos_intake'];
    return MotivosConsultaPaciente(
      consultaId: cid,
      turnoId: tid,
      turno: turnoMap is Map
          ? MotivoConsultaTurnoContext.fromJson(
              Map<String, dynamic>.from(turnoMap),
            )
          : null,
      contextoExplicito: json['contexto_explicito'] == true,
      resumen: resumenTxt,
      resumenIa: resumenTxt,
      resumenPendiente: json['resumen_pendiente'] == true ||
          json['resumen_ia_pendiente'] == true,
      imagenesAdjuntas: imgs,
      sugerenciasClinicas: sugMap is Map
          ? SugerenciasClinicasMotivos.fromJson(
              Map<String, dynamic>.from(sugMap),
            )
          : null,
      motivosIntake: intakeMap is Map
          ? MotivosIntakeStaff.fromJson(
              Map<String, dynamic>.from(intakeMap),
            )
          : null,
      messages: raw
          .map(
            (e) => MotivoConsultaMensajeApi.fromJson(
              e as Map<String, dynamic>,
              encounterId: cid,
            ),
          )
          .toList(),
    );
  }
}

class CarePackAssistanceAnswer {
  final String id;
  final String question;
  final String answer;

  CarePackAssistanceAnswer({
    required this.id,
    required this.question,
    required this.answer,
  });

  factory CarePackAssistanceAnswer.fromJson(Map<String, dynamic> json) {
    return CarePackAssistanceAnswer(
      id: json['id']?.toString() ?? '',
      question: json['question']?.toString() ?? '',
      answer: json['answer']?.toString() ?? '',
    );
  }
}

class CarePackAssistanceStaff {
  final String status;
  final String? notesForStaff;
  final String? submittedAt;
  final bool deltaRequested;
  final List<CarePackAssistanceAnswer> answers;

  CarePackAssistanceStaff({
    required this.status,
    this.notesForStaff,
    this.submittedAt,
    this.deltaRequested = false,
    this.answers = const [],
  });

  bool get tieneContenido =>
      answers.isNotEmpty ||
      (notesForStaff != null && notesForStaff!.trim().isNotEmpty);

  factory CarePackAssistanceStaff.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return CarePackAssistanceStaff(status: 'pending');
    }
    final rawAnswers = json['answers'] as List<dynamic>? ?? [];
    return CarePackAssistanceStaff(
      status: json['status']?.toString() ?? 'pending',
      notesForStaff: json['notes_for_staff']?.toString(),
      submittedAt: json['submitted_at']?.toString(),
      deltaRequested: json['delta_requested'] == true,
      answers: rawAnswers
          .whereType<Map>()
          .map((e) => CarePackAssistanceAnswer.fromJson(
                Map<String, dynamic>.from(e),
              ))
          .where((e) => e.question.isNotEmpty || e.answer.isNotEmpty)
          .toList(),
    );
  }
}

class CarePackCohorteStaff {
  final int encounterId;
  final String? cohortKeyShort;
  final Map<String, dynamic>? cohortProfile;
  final CarePackAssistanceStaff assistance;

  CarePackCohorteStaff({
    required this.encounterId,
    this.cohortKeyShort,
    this.cohortProfile,
    required this.assistance,
  });

  bool get tieneContenido => assistance.tieneContenido;

  factory CarePackCohorteStaff.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return CarePackCohorteStaff(
        encounterId: 0,
        assistance: CarePackAssistanceStaff(status: 'pending'),
      );
    }
    final profile = json['cohort_profile'];
    return CarePackCohorteStaff(
      encounterId: int.tryParse(json['encounter_id']?.toString() ?? '') ?? 0,
      cohortKeyShort: json['cohort_key_short']?.toString(),
      cohortProfile: profile is Map
          ? Map<String, dynamic>.from(profile)
          : null,
      assistance: CarePackAssistanceStaff.fromJson(
        json['assistance'] as Map<String, dynamic>?,
      ),
    );
  }
}

class HistoriaClinicaResponse {
  final PersonaData persona;
  final InformacionMedica informacionMedica;
  final SignosVitalesClinica signosVitales;
  final MotivosConsultaPaciente motivosConsultaPaciente;
  final CarePackCohorteStaff? carePackCohorte;
  final bool careCohortHabilitado;
  final List<TimelineEvent> historiaClinica;
  final int totalHistoriaClinica;

  HistoriaClinicaResponse({
    required this.persona,
    required this.informacionMedica,
    required this.signosVitales,
    required this.motivosConsultaPaciente,
    this.carePackCohorte,
    this.careCohortHabilitado = false,
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
      carePackCohorte: json['care_pack_cohorte'] is Map
          ? CarePackCohorteStaff.fromJson(
              Map<String, dynamic>.from(json['care_pack_cohorte'] as Map),
            )
          : null,
      careCohortHabilitado: json['care_cohort_habilitado'] == true,
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
