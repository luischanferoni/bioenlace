import 'package:flutter_test/flutter_test.dart';
import 'package:personalsalud/services/emergency_guardia_api.dart';

void main() {
  group('EmergencyTriageLevel.fromJson', () {
    test(
      'parses the triage level label, color and intent from API metadata',
      () {
        final level = EmergencyTriageLevel.fromJson({
          'level': 2,
          'label': 'Naranja — muy urgente',
          'color': '#e67e22',
          'intent': 'danger',
          'is_default': false,
        });

        expect(level.level, 2);
        expect(level.label, 'Naranja — muy urgente');
        expect(level.color, '#e67e22');
        expect(level.intent, 'danger');
        expect(level.isDefault, isFalse);
      },
    );

    test('parses the default triage level from API metadata', () {
      final level = EmergencyTriageLevel.fromJson({
        'level': 3,
        'label': 'Amarillo — urgente',
        'color': '#f1c40f',
        'intent': 'warning',
        'is_default': true,
      });

      expect(level.isDefault, isTrue);
    });

    test('rejects invalid level metadata', () {
      expect(
        () => EmergencyTriageLevel.fromJson({
          'level': 0,
          'label': 'Inválido',
          'color': '#ffffff',
          'intent': 'neutral',
        }),
        throwsFormatException,
      );
    });
  });

  group('EmergencyBoardItem.canViewEncounter', () {
    test('requires an encounter and a completed/derived circuit', () {
      final completed = EmergencyBoardItem(
        id: 1,
        idPersona: 10,
        nombreCompleto: 'Paciente',
        encounterId: 42,
        circuitoEstado: 'atendido',
      );
      final derived = EmergencyBoardItem(
        id: 2,
        idPersona: 11,
        nombreCompleto: 'Paciente',
        encounterId: 43,
        circuitoEstado: 'derivado',
      );
      final withoutEncounter = EmergencyBoardItem(
        id: 3,
        idPersona: 12,
        nombreCompleto: 'Paciente',
        circuitoEstado: 'atendido',
      );
      final inProgress = EmergencyBoardItem(
        id: 4,
        idPersona: 13,
        nombreCompleto: 'Paciente',
        encounterId: 44,
        circuitoEstado: 'en_atencion',
      );

      expect(completed.canViewEncounter, isTrue);
      expect(derived.canViewEncounter, isTrue);
      expect(withoutEncounter.canViewEncounter, isFalse);
      expect(inProgress.canViewEncounter, isFalse);
    });
  });
}
