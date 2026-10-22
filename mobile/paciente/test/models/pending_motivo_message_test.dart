import 'package:bioenlace_paciente/models/pending_motivo_message.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('persists queued encounter identifier with its canonical key', () {
    final message = PendingMotivoMessage.fromJson(<String, dynamic>{
      'id': 'queued-1',
      'encounter_id': 42,
      'type': 'audio',
      'status': 'pendingUpload',
      'created_at': '2026-10-21T10:00:00.000',
      'updated_at': '2026-10-21T10:01:00.000',
      'audio_file_name': 'queued-1.m4a',
      'transcript': 'Dolor de garganta desde ayer.',
      'stt_meta': {
        'provenance': 'device',
        'confidence': 0.9,
        'duration_ms': 8000,
      },
    });

    final persisted = message.toJson();

    expect(message.encounterId, 42);
    expect(message.type, PendingMotivoMessageType.audio);
    expect(message.hasAudio, isTrue);
    expect(persisted['encounter_id'], 42);
    expect(persisted.containsKey('encounter_id'), isTrue);
    expect(persisted['transcript'], 'Dolor de garganta desde ayer.');
    expect(persisted['stt_meta'], {
      'provenance': 'device',
      'confidence': 0.9,
      'duration_ms': 8000,
    });
    expect(
      PendingMotivoMessage.fromJson(persisted).sttMeta?['confidence'],
      0.9,
    );
  });
}
