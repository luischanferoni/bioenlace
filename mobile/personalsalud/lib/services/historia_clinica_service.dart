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
  /// HC / captura (turno pendiente o en atención). No usar para “Ver consulta” atendida.
  /// [turnoId] o [encounterId]: motivos del encounter de ese turno/consulta.
  Future<HistoriaClinicaResponse> getHistoriaClinica(
    int personaId, {
    int? turnoId,
    int? encounterId,
    String? parent,
    int? parentId,
  }) async {
    try {
      final q = <String, String>{};
      if (encounterId != null && encounterId > 0) {
        q['encounter_id'] = '$encounterId';
      } else if (turnoId != null && turnoId > 0) {
        q['turno_id'] = '$turnoId';
      } else if (parent != null &&
          parent.isNotEmpty &&
          parentId != null &&
          parentId > 0) {
        q['parent'] = parent;
        q['parent_id'] = '$parentId';
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

  /// GET /api/v1/clinical/encounter/ver-consulta-como-staff
  ///
  /// Solo lectura de lo documentado por el médico (turno atendido / “Ver consulta”).
  Future<HistoriaClinicaResponse> getConsultaComoStaff({
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
      if (q.isEmpty) {
        throw Exception('Indicá turno_id o encounter_id');
      }
      final uri = Uri.parse(
        '${AppConfig.apiUrl}/clinical/encounter/ver-consulta-como-staff',
      ).replace(queryParameters: q);

      final response = await http.get(uri, headers: _headers);
      final data = json.decode(response.body) as Map<String, dynamic>;
      if (response.statusCode == 200 &&
          data['success'] == true &&
          data['data'] != null) {
        return HistoriaClinicaResponse.fromStaffConsultaJson(
          data['data'] as Map<String, dynamic>,
        );
      }
      throw Exception(data['message'] ?? 'Error al obtener la consulta');
    } catch (e) {
      print('Error fetching consulta staff: $e');
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

class DocumentacionMedicoSeccion {
  final String titulo;
  final List<String> items;

  const DocumentacionMedicoSeccion({
    required this.titulo,
    required this.items,
  });

  factory DocumentacionMedicoSeccion.fromJson(Map<String, dynamic> json) {
    final raw = json['items'];
    return DocumentacionMedicoSeccion(
      titulo: json['titulo']?.toString() ?? '',
      items: raw is List
          ? raw.map((e) => e.toString().trim()).where((e) => e.isNotEmpty).toList()
          : const [],
    );
  }
}

class DocumentacionMedico {
  final int encounterId;
  final bool tieneDatos;
  final List<DocumentacionMedicoSeccion> secciones;

  const DocumentacionMedico({
    required this.encounterId,
    required this.tieneDatos,
    required this.secciones,
  });

  factory DocumentacionMedico.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return const DocumentacionMedico(
        encounterId: 0,
        tieneDatos: false,
        secciones: [],
      );
    }
    final raw = json['secciones'];
    return DocumentacionMedico(
      encounterId: int.tryParse(json['encounter_id']?.toString() ?? '') ?? 0,
      tieneDatos: json['tiene_datos'] == true,
      secciones: raw is List
          ? raw
              .whereType<Map>()
              .map((e) => DocumentacionMedicoSeccion.fromJson(
                    Map<String, dynamic>.from(e),
                  ))
              .where((s) => s.titulo.isNotEmpty && s.items.isNotEmpty)
              .toList()
          : const [],
    );
  }
}

class ContextoInternacionHistoria {
  final int internacionId;
  final String camaLabel;
  final String fechaInicio;
  final List<EvolucionInternacionItem> evoluciones;
  final String? medicoNombre;
  final String? motivo;

  ContextoInternacionHistoria({
    required this.internacionId,
    this.camaLabel = '',
    this.fechaInicio = '',
    this.evoluciones = const [],
    this.medicoNombre,
    this.motivo,
  });

  factory ContextoInternacionHistoria.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return ContextoInternacionHistoria(internacionId: 0);
    }
    int asInt(dynamic v) {
      if (v is int) return v;
      return int.tryParse(v?.toString() ?? '') ?? 0;
    }

    final raw = json['evoluciones'];
    final list = <EvolucionInternacionItem>[];
    if (raw is List) {
      for (final e in raw) {
        if (e is Map) {
          list.add(EvolucionInternacionItem.fromJson(
            Map<String, dynamic>.from(e),
          ));
        }
      }
    }

    String? medicoNombre;
    final medico = json['medico'];
    if (medico is Map) {
      final n = (medico['nombre'] ?? '').toString().trim();
      if (n.isNotEmpty) medicoNombre = n;
    }

    final motivo = (json['motivo'] ?? '').toString().trim();

    return ContextoInternacionHistoria(
      internacionId: asInt(json['internacion_id']),
      camaLabel: (json['cama_label'] ?? '').toString(),
      fechaInicio: (json['fecha_inicio'] ?? '').toString(),
      evoluciones: list,
      medicoNombre: medicoNombre,
      motivo: motivo.isEmpty ? null : motivo,
    );
  }
}

/// Banner unificado de episodio EMER/IMP (`contexto_episodio` en historia-clinica).
class ContextoEpisodioHistoria {
  final String tipo;
  final int episodioId;
  final String? estado;
  final String? estadoLabel;
  final String? motivo;
  final String? ingresoAt;
  final String? ubicacionLabel;
  final String? medicoNombre;
  final int? triageLevel;
  final String? triageLevelLabel;
  final String? triageLevelColor;
  final String? triageScale;
  final String? triageAt;
  final List<EpisodioAccionHistoria> acciones;

  ContextoEpisodioHistoria({
    required this.tipo,
    required this.episodioId,
    this.estado,
    this.estadoLabel,
    this.motivo,
    this.ingresoAt,
    this.ubicacionLabel,
    this.medicoNombre,
    this.triageLevel,
    this.triageLevelLabel,
    this.triageLevelColor,
    this.triageScale,
    this.triageAt,
    this.acciones = const [],
  });

  bool get esGuardia => tipo.toUpperCase() == 'GUARDIA';
  bool get esInternacion => tipo.toUpperCase() == 'INTERNACION';

  factory ContextoEpisodioHistoria.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return ContextoEpisodioHistoria(tipo: '', episodioId: 0);
    }
    int asInt(dynamic v) {
      if (v is int) return v;
      return int.tryParse(v?.toString() ?? '') ?? 0;
    }

    String? str(dynamic v) {
      final s = (v ?? '').toString().trim();
      return s.isEmpty ? null : s;
    }

    final ubicacion = json['ubicacion'];
    final equipo = json['equipo'];
    final triage = json['triage'];
    String? ubicacionLabel;
    if (ubicacion is Map) {
      ubicacionLabel = str(ubicacion['label']);
    }
    String? medicoNombre;
    if (equipo is Map) {
      final med = equipo['medico'];
      if (med is Map) {
        medicoNombre = str(med['nombre']);
      }
    }
    int? triageLevel;
    String? triageLevelLabel;
    String? triageLevelColor;
    String? triageScale;
    String? triageAt;
    if (triage is Map) {
      triageLevel = asInt(triage['level']);
      if (triageLevel == 0) triageLevel = null;
      triageLevelLabel = str(triage['level_label']);
      triageLevelColor = str(triage['level_color']);
      triageScale = str(triage['scale']);
      triageAt = str(triage['triaged_at']);
    }

    final acciones = <EpisodioAccionHistoria>[];
    final rawAcciones = json['acciones'];
    if (rawAcciones is List) {
      for (final e in rawAcciones) {
        if (e is Map) {
          acciones.add(
            EpisodioAccionHistoria.fromJson(Map<String, dynamic>.from(e)),
          );
        }
      }
    }

    return ContextoEpisodioHistoria(
      tipo: (json['tipo'] ?? '').toString(),
      episodioId: asInt(json['episodio_id']),
      estado: str(json['estado']),
      estadoLabel: str(json['estado_label']),
      motivo: str(json['motivo']),
      ingresoAt: str(json['ingreso_at']),
      ubicacionLabel: ubicacionLabel,
      medicoNombre: medicoNombre,
      triageLevel: triageLevel,
      triageLevelLabel: triageLevelLabel,
      triageLevelColor: triageLevelColor,
      triageScale: triageScale,
      triageAt: triageAt,
      acciones: acciones,
    );
  }
}

