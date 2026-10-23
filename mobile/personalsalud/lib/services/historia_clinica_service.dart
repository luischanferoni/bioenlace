// lib/services/historia_clinica_service.dart
import 'package:shared/shared.dart';

import '../models/timeline_event.dart';

abstract final class EncounterParentType {
  static const turno = 'TURNO';
  static const encounter = 'ENCOUNTER';
  static const guardia = 'GUARDIA';
  static const internacion = 'INTERNACION';
}

UiIntent _uiIntentFromContract(dynamic value) {
  return switch ((value ?? '').toString()) {
    'primary' => UiIntent.primary,
    'secondary' => UiIntent.secondary,
    'success' => UiIntent.success,
    'danger' => UiIntent.danger,
    'warning' => UiIntent.warning,
    'info' => UiIntent.info,
    'dark' => UiIntent.dark,
    _ => UiIntent.neutral,
  };
}

class HistoriaClinicaService {
  String? authToken;

  HistoriaClinicaService({this.authToken});

  ApiClient get _api => ApiClient(authToken: authToken);

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
      final data = await _api.getData(
        '/personas/$personaId/historia-clinica',
        query: q,
      );
      return HistoriaClinicaResponse.fromJson(data);
    } on ApiContractException catch (e) {
      if (e.code == 'history_before_window' ||
          e.code == 'history_window_closed') {
        final window = e.meta['ventana_medico'];
        throw HistoriaClinicaVentanaException(
          e.message,
          ventanaMedico: window is Map
              ? Map<String, dynamic>.from(window)
              : const {},
        );
      }
      rethrow;
    } catch (e) {
      print('Error fetching historia clínica: $e');
      rethrow;
    }
  }

  /// GET /api/v1/clinical/encounter/staff-summary
  ///
  /// Solo lectura de lo documentado por el médico (turno atendido / “Ver consulta”).
  Future<HistoriaClinicaResponse> getEncounterAsStaff({
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
      final data = await _api.getData(
        '/clinical/encounter/staff-summary',
        query: q,
      );
      return HistoriaClinicaResponse.fromStaffEncounterJson(data);
    } catch (e) {
      print('Error fetching encounter staff summary: $e');
      rethrow;
    }
  }

  Future<PersonaData> getPersona(int id) async {
    try {
      final data = await _api.getData('/personas/$id');
      return PersonaData.fromJson(data);
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
        list.add(
          Map<String, dynamic>.from(e.map((k, v) => MapEntry(k.toString(), v))),
        );
      }
    }
    Map<String, dynamic>? ultimos;
    final u = json['ultimos_sv'];
    if (u is Map) {
      ultimos = Map<String, dynamic>.from(
        u.map((k, v) => MapEntry(k.toString(), v)),
      );
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

/// `patient_encounter_reasons` de la API — mensajes enviados desde la app del paciente.
class EncounterReasonMessageApi {
  final int id;
  final String content;
  final int userId;
  final String userName;
  final String messageType;
  final String createdAt;

  EncounterReasonMessageApi({
    required this.id,
    required this.content,
    required this.userId,
    required this.userName,
    required this.messageType,
    required this.createdAt,
  });

  factory EncounterReasonMessageApi.fromJson(
    Map<String, dynamic> json, {
    int? encounterId,
    String mediaScope = 'encounter-reasons',
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
        final id = encounterId ?? int.tryParse('${json['encounter_id'] ?? ''}');
        content = resolveMediaContentUrl(
          content,
          mediaScope: mediaScope,
          encounterId: id,
        );
      }
    }

    return EncounterReasonMessageApi(
      id: asInt(json['id']),
      content: content,
      userId: asInt(json['user_id']),
      userName: json['user_name'] as String? ?? '',
      messageType: json['message_type'] as String? ?? 'texto',
      createdAt: json['created_at'] as String? ?? '',
    );
  }
}

class EncounterAppointmentContext {
  final int? turnoId;
  final String? fecha;
  final String? hora;
  final String? estadoLabel;

  EncounterAppointmentContext({
    this.turnoId,
    this.fecha,
    this.hora,
    this.estadoLabel,
  });

