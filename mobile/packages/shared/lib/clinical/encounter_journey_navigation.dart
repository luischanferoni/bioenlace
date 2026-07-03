import 'package:flutter/material.dart';

import '../format/datetime_friendly.dart';
import 'care_pack_navigation.dart';

/// Fases pre-turno del hub «Preparar tu consulta».
const List<String> kEncounterJourneyPreTurnoPhases = [
  'motivos_consulta',
  'asistencia_pre_consulta',
];

/// Fases post-turno del hub «Seguimiento post-consulta».
const List<String> kEncounterJourneyPostTurnoPhases = [
  'post_consulta',
];

const String kEncounterJourneyPhaseMotivos = 'motivos_consulta';
const String kEncounterJourneyPhaseAsistencia = 'asistencia_pre_consulta';
const String kEncounterJourneyPhasePostConsulta = 'post_consulta';

const Set<String> kEncounterJourneyPushTypes = {
  'JOURNEY_MOTIVOS_RECORDATORIO',
  'JOURNEY_MOTIVOS_ULTIMO_AVISO',
  'JOURNEY_PRECONSULTA_RECORDATORIO',
  'JOURNEY_POSTCONSULTA_DISPONIBLE',
  'JOURNEY_POSTCONSULTA_RECORDATORIO',
};

class JourneyHubEntry {
  final String phaseId;
  final String label;
  final String? subtitle;
  final bool enabled;
  final int? touchpointId;

  const JourneyHubEntry({
    required this.phaseId,
    required this.label,
    this.subtitle,
    required this.enabled,
    this.touchpointId,
  });
}

bool turnoTieneJourneyPayload(Map<String, dynamic> turno) {
  return turno['journey'] is Map;
}

Map<String, dynamic>? journeyPhase(
  Map<String, dynamic> turno,
  String phaseId,
) {
  final journey = turno['journey'];
  if (journey is! Map) return null;
  final phases = journey['phases'];
  if (phases is! Map) return null;
  var phase = phases[phaseId];
  if (phase == null && phaseId == 'motivos_intake') {
    phase = phases[kEncounterJourneyPhaseMotivos];
  }
  if (phase is! Map) return null;
  return Map<String, dynamic>.from(phase);
}

List<JourneyHubEntry> journeyHubEntries(
  Map<String, dynamic> turno,
  List<String> phaseIds,
) {
  if (!turnoTieneJourneyPayload(turno)) return [];
  final out = <JourneyHubEntry>[];
  for (final phaseId in phaseIds) {
    final phase = journeyPhase(turno, phaseId);
    if (phase == null) continue;
    if (phase['applies'] != true) continue;
    if (phase['completed'] == true) continue;

    if (phaseId == kEncounterJourneyPhasePostConsulta) {
      out.addAll(_postConsultaHubEntries(phase));
      continue;
    }

    out.add(
      JourneyHubEntry(
        phaseId: phaseId,
        label: phase['label']?.toString() ?? 'Paso del recorrido',
        subtitle: subtituloFaseJourney(phase),
        enabled: phase['enabled'] == true,
      ),
    );
  }
  return out;
}

List<JourneyHubEntry> _postConsultaHubEntries(Map<String, dynamic> phase) {
  final defaultLabel =
      phase['label']?.toString() ?? 'Seguimiento post-consulta';
  final followup = phase['followup'];
  if (followup is! Map) {
    return [
      JourneyHubEntry(
        phaseId: kEncounterJourneyPhasePostConsulta,
        label: defaultLabel,
        subtitle: subtituloFaseJourney(phase),
        enabled: phase['enabled'] == true,
      ),
    ];
  }

  final items = followup['items'];
  if (items is! List || items.isEmpty) {
    return [
      JourneyHubEntry(
        phaseId: kEncounterJourneyPhasePostConsulta,
        label: defaultLabel,
        subtitle: subtituloFaseJourney(phase),
        enabled: phase['enabled'] == true,
      ),
    ];
  }

  final out = <JourneyHubEntry>[];
  for (final raw in items) {
    if (raw is! Map) continue;
    if (raw['completed'] == true) continue;
    final title = raw['title']?.toString().trim();
    final label = title != null && title.isNotEmpty ? title : defaultLabel;
    final actionable = raw['actionable'] == true;
    final touchpointId = _touchpointIdDesdeMap(raw);
    String? subtitle;
    if (!actionable) {
      final runAt = raw['run_at']?.toString();
      if (runAt != null && runAt.isNotEmpty) {
        final when = formatNotificacionFecha(runAt);
        if (when.isNotEmpty) subtitle = 'Disponible desde $when';
      } else {
        subtitle = subtituloFaseJourney(phase);
      }
    }
    out.add(
      JourneyHubEntry(
        phaseId: kEncounterJourneyPhasePostConsulta,
        label: label,
        subtitle: subtitle,
        enabled: actionable && touchpointId != null,
        touchpointId: touchpointId,
      ),
    );
  }

  if (out.isEmpty) {
    return [
      JourneyHubEntry(
        phaseId: kEncounterJourneyPhasePostConsulta,
        label: defaultLabel,
        subtitle: subtituloFaseJourney(phase),
        enabled: false,
      ),
    ];
  }
  return out;
}

