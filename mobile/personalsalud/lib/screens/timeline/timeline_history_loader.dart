import '../../services/historia_clinica_service.dart';

/// Pide al backend la historia clínica que corresponde al contexto de la pantalla:
/// el resumen de una consulta ya cargada o la historia del paciente con su
/// contexto de captura (turno, encounter o episodio).
Future<HistoriaClinicaResponse> fetchTimelineHistory(
  HistoriaClinicaService service, {
  required int personaId,
  required bool encounterSummaryLoaded,
  String? parentType,
  int? parentId,
}) {
  final isEncounter = parentType == EncounterParentType.encounter;
  final int? turnoId = parentType == EncounterParentType.turno ? parentId : null;
  final int? encounterId = isEncounter ? parentId : null;

  if (encounterSummaryLoaded) {
    return service.getEncounterAsStaff(
      turnoId: turnoId,
      encounterId: encounterId,
    );
  }

  final hasEpisodeParent = turnoId == null && parentType != null && !isEncounter;
  return service.getHistoriaClinica(
    personaId,
    turnoId: turnoId,
    encounterId: encounterId,
    parent: hasEpisodeParent ? parentType : null,
    parentId: hasEpisodeParent ? parentId : null,
  );
}
