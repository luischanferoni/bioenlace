import 'package:flutter_test/flutter_test.dart';
import 'package:personalsalud/services/emergency_guardia_api.dart';

void main() {
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