int? _touchpointIdDesdeMap(Map raw) {
  final id = raw['id'];
  if (id is int) return id > 0 ? id : null;
  return int.tryParse(id?.toString() ?? '');
}

List<MapEntry<String, Map<String, dynamic>>> prepararConsultaFasesPendientes(
  Map<String, dynamic> turno,
) {
  if (!turnoTieneJourneyPayload(turno)) return [];
  final out = <MapEntry<String, Map<String, dynamic>>>[];
  for (final id in kEncounterJourneyPreTurnoPhases) {
    final phase = journeyPhase(turno, id);
    if (phase == null) continue;
    if (phase['applies'] != true) continue;
    if (phase['completed'] == true) continue;
    out.add(MapEntry(id, phase));
  }
  return out;
}

bool prepararConsultaTienePendientes(Map<String, dynamic> turno) {
  return journeyHubEntries(turno, kEncounterJourneyPreTurnoPhases).isNotEmpty;
}

bool seguimientoPostConsultaTienePendientes(Map<String, dynamic> turno) {
  final phase = journeyPhase(turno, kEncounterJourneyPhasePostConsulta);
  if (phase == null || phase['applies'] != true || phase['completed'] == true) {
    return false;
  }
  final followup = phase['followup'];
  if (followup is Map) {
    final count = followup['touchpoint_count'];
    if (count is int && count <= 0) {
      return false;
    }
  }
  return journeyHubEntries(turno, kEncounterJourneyPostTurnoPhases).isNotEmpty;
}

bool seguimientoPostConsultaTieneAccionDisponible(Map<String, dynamic> turno) {
  return journeyHubEntries(turno, kEncounterJourneyPostTurnoPhases)
      .any((e) => e.enabled);
}

bool prepararConsultaTieneAccionDisponible(Map<String, dynamic> turno) {
  return journeyHubEntries(turno, kEncounterJourneyPreTurnoPhases)
      .any((e) => e.enabled);
}

int? encounterIdDesdeTurno(Map<String, dynamic> turno) {
  final raw = turno['encounter_id'] ?? turno['id_consulta'];
  if (raw is int) return raw > 0 ? raw : null;
  if (raw == null) return null;
  final n = int.tryParse(raw.toString());
  return n != null && n > 0 ? n : null;
}

int? touchpointIdDesdeFasePostConsulta(Map<String, dynamic> phase) {
  final followup = phase['followup'];
  if (followup is! Map) return null;
  final raw = followup['next_touchpoint_id'];
  if (raw is int) return raw > 0 ? raw : null;
  return int.tryParse(raw?.toString() ?? '');
}

String tituloMotivosDesdeTurno(Map<String, dynamic> turno) {
  final fecha = turno['fecha']?.toString() ?? '';
  final hora = turno['hora']?.toString() ?? '';
  final horaCorta = hora.length >= 5 ? hora.substring(0, 5) : hora;
  if (fecha.isEmpty) return 'Motivos de consulta';
  if (horaCorta.isEmpty) return 'Motivos · $fecha';
  return 'Motivos · $fecha · $horaCorta';
}

String? subtituloFaseJourney(Map<String, dynamic> phase) {
  if (phase['enabled'] == true) return null;
  final window = phase['window'];
  if (window is! Map) return 'Próximamente';
  final abreEn = window['abre_en']?.toString();
  if (abreEn != null && abreEn.isNotEmpty) {
    final label = formatNotificacionFecha(abreEn);
    if (label.isNotEmpty) return 'Disponible desde $label';
  }
  final cierraEn = window['cierra_en']?.toString();
  if (cierraEn != null && cierraEn.isNotEmpty) {
    final label = formatNotificacionFecha(cierraEn);
    if (label.isNotEmpty) return 'Disponible hasta $label';
  }
  return 'No disponible por ahora';
}

typedef AbrirMotivosConsulta = void Function(
  BuildContext context, {
  required int consultaId,
  required String titulo,
});

