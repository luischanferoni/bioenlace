import 'package:flutter_test/flutter_test.dart';
import 'package:personalsalud/models/turno.dart';

void main() {
  group('Turno.fromJson', () {
    test('identifica modalidad remota sin exponer terminología legacy', () {
      final turno = Turno.fromJson({
        'id': 3,
        'id_persona': 8,
        'fecha': '2025-01-01',
        'hora': '09:30:00',
        'estado': 'PENDIENTE',
        'estado_label': 'Pendiente',
        'tipo_atencion': 'teleconsulta',
      });

      expect(turno.isTelehealth, isTrue);
      expect(turno.modalidadLabel, 'Videollamada');
    });

    test('prioriza encounter_id sobre el alias legacy', () {
      final turno = Turno.fromJson({
        'id': 3,
        'id_persona': 8,
        'fecha': '2025-01-01',
        'hora': '09:30:00',
        'estado': 'PENDIENTE',
        'estado_label': 'Pendiente',
        'encounter_id': 42,
        'id_consulta': 12,
      });

      expect(turno.encounterId, 42);
    });

    test('acepta id_consulta en respuestas legacy', () {
      final turno = Turno.fromJson({
        'id': 3,
        'id_persona': 8,
        'fecha': '2025-01-01',
        'hora': '09:30:00',
        'estado': 'PENDIENTE',
        'estado_label': 'Pendiente',
        'id_consulta': '12',
      });

      expect(turno.encounterId, 12);
    });
  });
}