class EpisodioAccionHistoria {
  final String id;
  final String label;
  final String kind;
  final String? apiRoute;
  final String? apiMethod;

  EpisodioAccionHistoria({
    required this.id,
    required this.label,
    this.kind = 'ui_json',
    this.apiRoute,
    this.apiMethod,
  });

  factory EpisodioAccionHistoria.fromJson(Map<String, dynamic> json) {
    String? route;
    String? method;
    final api = json['api'];
    if (api is Map) {
      route = (api['route'] ?? '').toString();
      method = (api['method'] ?? 'GET').toString();
      if (route.isEmpty) route = null;
    }
    return EpisodioAccionHistoria(
      id: (json['id'] ?? '').toString(),
      label: (json['label'] ?? json['id'] ?? '').toString(),
      kind: (json['kind'] ?? 'ui_json').toString(),
      apiRoute: route,
      apiMethod: method,
    );
  }
}

class TimelineEpisodioFeed {
  final String parentType;
  final int episodioId;
  final List<TimelineEpisodioItem> items;

  TimelineEpisodioFeed({
    required this.parentType,
    required this.episodioId,
    this.items = const [],
  });

  factory TimelineEpisodioFeed.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return TimelineEpisodioFeed(parentType: '', episodioId: 0);
    }
    int asInt(dynamic v) {
      if (v is int) return v;
      return int.tryParse(v?.toString() ?? '') ?? 0;
    }

    final raw = json['items'];
    final list = <TimelineEpisodioItem>[];
    if (raw is List) {
      for (final e in raw) {
        if (e is Map) {
          list.add(TimelineEpisodioItem.fromJson(Map<String, dynamic>.from(e)));
        }
      }
    }

    return TimelineEpisodioFeed(
      parentType: (json['parent_type'] ?? '').toString(),
      episodioId: asInt(json['episodio_id']),
      items: list,
    );
  }
}

