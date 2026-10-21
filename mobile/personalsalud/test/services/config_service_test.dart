import 'package:flutter_test/flutter_test.dart';
import 'package:personalsalud/services/config_service.dart';

void main() {
  group('Efector.fromJson', () {
    test('parses only encounter classes allowed by that efector', () {
      final efector = Efector.fromJson({
        'id_efector': 17,
        'nombre': 'Hospital',
        'encounter_classes': [
          {
            'code': 'AMB',
            'label': 'Ambulatoria',
            'capabilities': ['appointments', 'date_navigation'],
          },
          {
            'code': 'VR',
            'label': 'Virtual',
            'capabilities': ['async_requests'],
          },
        ],
      });

      expect(
        efector.encounterClasses.map((encounterClass) => encounterClass.code),
        ['AMB', 'VR'],
      );
      expect(efector.encounterClasses.first.capabilities, [
        'appointments',
        'date_navigation',
      ]);
    });

    test('does not invent encounter classes when metadata is absent', () {
      final efector = Efector.fromJson({
        'id_efector': 17,
        'nombre': 'Hospital',
      });

      expect(efector.encounterClasses, isEmpty);
    });
  });

  group('EncounterClass.fromJson', () {
    test('parses capabilities supplied by the API', () {
      final encounterClass = EncounterClass.fromJson({
        'code': 'EMER',
        'label': 'Emergencia',
        'capabilities': ['emergency_board', 'emergency_polling'],
      });

      expect(encounterClass.code, 'EMER');
      expect(encounterClass.label, 'Emergencia');
      expect(encounterClass.capabilities, [
        'emergency_board',
        'emergency_polling',
      ]);
    });

    test('ignores malformed capability values', () {
      final encounterClass = EncounterClass.fromJson({
        'code': 'AMB',
        'label': 'Ambulatoria',
        'capabilities': ['appointments', 1, null],
      });

      expect(encounterClass.capabilities, ['appointments']);
    });
  });
}