  factory EncounterAppointmentContext.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return EncounterAppointmentContext();
    }
    final idRaw = json['id'];
    final turnoId = idRaw is int ? idRaw : int.tryParse('$idRaw');
    return EncounterAppointmentContext(
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

  HistoriaClinicaVentanaException(
    this.message, {
    this.ventanaMedico = const {},
  });

  @override
  String toString() => message;
}

class SugerenciaClinicaItem {
  final String termino;
  final String? justificacion;
  final String? tipo;

  SugerenciaClinicaItem({required this.termino, this.justificacion, this.tipo});

  factory SugerenciaClinicaItem.fromJson(Map<String, dynamic> json) {
    return SugerenciaClinicaItem(
      termino: json['termino']?.toString() ?? '',
      justificacion: json['justificacion']?.toString(),
      tipo: json['tipo']?.toString(),
    );
  }
}

class EncounterReasonSuggestions {
  final List<SugerenciaClinicaItem> diagnosticos;
  final List<SugerenciaClinicaItem> practicas;

  EncounterReasonSuggestions({
    this.diagnosticos = const [],
    this.practicas = const [],
  });

  factory EncounterReasonSuggestions.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return EncounterReasonSuggestions();
    }
    List<SugerenciaClinicaItem> mapList(String key) {
      final raw = json[key];
      if (raw is! List) return [];
      return raw
          .whereType<Map>()
          .map(
            (e) => SugerenciaClinicaItem.fromJson(Map<String, dynamic>.from(e)),
          )
          .where((e) => e.termino.isNotEmpty)
          .toList();
    }

    return EncounterReasonSuggestions(
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
          .map(
            (e) =>
                CarePackAssistanceAnswer.fromJson(Map<String, dynamic>.from(e)),
          )
          .where((e) => e.question.isNotEmpty || e.answer.isNotEmpty)
          .toList(),
    );
  }
}

class PatientEncounterReasons {
  final int? encounterId;
  final int? turnoId;
  final EncounterAppointmentContext? turno;
  final bool contextoExplicito;
  final String? resumen;
  final String? resumenIa;
  final bool resumenPendiente;
  final List<MotivoImagenAdjunta> imagenesAdjuntas;
  final EncounterReasonSuggestions? sugerenciasClinicas;
  final MotivosIntakeStaff? motivosIntake;
  final List<EncounterReasonMessageApi> messages;

  PatientEncounterReasons({
    required this.encounterId,
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

  factory PatientEncounterReasons.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return PatientEncounterReasons(encounterId: null, messages: []);
    }
    final raw = json['messages'] as List<dynamic>? ?? [];
    int? cid;
    final c = json['encounter_id'];
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
              .map(
                (e) =>
                    MotivoImagenAdjunta.fromJson(Map<String, dynamic>.from(e)),
              )
              .where((e) => e.ref.isNotEmpty)
              .toList()
        : <MotivoImagenAdjunta>[];
    final resumenTxt =
        json['resumen']?.toString() ?? json['resumen_ia']?.toString();
    final intakeMap = json['motivos_intake'];
    return PatientEncounterReasons(
      encounterId: cid,
      turnoId: tid,
      turno: turnoMap is Map
          ? EncounterAppointmentContext.fromJson(
              Map<String, dynamic>.from(turnoMap),
            )
          : null,
      contextoExplicito: json['contexto_explicito'] == true,
      resumen: resumenTxt,
      resumenIa: resumenTxt,
      resumenPendiente:
          json['resumen_pendiente'] == true ||
          json['resumen_ia_pendiente'] == true,
      imagenesAdjuntas: imgs,
      sugerenciasClinicas: sugMap is Map
          ? EncounterReasonSuggestions.fromJson(
              Map<String, dynamic>.from(sugMap),
            )
          : null,
      motivosIntake: intakeMap is Map
          ? MotivosIntakeStaff.fromJson(Map<String, dynamic>.from(intakeMap))
          : null,
      messages: raw
          .map(
            (e) => EncounterReasonMessageApi.fromJson(
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
          .map(
            (e) =>
                CarePackAssistanceAnswer.fromJson(Map<String, dynamic>.from(e)),
          )
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
      cohortProfile: profile is Map ? Map<String, dynamic>.from(profile) : null,
      assistance: CarePackAssistanceStaff.fromJson(
        json['assistance'] as Map<String, dynamic>?,
      ),
    );
  }
}

class DocumentacionMedico {
  final int encounterId;
  final bool tieneDatos;
  final List<ClinicalSection> secciones;

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
    return DocumentacionMedico(
      encounterId: int.tryParse(json['encounter_id']?.toString() ?? '') ?? 0,
      tieneDatos: json['tiene_datos'] == true,
      secciones: ClinicalSection.listFromJson(json).where((s) => s.title.isNotEmpty && !s.isEmpty).toList(),
    );
  }
}

class ContextoInternacionHistoria {
  final int internacionId;
  final String camaLabel;
  final String fechaInicio;
  final List<ClinicalSection> secciones;
  final List<EvolucionInternacionItem> evoluciones;
  final String? medicoNombre;
  final String? motivo;