class SignosVitalesEpisodio {
  final String parentType;
  final int episodioId;
  final int totalPoints;
  final Map<String, SignosVitalesEpisodioUltimo> ultimos;
  final List<SignosVitalesEpisodioSerie> series;

  SignosVitalesEpisodio({
    required this.parentType,
    required this.episodioId,
    this.totalPoints = 0,
    this.ultimos = const {},
    this.series = const [],
  });

  bool get tieneDatos => totalPoints > 0 || series.isNotEmpty;

  factory SignosVitalesEpisodio.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return SignosVitalesEpisodio(parentType: '', episodioId: 0);
    }
    int asInt(dynamic v) {
      if (v is int) return v;
      return int.tryParse(v?.toString() ?? '') ?? 0;
    }

    final ultimos = <String, SignosVitalesEpisodioUltimo>{};
    final rawUltimos = json['ultimos'];
    if (rawUltimos is Map) {
      rawUltimos.forEach((k, v) {
        if (v is Map) {
          ultimos[k.toString()] = SignosVitalesEpisodioUltimo.fromJson(
            Map<String, dynamic>.from(v),
          );
        }
      });
    }

    final series = <SignosVitalesEpisodioSerie>[];
    final rawSeries = json['series'];
    if (rawSeries is List) {
      for (final e in rawSeries) {
        if (e is Map) {
          series.add(
            SignosVitalesEpisodioSerie.fromJson(Map<String, dynamic>.from(e)),
          );
        }
      }
    }

    return SignosVitalesEpisodio(
      parentType: (json['parent_type'] ?? '').toString(),
      episodioId: asInt(json['episodio_id']),
      totalPoints: asInt(json['total_points']),
      ultimos: ultimos,
      series: series,
    );
  }
}

class SignosVitalesEpisodioUltimo {
  final double? value;
  final String? at;
  final String? unit;
  final String? label;
  final String? source;
  final double? sistolica;
  final double? diastolica;

  SignosVitalesEpisodioUltimo({
    this.value,
    this.at,
    this.unit,
    this.label,
    this.source,
    this.sistolica,
    this.diastolica,
  });

  factory SignosVitalesEpisodioUltimo.fromJson(Map<String, dynamic> json) {
    double? asDouble(dynamic v) {
      if (v == null) return null;
      if (v is num) return v.toDouble();
      return double.tryParse(v.toString());
    }

    return SignosVitalesEpisodioUltimo(
      value: asDouble(json['value']),
      at: (json['at'] ?? '').toString().isEmpty ? null : json['at'].toString(),
      unit: (json['unit'] ?? '').toString().isEmpty ? null : json['unit'].toString(),
      label: (json['label'] ?? '').toString().isEmpty ? null : json['label'].toString(),
      source: (json['source'] ?? '').toString().isEmpty ? null : json['source'].toString(),
      sistolica: asDouble(json['sistolica']),
      diastolica: asDouble(json['diastolica']),
    );
  }
}

class SignosVitalesEpisodioSerie {
  final String metric;
  final String label;
  final String unit;
  final List<SignosVitalesEpisodioPunto> points;

  SignosVitalesEpisodioSerie({
    required this.metric,
    this.label = '',
    this.unit = '',
    this.points = const [],
  });

