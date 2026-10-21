import 'package:flutter_test/flutter_test.dart';
import 'package:personalsalud/services/historia_clinica_service.dart';

void main() {
  test('reads patient encounter reasons from the current API contract', () {
    final response = HistoriaClinicaResponse.fromJson(<String, dynamic>{
      'persona': <String, dynamic>{'id': 12, 'nombre_completo': 'Paciente'},
      'informacion_medica': <String, dynamic>{},
      'patient_encounter_reasons': <String, dynamic>{
        'encounter_id': 34,
        'messages': [],
      },
    });

    expect(response.patientEncounterReasons.encounterId, 34);
  });
}
