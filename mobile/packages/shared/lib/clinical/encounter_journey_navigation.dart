import 'package:flutter/material.dart';

import '../format/datetime_friendly.dart';
import 'care_pack_navigation.dart';

/// Fases pre-turno mostradas en el hub «Preparar tu consulta».
const List<String> kEncounterJourneyPreTurnoPhases = [
  'motivos_consulta',
  'asistencia_pre_consulta',
];

const String kEncounterJourneyPhaseMotivos = 'motivos_consulta';
const String kEncounterJourneyPhaseAsistencia = 'asistencia_pre_consulta';
const String kEncounterJourneyPhasePostConsulta = 'post_consulta';

const Set<String> kEncounterJourneyPushTypes = {
  'JOURNEY_MOTIVOS_RECORDATORIO',
  'JOURNEY_MOTIVOS_ULTIMO_AVISO',
  'JOURNEY_PRECONSULTA_RECORDATORIO',
};

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
  final phase = phases[phaseId];
  if (phase is! Map) return null;
  return Map<String, dynamic>.from(phase);
}

/// Fases pre-turno que aplican y aún no están completadas.
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
  return prepararConsultaFasesPendientes(turno).isNotEmpty;
}

bool prepararConsultaTieneAccionDisponible(Map<String, dynamic> turno) {
  return prepararConsultaFasesPendientes(turno)
      .any((e) => e.value['enabled'] == true);
}

int? encounterIdDesdeTurno(Map<String, dynamic> turno) {
  final raw = turno['encounter_id'] ?? turno['id_consulta'];
  if (raw is int) return raw > 0 ? raw : null;
  if (raw == null) return null;
  final n = int.tryParse(raw.toString());
  return n != null && n > 0 ? n : null;
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
  required AbrirMotivosConsulta onOpenMotivos,
}) {
  final phase = journeyPhase(turno, phaseId);
  if (phase == null || phase['enabled'] != true) return;

  final surface = phase['surface']?.toString() ?? '';
  if (surface == 'chat_motivos') {
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
    final turnoId = turnoIdDesdePayloadProducto(turno);
    if (turnoId == null) return;
    abrirAsistenciaPreConsulta(
      context: context,
      turnoId: turnoId,
      authToken: authToken,
      subjectPersonaId: subjectPersonaId,
    );
    return;
  }
}

/// Payload push de recordatorios del recorrido (`id_turno`, `phase`, `encounter_id`).
Map<String, String>? journeyPushDesdeData(Map<String, dynamic> data) {
  final type = data['type']?.toString() ?? '';
  if (!kEncounterJourneyPushTypes.contains(type)) return null;
  final idTurno = data['id_turno']?.toString() ?? '';
  if (idTurno.isEmpty) return null;
  return {
    'type': type,
    'id_turno': idTurno,
    if (data['phase'] != null) 'phase': data['phase'].toString(),
    if (data['encounter_id'] != null)
      'encounter_id': data['encounter_id'].toString(),
  };
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
