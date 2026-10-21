import 'package:flutter_test/flutter_test.dart';
import 'package:shared/clinical/encounter_chat_policy.dart';

void main() {
  group('EncounterChatPolicy.fromApi', () {
    test('uses safe defaults when there is no policy', () {
      final policy = EncounterChatPolicy.fromApi(null);

      expect(policy.composerEnabled, isTrue);
      expect(policy.uploadEnabled, isFalse);
      expect(policy.canCancel, isFalse);
      expect(policy.canClose, isFalse);
      expect(policy.resolutions, isEmpty);
    });

    test('decodes encounter chat capabilities and resolutions', () {
      final policy = EncounterChatPolicy.fromApi({
        'composer': {
          'enabled': true,
          'upload_enabled': true,
          'upload_types': ['audio', 'documento'],
          'hint': 'Enviar mensaje',
        },
        'acciones': {'cancelar': true, 'cerrar': true},
        'resoluciones_disponibles': {
          'resuelto': {'label': 'Resuelto', 'require_note': true},
        },
        'suggest_turno': true,
        'conversation_mode': 'structured',
      });

      expect(policy.composerEnabled, isTrue);
      expect(policy.canUploadAudio, isTrue);
      expect(policy.canUploadDocument, isTrue);
      expect(policy.canCancel, isTrue);
      expect(policy.canClose, isTrue);
      expect(policy.suggestTurno, isTrue);
      expect(policy.showMessageThread, isFalse);
      expect(policy.resolutions, hasLength(1));
      expect(policy.resolutions.single.code, 'resuelto');
      expect(policy.resolutions.single.requireNote, isTrue);
    });
  });
}