  factory SignosVitalesEpisodioSerie.fromJson(Map<String, dynamic> json) {
    final pts = <SignosVitalesEpisodioPunto>[];
    final raw = json['points'];
    if (raw is List) {
      for (final e in raw) {
        if (e is Map) {
          pts.add(SignosVitalesEpisodioPunto.fromJson(Map<String, dynamic>.from(e)));
        }
      }
    }
    return SignosVitalesEpisodioSerie(
      metric: (json['metric'] ?? '').toString(),
      label: (json['label'] ?? '').toString(),
      unit: (json['unit'] ?? '').toString(),
      points: pts,
    );
  }
}

class SignosVitalesEpisodioPunto {
  final String at;
  final double value;
  final String source;

  SignosVitalesEpisodioPunto({
    required this.at,
    required this.value,
    this.source = '',
  });

  factory SignosVitalesEpisodioPunto.fromJson(Map<String, dynamic> json) {
    double value = 0;
    final v = json['value'];
    if (v is num) {
      value = v.toDouble();
    } else {
      value = double.tryParse(v?.toString() ?? '') ?? 0;
    }
    return SignosVitalesEpisodioPunto(
      at: (json['at'] ?? '').toString(),
      value: value,
      source: (json['source'] ?? '').toString(),
    );
  }
}

class TimelineEpisodioItem {
  final String type;
  final String id;
  final String occurredAt;
  final String summary;
  final String? actorNombre;
  final int? encounterId;

  TimelineEpisodioItem({
    required this.type,
    required this.id,
    this.occurredAt = '',
    this.summary = '',
    this.actorNombre,
    this.encounterId,
  });

  String get typeLabel {
    switch (type) {
      case 'circuito':
        return 'Circuito';
      case 'triage':
        return 'Triage';
      case 'evolucion_medica':
        return 'Evolución';
      case 'atencion_enfermeria':
        return 'Enfermería';
      case 'pedido':
        return 'Pedido';
      case 'resultado_lab':
        return 'Lab';
      case 'medicacion':
        return 'Medicación';
      case 'administracion':
        return 'Admin.';
      case 'interconsulta':
        return 'Interconsulta';
      default:
        return type;
    }
  }

  factory TimelineEpisodioItem.fromJson(Map<String, dynamic> json) {
    int? asIntOrNull(dynamic v) {
      if (v == null) return null;
      if (v is int) return v;
      return int.tryParse(v.toString());
    }

    String? actorNombre;
    final actor = json['actor'];
    if (actor is Map) {
      final n = (actor['nombre'] ?? '').toString().trim();
      if (n.isNotEmpty) actorNombre = n;
    }

    return TimelineEpisodioItem(
      type: (json['type'] ?? '').toString(),
      id: (json['id'] ?? '').toString(),
      occurredAt: (json['occurred_at'] ?? '').toString(),
      summary: (json['summary'] ?? '').toString(),
      actorNombre: actorNombre,
      encounterId: asIntOrNull(json['encounter_id']),
    );
  }
}

class EvolucionInternacionItem {
  final int encounterId;
  final String fecha;
  final String texto;
  final String status;

  EvolucionInternacionItem({
    required this.encounterId,
    this.fecha = '',
    this.texto = '',
    this.status = '',
  });

  factory EvolucionInternacionItem.fromJson(Map<String, dynamic> json) {
    int asInt(dynamic v) {
      if (v is int) return v;
      return int.tryParse(v?.toString() ?? '') ?? 0;
    }

    return EvolucionInternacionItem(
      encounterId: asInt(json['encounter_id']),
      fecha: (json['fecha'] ?? '').toString(),
      texto: (json['texto'] ?? '').toString(),
      status: (json['status'] ?? '').toString(),
    );
  }
}

class HistoriaClinicaResponse {
  final PersonaData persona;
  final InformacionMedica informacionMedica;
  final SignosVitalesClinica signosVitales;
  final MotivosConsultaPaciente motivosConsultaPaciente;
  final ContextoInternacionHistoria? contextoInternacion;
  final ContextoEpisodioHistoria? contextoEpisodio;
  final TimelineEpisodioFeed? timelineEpisodio;
  final SignosVitalesEpisodio? signosVitalesEpisodio;
  final CarePackCohorteStaff? carePackCohorte;
  final bool careCohortHabilitado;
  final DocumentacionMedico documentacionMedico;
  final List<TimelineEvent> historiaClinica;
  final int totalHistoriaClinica;
  /// null = sin contexto de turno; true/false según estado del turno.
  final bool? capturaPermitida;
  final String? capturaMotivo;

