import '../../../models/pending_encounter_capture.dart';

/// Tope de notas que se analizan juntas (el servidor aplica el mismo límite).
const int maxDraftNotes = 10;

/// Notas del borrador en orden cronológico, sin la que se está componiendo.
List<PendingEncounterCapture> draftNotes(
  List<PendingEncounterCapture> all, {
  String? composingId,
}) {
  final notes = all.where((e) => e.id != composingId).toList()
    ..sort((a, b) => a.createdAt.compareTo(b.createdAt));
  return notes;
}

/// Una nota está lista cuando ya tiene texto (escrito, dictado o transcripto).
bool isNoteReady(PendingEncounterCapture item) {
  final text = item.texto.trim();
  if (text.isEmpty || text.startsWith('(audio')) return false;
  return switch (item.status) {
    PendingEncounterCaptureStatus.draft ||
    PendingEncounterCaptureStatus.pendingUpload ||
    PendingEncounterCaptureStatus.pendingStt => false,
    _ => true,
  };
}

bool canAnalyzeNotes(List<PendingEncounterCapture> notes) =>
    notes.isNotEmpty &&
    notes.length <= maxDraftNotes &&
    notes.every(isNoteReady);

/// La nota principal lleva el análisis en el servidor: siempre la más antigua.
PendingEncounterCapture? primaryNote(List<PendingEncounterCapture> notes) =>
    notes.isEmpty ? null : notes.first;

List<String> batchClientIds(List<PendingEncounterCapture> notes) =>
    notes.map((e) => e.id).toList();
