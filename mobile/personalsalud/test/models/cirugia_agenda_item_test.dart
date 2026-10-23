import 'package:flutter_test/flutter_test.dart';
import 'package:personalsalud/models/cirugia_agenda_item.dart';

void main() {
  test('reads surgery schedule fields from generic sections', () {
    final item = CirugiaAgendaItem.fromJson(<String, dynamic>{
      'sections': [
        <String, dynamic>{
          'key': 'surgery_schedule',
          'label': 'Agenda quirúrgica',
          'order': 1,
          'fields': [
            <String, dynamic>{'key': 'id', 'value': '35', 'order': 1},
            <String, dynamic>{'key': 'id_persona', 'value': 18, 'order': 2},
            <String, dynamic>{
              'key': 'nombre_paciente',
              'value': 'Paciente genérico',
              'order': 3,
            },
            <String, dynamic>{
              'key': 'documento',
              'value': '12345678',
              'order': 4,
            },
            <String, dynamic>{
              'key': 'sala_nombre',
              'value': 'Quirófano 2',
              'order': 5,
            },
            <String, dynamic>{
              'key': 'fecha_hora_inicio',
              'value': '2026-10-23 10:00:00',
              'order': 6,
            },
            <String, dynamic>{
              'key': 'fecha_hora_fin_estimada',
              'value': '2026-10-23 11:00:00',
              'order': 7,
            },
            <String, dynamic>{
              'key': 'estado',
              'value': 'programada',
              'order': 8,
            },
            <String, dynamic>{
              'key': 'estado_label',
              'value': 'Programada',
              'order': 9,
            },
          ],
        },
      ],
    });

    expect(item.id, 35);
    expect(item.idPersona, 18);
    expect(item.nombrePaciente, 'Paciente genérico');
    expect(item.documento, '12345678');
    expect(item.salaNombre, 'Quirófano 2');
    expect(item.fechaHoraInicio, '2026-10-23 10:00:00');
    expect(item.fechaHoraFinEstimada, '2026-10-23 11:00:00');
    expect(item.estado, 'programada');
    expect(item.estadoLabel, 'Programada');
  });

  test(
    'prefers generic surgery fields and falls back to legacy properties',
    () {
      final item = CirugiaAgendaItem.fromJson(<String, dynamic>{
        'id': 30,
        'id_persona': 17,
        'paciente': <String, dynamic>{
          'nombre_completo': 'Paciente legacy',
          'documento': '87654321',
        },
        'sala_nombre': 'Sala legacy',
        'fecha_hora_inicio': 'legacy start',
        'fecha_hora_fin_estimada': 'legacy end',
        'estado': 'legacy status',
        'estado_label': 'Estado legacy',
        'sections': [
          <String, dynamic>{
            'key': 'surgery_schedule',
            'fields': [
              <String, dynamic>{
                'key': 'nombre_paciente',
                'value': 'Paciente genérico',
              },
              <String, dynamic>{'key': 'sala_nombre', 'value': null},
              <String, dynamic>{'key': 'estado_label', 'value': 'Confirmada'},
            ],
          },
        ],
      });

      expect(item.id, 30);
      expect(item.nombrePaciente, 'Paciente genérico');
      expect(item.salaNombre, 'Sala legacy');
      expect(item.fechaHoraInicio, 'legacy start');
      expect(item.estado, 'legacy status');
      expect(item.estadoLabel, 'Confirmada');
    },
  );

  test('continues to read the legacy surgery payload', () {
    final item = CirugiaAgendaItem.fromJson(<String, dynamic>{
      'id': 30,
      'id_persona': 17,
      'paciente': <String, dynamic>{
        'nombre_completo': 'Paciente legacy',
        'documento': '87654321',
      },
      'sala_nombre': 'Sala legacy',
      'fecha_hora_inicio': 'legacy start',
      'estado': 'programada',
      'estado_label': 'Programada',
    });

    expect(item.id, 30);
    expect(item.idPersona, 17);
    expect(item.nombrePaciente, 'Paciente legacy');
    expect(item.documento, '87654321');
    expect(item.salaNombre, 'Sala legacy');
    expect(item.fechaHoraInicio, 'legacy start');
    expect(item.estado, 'programada');
    expect(item.estadoLabel, 'Programada');
  });
}
