import 'package:shared/shared.dart';

import '../../../models/pending_encounter_capture.dart';
import '../../../services/pending_encounter_capture_store.dart';

bool _readyForReview(PendingEncounterCapture item) =>
    item.status == PendingEncounterCaptureStatus.pendingSave ||
    item.status == PendingEncounterCaptureStatus.failedSave;

/// Une las capturas locales con las del servidor (sin red quedan solo las locales),
/// ordenadas de la más reciente a la más antigua.
Future<List<PendingEncounterCapture>> loadMergedPendingCaptures({
  required PendingEncounterCaptureStore store,
  required EncounterCaptureApi api,
  required int personaId,
  required String parentType,
  required int parentId,
}) async {
  final local = await store.listForContext(
    personaId: personaId,
    parent: parentType,
    parentId: parentId,
  );
  final byId = <String, PendingEncounterCapture>{
    for (final item in local) item.id: item,
  };
  try {
    final remote = await api.capturaListar(
      idPersona: personaId,
      parent: parentType,
      parentId: parentId,
    );
    for (final row in remote) {
      final clientId = row['client_capture_id']?.toString() ?? '';
      if (clientId.isEmpty) continue;
      var merged = PendingEncounterCapture.fromServerCapture(
        row,
        local: byId[clientId],
      );
      // listar es liviano: si hace falta revisión y no hay análisis local, pedir /ver.
      if (_readyForReview(merged) && merged.analysisResponse == null) {
        try {
          final detail = await api.capturaVer(
            clientCaptureId: merged.id,
            captureId: merged.serverCaptureId,
          );
          final capture = detail['capture'];
          if (capture is Map) {
            merged = PendingEncounterCapture.fromServerCapture(
              Map<String, dynamic>.from(capture),
              local: merged,
            );
          }
        } catch (_) {}
      }
      byId[clientId] = merged;
      await store.upsert(merged);
    }
  } catch (_) {
    // Sin red: solo locales.
  }
  return byId.values.toList()..sort((a, b) => b.updatedAt.compareTo(a.updatedAt));
}

/// Primera captura con análisis listo para revisar, o null.
PendingEncounterCapture? firstCaptureReadyForReview(
  List<PendingEncounterCapture> list,
) {
  for (final item in list) {
    if (_readyForReview(item) && item.analysisResponse != null) return item;
  }
  return null;
}