  HistoriaClinicaResponse({
    required this.persona,
    required this.informacionMedica,
    required this.signosVitales,
    required this.motivosConsultaPaciente,
    this.contextoInternacion,
    this.contextoEpisodio,
    this.timelineEpisodio,
    this.signosVitalesEpisodio,
    this.carePackCohorte,
    this.careCohortHabilitado = false,
    required this.documentacionMedico,
    required this.historiaClinica,
    required this.totalHistoriaClinica,
    this.capturaPermitida,
    this.capturaMotivo,
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
    final captura = json['captura'];
    bool? capturaPermitida;
    String? capturaMotivo;
    if (captura is Map) {
      final p = captura['permitida'];
      if (p is bool) {
        capturaPermitida = p;
      }
      final m = captura['motivo']?.toString().trim();
      if (m != null && m.isNotEmpty) {
        capturaMotivo = m;
      }
    }

    return HistoriaClinicaResponse(
      persona: PersonaData.fromJson(json['persona'] as Map<String, dynamic>),
      informacionMedica: InformacionMedica.fromJson(
        json['informacion_medica'] as Map<String, dynamic>,
      ),
      signosVitales:
          SignosVitalesClinica.fromJson(json['signos_vitales'] as Map<String, dynamic>?),
      motivosConsultaPaciente: MotivosConsultaPaciente.fromJson(
          json['motivos_consulta_paciente'] as Map<String, dynamic>?),
      contextoInternacion: json['contexto_internacion'] is Map
          ? ContextoInternacionHistoria.fromJson(
              Map<String, dynamic>.from(json['contexto_internacion'] as Map),
            )
          : null,
      contextoEpisodio: json['contexto_episodio'] is Map
          ? ContextoEpisodioHistoria.fromJson(
              Map<String, dynamic>.from(json['contexto_episodio'] as Map),
            )
          : null,
      timelineEpisodio: json['timeline_episodio'] is Map
          ? TimelineEpisodioFeed.fromJson(
              Map<String, dynamic>.from(json['timeline_episodio'] as Map),
            )
          : null,
      signosVitalesEpisodio: json['signos_vitales_episodio'] is Map
          ? SignosVitalesEpisodio.fromJson(
              Map<String, dynamic>.from(json['signos_vitales_episodio'] as Map),
            )
          : null,
      carePackCohorte: json['care_pack_cohorte'] is Map
          ? CarePackCohorteStaff.fromJson(
              Map<String, dynamic>.from(json['care_pack_cohorte'] as Map),
            )
          : null,
      careCohortHabilitado: json['care_cohort_habilitado'] == true,
      documentacionMedico: DocumentacionMedico.fromJson(
        json['documentacion_medico'] as Map<String, dynamic>?,
      ),
      historiaClinica: rawList
              ?.map((e) => TimelineEvent.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      totalHistoriaClinica: _parseInt(total, defaultValue: 0),
      capturaPermitida: capturaPermitida,
      capturaMotivo: capturaMotivo,
    );
  }

  /// Payload de GET clinical/encounter/ver-consulta-como-staff.
  factory HistoriaClinicaResponse.fromStaffConsultaJson(
    Map<String, dynamic> json,
  ) {
    final motivos = json['motivos_consulta_paciente'];

    return HistoriaClinicaResponse(
      persona: PersonaData.fromJson(json['persona'] as Map<String, dynamic>),
      informacionMedica: InformacionMedica.fromJson(const {}),
      signosVitales: SignosVitalesClinica.fromJson(null),
      motivosConsultaPaciente: MotivosConsultaPaciente.fromJson(
        motivos is Map ? Map<String, dynamic>.from(motivos) : null,
      ),
      carePackCohorte: json['care_pack_cohorte'] is Map
          ? CarePackCohorteStaff.fromJson(
              Map<String, dynamic>.from(json['care_pack_cohorte'] as Map),
            )
          : null,
      careCohortHabilitado: json['care_pack_cohorte'] != null,
      documentacionMedico: DocumentacionMedico.fromJson(
        json['documentacion_medico'] as Map<String, dynamic>?,
      ),
      historiaClinica: const [],
      totalHistoriaClinica: 0,
      capturaPermitida: false,
      capturaMotivo: null,
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
  final int? genero;
  final String? generoTexto;

  PersonaData({
    required this.id,
    required this.nombreCompleto,
    this.documento,
    this.fechaNacimiento,
    this.edad,
    this.sexo,
    this.genero,
    this.generoTexto,
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
      genero: _parseInt(json['genero']),
      generoTexto: () {
        final raw = (json['genero_texto'] ?? json['generoTexto'])?.toString().trim();
        return (raw == null || raw.isEmpty) ? null : raw;
      }(),
    );
  }
}