  ContextoInternacionHistoria({
    required this.internacionId,
    this.camaLabel = '',
    this.fechaInicio = '',
    this.secciones = const [],
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

    final sections = ClinicalSection.listFromJson(json);
    final raw = json['evoluciones'];
    final list = <EvolucionInternacionItem>[];
    final evolutionSections = sections
        .where((section) => section.key == 'inpatient_evolution')
        .toList();
    if (evolutionSections.isNotEmpty) {
      list.addAll(evolutionSections.map(EvolucionInternacionItem.fromSection));
    } else if (raw is List) {
      for (final e in raw) {
        if (e is Map) {
          list.add(
            EvolucionInternacionItem.fromJson(Map<String, dynamic>.from(e)),
          );
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
      secciones: sections,
      evoluciones: list,
      medicoNombre: medicoNombre,
      motivo: motivo.isEmpty ? null : motivo,
    );
  }
}

/// Banner unificado de episodio EMER/IMP (`contexto_episodio` en historia-clinica).
class ContextoEpisodioHistoria {
  final String tipo;
  final String? tipoLabel;
  final UiIntent intent;
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
    this.tipoLabel,
    this.intent = UiIntent.neutral,
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

  bool get esGuardia => tipo.toUpperCase() == EncounterParentType.guardia;
  bool get esInternacion =>
      tipo.toUpperCase() == EncounterParentType.internacion;

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

    final intent = _uiIntentFromContract(json['intent']);

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
      tipoLabel: str(json['tipo_label']),
      intent: intent,
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
  final UiIntent intent;
  final BioButtonVariant variant;
  final String? apiRoute;
  final String? apiMethod;

  EpisodioAccionHistoria({
    required this.id,
    required this.label,
    this.kind = 'ui_json',
    this.intent = UiIntent.primary,
    this.variant = BioButtonVariant.outline,
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
    // Compat: algunos payloads usan api_route plano.
    if (route == null || route.isEmpty) {
      final flat = (json['api_route'] ?? '').toString().trim();
      if (flat.isNotEmpty) route = flat;
    }
    return EpisodioAccionHistoria(
      id: (json['id'] ?? '').toString(),
      label: (json['label'] ?? json['id'] ?? '').toString(),
      kind: (json['kind'] ?? 'ui_json').toString(),
      intent: _uiIntentFromContract(json['intent']),
      variant: switch ((json['variant'] ?? '').toString()) {
        'filled' => BioButtonVariant.filled,
        'soft' => BioButtonVariant.soft,
        _ => BioButtonVariant.outline,
      },
      apiRoute: route,
      apiMethod: method,
    );
  }
}

class TimelineEpisodioFeed {
  final String parentType;
  final int episodioId;
  final List<TimelineEpisodioItem> items;
  final List<TimelineEpisodioFilter> filters;

  TimelineEpisodioFeed({
    required this.parentType,
    required this.episodioId,
    this.items = const [],
    this.filters = const [],
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
    final filters = <TimelineEpisodioFilter>[];
    final rawFilters = json['filters'];
    if (rawFilters is List) {
      for (final filter in rawFilters) {
        if (filter is Map) {
          filters.add(
            TimelineEpisodioFilter.fromJson(Map<String, dynamic>.from(filter)),
          );
        }
      }
    }

    return TimelineEpisodioFeed(
      parentType: (json['parent_type'] ?? '').toString(),
      episodioId: asInt(json['episodio_id']),
      items: list,
      filters: filters,
    );
  }
}

class TimelineEpisodioFilter {
  final String id;
  final String label;
  final List<String>? types;

  const TimelineEpisodioFilter({
    required this.id,
    required this.label,
    this.types,
  });

  factory TimelineEpisodioFilter.fromJson(Map<String, dynamic> json) {
    final rawTypes = json['types'];
    return TimelineEpisodioFilter(
      id: (json['id'] ?? '').toString(),
      label: (json['label'] ?? '').toString(),
      types: rawTypes is List
          ? rawTypes.map((type) => type.toString()).toList()
          : null,
    );
  }
}

class SignosVitalesEpisodio {
  final String parentType;
  final int episodioId;
  final int totalPoints;
  final List<SignosVitalesEpisodioSeccion> sections;
  final Map<String, SignosVitalesEpisodioMetricMetadata> metricMetadata;
  final Map<String, SignosVitalesEpisodioUltimo> ultimos;
  final List<SignosVitalesEpisodioSerie> series;

  SignosVitalesEpisodio({
    required this.parentType,
    required this.episodioId,
    this.totalPoints = 0,
    this.sections = const [],
    this.metricMetadata = const {},
    this.ultimos = const {},
    this.series = const [],
  });

  bool get tieneDatos =>
      totalPoints > 0 ||
      series.isNotEmpty ||
      sections.any(
        (section) => section.fields.any((field) => field.value != null),
      );

  factory SignosVitalesEpisodio.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return SignosVitalesEpisodio(parentType: '', episodioId: 0);
    }
    final metricMetadata = <String, SignosVitalesEpisodioMetricMetadata>{};
    final rawMetricMetadata = json['metric_metadata'];
    if (rawMetricMetadata is Map) {
      rawMetricMetadata.forEach((key, value) {
        if (value is Map) {
          metricMetadata[key
              .toString()] = SignosVitalesEpisodioMetricMetadata.fromJson(
            Map<String, dynamic>.from(value),
          );
        }
      });
    }

    final sections = <SignosVitalesEpisodioSeccion>[];
    final rawSections = json['sections'];
    if (rawSections is List) {
      for (final rawSection in rawSections) {
        if (rawSection is! Map) continue;
        final section = SignosVitalesEpisodioSeccion.fromJson(
          Map<String, dynamic>.from(rawSection),
        );
        sections.add(section);
        for (final field in section.fields) {
          metricMetadata.putIfAbsent(
            field.key,
            () => SignosVitalesEpisodioMetricMetadata(
              label: field.label,
              unit: field.unit,
              intent: field.intent,
              format: field.format,
            ),
          );
        }
      }
    }

    int asInt(dynamic v) {
      if (v is int) return v;
      return int.tryParse(v?.toString() ?? '') ?? 0;
    }

    final ultimos = <String, SignosVitalesEpisodioUltimo>{};
    for (final section in sections) {
      for (final field in section.fields) {
        if (field.value == null) continue;
        ultimos[field.key] = SignosVitalesEpisodioUltimo(
          value: field.value,
          unit: field.unit.isEmpty ? null : field.unit,
          label: field.label.isEmpty ? null : field.label,
          intent: field.intent,
        );
      }
    }
    final rawUltimos = json['ultimos'];
    if (rawUltimos is Map) {
      rawUltimos.forEach((k, v) {
        if (v is Map) {
          ultimos[k.toString()] = SignosVitalesEpisodioUltimo.fromJson(
            Map<String, dynamic>.from(v),
            metadata: metricMetadata[k.toString()],
          );
        }
      });
    }

    final series = <SignosVitalesEpisodioSerie>[];
    final rawSeries = json['series'];
    if (rawSeries is List) {
      for (final e in rawSeries) {
        if (e is Map) {
          final serie = Map<String, dynamic>.from(e);
          series.add(
            SignosVitalesEpisodioSerie.fromJson(
              serie,
              metadata: metricMetadata[(serie['metric'] ?? '').toString()],
            ),
          );
        }
      }
    }

    return SignosVitalesEpisodio(
      parentType: (json['parent_type'] ?? '').toString(),
      episodioId: asInt(json['episodio_id']),
      totalPoints: asInt(json['total_points']),
      sections: sections,
      metricMetadata: metricMetadata,
      ultimos: ultimos,
      series: series,
    );
  }
}

class SignosVitalesEpisodioSeccion {
  final String key;
  final String label;
  final int order;
  final List<SignosVitalesEpisodioCampo> fields;

  const SignosVitalesEpisodioSeccion({
    required this.key,
    required this.label,
    required this.order,
    required this.fields,
  });

  factory SignosVitalesEpisodioSeccion.fromJson(Map<String, dynamic> json) {
    final rawFields = json['fields'];
    return SignosVitalesEpisodioSeccion(
      key: (json['key'] ?? '').toString(),
      label: (json['label'] ?? '').toString(),
      order: int.tryParse(json['order']?.toString() ?? '') ?? 0,
      fields: rawFields is List
          ? rawFields
                .whereType<Map>()
                .map(
                  (field) => SignosVitalesEpisodioCampo.fromJson(
                    Map<String, dynamic>.from(field),
                  ),
                )
                .toList()
          : const [],
    );
  }
}

class SignosVitalesEpisodioCampo {
  final String key;
  final String label;
  final double? value;
  final String unit;
  final UiIntent intent;
  final String format;
  final int order;

  const SignosVitalesEpisodioCampo({
    required this.key,
    required this.label,
    required this.value,
    required this.unit,
    required this.intent,
    required this.format,
    required this.order,
  });

  factory SignosVitalesEpisodioCampo.fromJson(Map<String, dynamic> json) {
    final rawValue = json['value'];
    return SignosVitalesEpisodioCampo(
      key: (json['key'] ?? '').toString(),
      label: (json['label'] ?? '').toString(),
      value: rawValue is num
          ? rawValue.toDouble()
          : double.tryParse(rawValue?.toString() ?? ''),
      unit: (json['unit'] ?? '').toString(),
      intent: _uiIntentFromContract(json['intent']),
      format: (json['format'] ?? 'text').toString(),
      order: int.tryParse(json['order']?.toString() ?? '') ?? 0,
    );
  }
}

class SignosVitalesEpisodioMetricMetadata {
  final String label;
  final String unit;
  final UiIntent intent;
  final String format;

  const SignosVitalesEpisodioMetricMetadata({
    this.label = '',
    this.unit = '',
    this.intent = UiIntent.neutral,
    this.format = 'text',
  });

  factory SignosVitalesEpisodioMetricMetadata.fromJson(
    Map<String, dynamic> json,
  ) {
    return SignosVitalesEpisodioMetricMetadata(
      label: (json['label'] ?? '').toString(),
      unit: (json['unit'] ?? '').toString(),
      intent: _uiIntentFromContract(json['intent']),
      format: (json['format'] ?? 'text').toString(),
    );
  }
}

class SignosVitalesEpisodioUltimo {
  final double? value;
  final String? at;
  final String? unit;
  final String? label;
  final UiIntent intent;
  final String? source;

  SignosVitalesEpisodioUltimo({
    this.value,
    this.at,
    this.unit,
    this.label,
    this.intent = UiIntent.neutral,
    this.source,
  });

  factory SignosVitalesEpisodioUltimo.fromJson(
    Map<String, dynamic> json, {
    SignosVitalesEpisodioMetricMetadata? metadata,
  }) {
    double? asDouble(dynamic v) {
      if (v == null) return null;
      if (v is num) return v.toDouble();
      return double.tryParse(v.toString());
    }

    return SignosVitalesEpisodioUltimo(
      value: asDouble(json['value']),
      at: (json['at'] ?? '').toString().isEmpty ? null : json['at'].toString(),
      unit: (json['unit'] ?? '').toString().isEmpty
          ? (metadata != null && metadata.unit.isNotEmpty
                ? metadata.unit
                : null)
          : json['unit'].toString(),
      label: (json['label'] ?? '').toString().isEmpty
          ? (metadata != null && metadata.label.isNotEmpty
                ? metadata.label
                : null)
          : json['label'].toString(),
      intent: (json['intent'] ?? '').toString().isEmpty
          ? metadata?.intent ?? UiIntent.neutral
          : _uiIntentFromContract(json['intent']),
      source: (json['source'] ?? '').toString().isEmpty
          ? null
          : json['source'].toString(),
    );
  }
}

class SignosVitalesEpisodioSerie {
  final String metric;
  final String label;
  final String unit;
  final UiIntent intent;
  final List<SignosVitalesEpisodioPunto> points;

  SignosVitalesEpisodioSerie({
    required this.metric,
    this.label = '',
    this.unit = '',
    this.intent = UiIntent.neutral,
    this.points = const [],
  });

  factory SignosVitalesEpisodioSerie.fromJson(
    Map<String, dynamic> json, {
    SignosVitalesEpisodioMetricMetadata? metadata,
  }) {
    final pts = <SignosVitalesEpisodioPunto>[];
    final raw = json['points'];
    if (raw is List) {
      for (final e in raw) {
        if (e is Map) {
          pts.add(
            SignosVitalesEpisodioPunto.fromJson(Map<String, dynamic>.from(e)),
          );
        }
      }
    }
    return SignosVitalesEpisodioSerie(
      metric: (json['metric'] ?? '').toString(),
      label: (json['label'] ?? '').toString().isEmpty
          ? metadata?.label ?? ''
          : json['label'].toString(),
      unit: (json['unit'] ?? '').toString().isEmpty
          ? metadata?.unit ?? ''
          : json['unit'].toString(),
      intent: (json['intent'] ?? '').toString().isEmpty
          ? metadata?.intent ?? UiIntent.neutral
          : _uiIntentFromContract(json['intent']),
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
  final String typeLabel;
  final UiIntent intent;
  final String id;
  final String occurredAt;
  final String summary;
  final String? actorNombre;
  final int? encounterId;

  TimelineEpisodioItem({
    required this.type,
    this.typeLabel = '',
    this.intent = UiIntent.neutral,
    required this.id,
    this.occurredAt = '',
    this.summary = '',
    this.actorNombre,
    this.encounterId,
  });

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
      typeLabel: (json['type_label'] ?? '').toString(),
      intent: _uiIntentFromContract(json['intent']),
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

  factory EvolucionInternacionItem.fromSection(ClinicalSection section) {
    final fields = <String, ClinicalField>{
      for (final field in section.fields) field.key: field,
    };
    int asInt(dynamic v) {
      if (v is int) return v;
      return int.tryParse(v?.toString() ?? '') ?? 0;
    }

    return EvolucionInternacionItem(
      encounterId: asInt(fields['encounter_id']?.value),
      fecha: fields['fecha']?.value ?? '',
      texto: fields['texto']?.value ?? '',
      status: fields['status']?.value ?? '',
    );
  }
}

class HistoriaClinicaResponse {
  final PersonaData persona;
  final InformacionMedica informacionMedica;
  final SignosVitalesClinica signosVitales;
  final PatientEncounterReasons patientEncounterReasons;
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
    required this.patientEncounterReasons,
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

    final rawList =
        json['historia_clinica'] as List<dynamic>? ??
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
      signosVitales: SignosVitalesClinica.fromJson(
        json['signos_vitales'] as Map<String, dynamic>?,
      ),
      patientEncounterReasons: PatientEncounterReasons.fromJson(
        json['patient_encounter_reasons'] as Map<String, dynamic>?,
      ),
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
      historiaClinica:
          rawList
              ?.map((e) => TimelineEvent.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      totalHistoriaClinica: _parseInt(total, defaultValue: 0),
      capturaPermitida: capturaPermitida,
      capturaMotivo: capturaMotivo,
    );
  }

  /// Payload de GET clinical/encounter/staff-summary.
  factory HistoriaClinicaResponse.fromStaffEncounterJson(
    Map<String, dynamic> json,
  ) {
    final motivos = json['patient_encounter_reasons'];

    return HistoriaClinicaResponse(
      persona: PersonaData.fromJson(json['persona'] as Map<String, dynamic>),
      informacionMedica: InformacionMedica.fromJson(const {}),
      signosVitales: SignosVitalesClinica.fromJson(null),
      patientEncounterReasons: PatientEncounterReasons.fromJson(
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
        'Expected int or String representation of int, got: $value',
      );
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
        final raw = (json['genero_texto'] ?? json['generoTexto'])
            ?.toString()
            .trim();
        return (raw == null || raw.isEmpty) ? null : raw;
      }(),
    );
  }
}
