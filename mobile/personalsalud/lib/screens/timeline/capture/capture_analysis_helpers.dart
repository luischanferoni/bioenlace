import '../../../models/encounter_capture_analysis.dart';
import 'capture_server_snapshot.dart';

const _topLevelAnalysisKeys = [
  'capture_review',
  'texto_original',
  'texto_procesado',
  'datosExtraidos',
  'analysis_cache_token',
  'id_configuracion',
  'encounter_id',
  'puede_confirmar',
  'tiene_datos_faltantes',
];

/// Arma el payload de análisis a partir de la respuesta de `captura/analizar`.
/// Prefiere `capture.analysis`, completa con campos del `capture` y aplica el
/// formato legacy `datos.datosExtraidos`.
Map<String, dynamic> buildAnalysisPayload(
  Map<String, dynamic> response,
  CaptureServerSnapshot? snapshot,
) {
  if (snapshot == null) return response;
  final map = snapshot.raw;
  final payload = map['analysis'] is Map
      ? Map<String, dynamic>.from(map['analysis'] as Map)
      : response;
  for (final key in _topLevelAnalysisKeys) {
    if (!payload.containsKey(key) && map[key] != null) {
      payload[key] = map[key];
    }
  }
  final datos = payload['datos'];
  if (datos is Map &&
      payload['datosExtraidos'] == null &&
      datos['datosExtraidos'] != null) {
    payload['datosExtraidos'] = datos['datosExtraidos'];
  }
  return payload;
}

/// Ítems seleccionados por defecto: los que indica el servidor o, si no hay,
/// los provenientes del texto clínico que aún no están activos.
Set<String> defaultStagedItems(EncounterCaptureAnalysis review) {
  if (review.defaultStagedItemIds.isNotEmpty) {
    return Set<String>.from(review.defaultStagedItemIds);
  }
  return review.allItems
      .where((i) => i.isFromClinicalText && !i.alreadyActive)
      .map((e) => e.id)
      .toSet();
}

/// Id del ítem seleccionado al que pertenece un issue (`item::n:detalle` → `item::n`).
String stagedIdForIssue(String issueId) {
  final m = RegExp(r'^(.*)::(\d+):').firstMatch(issueId);
  return m != null ? '${m.group(1)}::${m.group(2)}' : issueId;
}

/// true si todos los issues de ítems seleccionados tienen resolución o texto propio.
bool allStagedIssuesResolved({
  required EncounterCaptureAnalysis review,
  required Set<String> stagedItemIds,
  required Map<String, dynamic> resolutions,
  required String Function(String issueId) customText,
}) {
  for (final issue in review.issues) {
    if (!stagedItemIds.contains(stagedIdForIssue(issue.id))) continue;
    final v = resolutions[issue.id];
    if (v != null && v.toString().trim().isNotEmpty) continue;
    if (issue.allowCustom && customText(issue.id).trim().isNotEmpty) continue;
    return false;
  }
  return true;
}
