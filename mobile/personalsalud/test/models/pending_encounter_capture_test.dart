import 'package:flutter_test/flutter_test.dart';
import 'package:personalsalud/models/pending_encounter_capture.dart';

void main() {
  test('keeps server stage presentation when mapping a capture', () {
    final capture = PendingEncounterCapture.fromServerCapture({
      'client_capture_id': 'capture-1',
      'stage': 'READY_FOR_REVIEW',
      'stage_metadata': {
        'code': 'READY_FOR_REVIEW',
        'label': 'Para revisar',
        'intent': 'primary',
      },
    });

    expect(capture.serverStage, 'READY_FOR_REVIEW');
    expect(capture.serverStageLabel, 'Para revisar');
    expect(capture.serverStageIntent, 'primary');
  });

  test('persists server stage presentation in local drafts', () {
    final createdAt = DateTime.utc(2026, 10, 22);
    final capture = PendingEncounterCapture(
      id: 'capture-1',
      personaId: 12,
      parent: 'GUARDIA',
      parentId: 34,
      texto: 'Audio pendiente',
      status: PendingEncounterCaptureStatus.pendingStt,
      createdAt: createdAt,
      updatedAt: createdAt,
      serverStage: 'UPLOADED',
      serverStageLabel: 'Audio recibido',
      serverStageIntent: 'info',
    );

    final restored = PendingEncounterCapture.fromJson(capture.toJson());

    expect(restored.serverStage, 'UPLOADED');
    expect(restored.serverStageLabel, 'Audio recibido');
    expect(restored.serverStageIntent, 'info');
  });

  test('ignores metadata that does not match its server stage', () {
    final capture = PendingEncounterCapture.fromServerCapture({
      'client_capture_id': 'capture-1',
      'stage': 'UPLOADED',
      'stage_metadata': {
        'code': 'STT_FAILED',
        'label': 'Error de transcripción',
        'intent': 'danger',
      },
    });

    expect(capture.serverStageLabel, isNull);
    expect(capture.serverStageIntent, isNull);
  });
}
