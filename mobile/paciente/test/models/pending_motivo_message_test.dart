import 'package:bioenlace_paciente/models/pending_motivo_message.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'persists queued encounter identifier with its canonical key',
    () {
      final message = PendingMotivoMessage.fromJson(<String, dynamic>{
        'id': 'queued-1',
        'encounter_id': 42,
        'type': 'texto',
        'status': 'pendingUpload',
        'created_at': '2026-10-21T10:00:00.000',
        'updated_at': '2026-10-21T10:01:00.000',
        'texto': 'Necesito consultar',
      });

      final persisted = message.toJson();

      expect(message.encounterId, 42);
      expect(persisted['encounter_id'], 42);
      expect(persisted.containsKey('encounter_id'), isTrue);
      expect(persisted['texto'], 'Necesito consultar');
    },
  );
}
