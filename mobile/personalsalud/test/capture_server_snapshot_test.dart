import 'package:flutter_test/flutter_test.dart';
import 'package:personalsalud/screens/timeline/capture/capture_server_snapshot.dart';

void main() {
  test('devuelve null sin objeto capture', () {
    expect(CaptureServerSnapshot.fromResponse({'ok': true}), isNull);
  });

  test('lee etapa, metadata, id, audio y transcripción', () {
    final snap = CaptureServerSnapshot.fromResponse({
      'capture': {
        'id': '7',
        'stage': 'UPLOADED',
        'stage_metadata': {
          'code': 'UPLOADED',
          'label': 'Subido',
          'intent': 'info',
        },
        'has_audio': true,
        'transcript': 'texto',
      },
    })!;
    expect(snap.stage, 'UPLOADED');
    expect(snap.stageLabel, 'Subido');
    expect(snap.stageIntent, 'info');
    expect(snap.id, 7);
    expect(snap.hasAudio, isTrue);
    expect(snap.transcript, 'texto');
  });

  test('ignora metadata que no corresponde a la etapa', () {
    final snap = CaptureServerSnapshot.fromResponse({
      'capture': {
        'id': 3,
        'stage': 'STT_FAILED',
        'stage_metadata': {'code': 'OTHER', 'label': 'x', 'intent': 'y'},
      },
    })!;
    expect(snap.stageLabel, isNull);
    expect(snap.stageIntent, isNull);
    expect(snap.id, 3);
    expect(snap.transcript, '');
  });
}
