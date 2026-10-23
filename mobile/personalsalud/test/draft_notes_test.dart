import 'package:flutter_test/flutter_test.dart';
import 'package:personalsalud/models/pending_encounter_capture.dart';
import 'package:personalsalud/screens/timeline/capture/draft_notes.dart';

PendingEncounterCapture note(
  String id, {
  String texto = 'dolor',
  PendingEncounterCaptureStatus status =
      PendingEncounterCaptureStatus.pendingAnalyze,
  int minute = 0,
}) {
  final at = DateTime(2026, 1, 1, 10, minute);
  return PendingEncounterCapture(
    id: id,
    personaId: 1,
    parent: 'GUARDIA',
    parentId: 2,
    texto: texto,
    status: status,
    createdAt: at,
    updatedAt: at,
  );
}

void main() {
  test('ordena cronológicamente y excluye la nota en composición', () {
    final notes = draftNotes([
      note('b', minute: 5),
      note('a', minute: 1),
      note('c', minute: 9),
    ], composingId: 'c');
    expect(batchClientIds(notes), ['a', 'b']);
    expect(primaryNote(notes)!.id, 'a');
  });

  test('una nota sin texto o sin transcribir no está lista', () {
    expect(isNoteReady(note('a')), isTrue);
    expect(isNoteReady(note('a', texto: '(audio pendiente de transcribir)')), isFalse);
    expect(isNoteReady(note('a', texto: '  ')), isFalse);
    expect(
      isNoteReady(note('a', status: PendingEncounterCaptureStatus.pendingStt)),
      isFalse,
    );
  });

  test('solo se puede analizar con notas listas y dentro del tope', () {
    expect(canAnalyzeNotes([]), isFalse);
    expect(canAnalyzeNotes([note('a'), note('b')]), isTrue);
    expect(
      canAnalyzeNotes([
        note('a'),
        note('b', status: PendingEncounterCaptureStatus.pendingUpload),
      ]),
      isFalse,
    );
    expect(
      canAnalyzeNotes([for (var i = 0; i <= maxDraftNotes; i++) note('n$i')]),
      isFalse,
    );
  });
}