/// Abre la superficie declarada en metadata (`surface`, `action_id`).
void abrirFaseEncounterJourney({
  required BuildContext context,
  required Map<String, dynamic> turno,
  required String phaseId,
  String? authToken,
  int? subjectPersonaId,
  int? touchpointId,
  required AbrirMotivosConsulta onOpenMotivos,
  String appClient = 'paciente-flutter',
}) {
  final phase = journeyPhase(turno, phaseId);
  if (phase == null) return;

  final surface = phase['surface']?.toString() ?? '';
  if (surface == 'chat_motivos') {
    if (phase['enabled'] != true) return;
    final consultaId = encounterIdDesdeTurno(turno);
    if (consultaId == null) return;
    onOpenMotivos(
      context,
      consultaId: consultaId,
      titulo: tituloMotivosDesdeTurno(turno),
    );
    return;
  }

  if (surface == 'flow') {
    if (phase['enabled'] != true) return;
    final apiPath = phase['api_path']?.toString().trim() ?? '';
    final turnoId = turnoIdDesdePayloadProducto(turno);
    if (apiPath.isNotEmpty && turnoId != null) {
      abrirEncounterJourneyFlowApi(
        context: context,
        apiPath: apiPath,
        turnoId: turnoId,
        authToken: authToken,
        subjectPersonaId: subjectPersonaId,
        appClient: appClient,
      );
      return;
    }
    if (turnoId == null) return;
    abrirAsistenciaPreConsulta(
      context: context,
      turnoId: turnoId,
      authToken: authToken,
      subjectPersonaId: subjectPersonaId,
      appClient: appClient,
    );
    return;
  }

  if (surface == 'pack_followup') {
    final tid = touchpointId ?? touchpointIdDesdeFasePostConsulta(phase);
    if (tid == null || tid <= 0) return;
    abrirSeguimientoPostConsulta(
      context: context,
      touchpointId: tid,
      authToken: authToken,
      appClient: appClient,
    );
  }
}

void abrirJourneyHubEntry({
  required BuildContext context,
  required Map<String, dynamic> turno,
  required JourneyHubEntry entry,
  String? authToken,
  int? subjectPersonaId,
  required AbrirMotivosConsulta onOpenMotivos,
  String appClient = 'paciente-flutter',
}) {
  if (!entry.enabled) return;
  abrirFaseEncounterJourney(
    context: context,
    turno: turno,
    phaseId: entry.phaseId,
    authToken: authToken,
    subjectPersonaId: subjectPersonaId,
    touchpointId: entry.touchpointId,
    onOpenMotivos: onOpenMotivos,
    appClient: appClient,
  );
}

/// Payload push de recordatorios del recorrido (`id_turno`, `phase`, `encounter_id`, `touchpoint_id`).
Map<String, String>? journeyPushDesdeData(Map<String, dynamic> data) {
  final type = data['type']?.toString() ?? '';
  if (kEncounterJourneyPushTypes.contains(type)) {
    final idTurno = data['id_turno']?.toString() ?? '';
    if (idTurno.isEmpty) return null;
    return {
      'type': type,
      'id_turno': idTurno,
      if (data['phase'] != null) 'phase': data['phase'].toString(),
      if (data['encounter_id'] != null)
        'encounter_id': data['encounter_id'].toString(),
      if (data['touchpoint_id'] != null)
        'touchpoint_id': data['touchpoint_id'].toString(),
    };
  }
  if (type == 'CARE_FOLLOWUP_TOUCHPOINT') {
    final idTurno = data['id_turno']?.toString() ?? '';
    final touchpointId = data['touchpoint_id']?.toString() ?? '';
    if (idTurno.isEmpty || touchpointId.isEmpty) return null;
    return {
      'type': type,
      'id_turno': idTurno,
      'phase': data['phase']?.toString() ?? kEncounterJourneyPhasePostConsulta,
      'touchpoint_id': touchpointId,
      if (data['encounter_id'] != null)
        'encounter_id': data['encounter_id'].toString(),
    };
  }
  return null;
}

/// Combina datos del listado con respuesta de `/encounter-journey/estado`.
Map<String, dynamic> turnoConJourneyDesdeEstado(
  Map<String, dynamic> base,
  Map<String, dynamic> estado,
) {
  final merged = Map<String, dynamic>.from(base);
  if (estado['journey'] is Map) {
    merged['journey'] = estado['journey'];
  }
  if (estado['encounter_id'] != null) {
    merged['encounter_id'] = estado['encounter_id'];
    merged['id_consulta'] = estado['encounter_id'];
  }
  if (estado['motivos_input_abierto'] is bool) {
    merged['motivos_input_abierto'] = estado['motivos_input_abierto'];
  }
  if (estado['asistencia_cohorte_disponible'] is bool) {
    merged['asistencia_cohorte_disponible'] =
        estado['asistencia_cohorte_disponible'];
  }
  return merged;
}
