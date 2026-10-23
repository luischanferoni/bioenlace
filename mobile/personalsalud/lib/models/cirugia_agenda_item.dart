// lib/models/cirugia_agenda_item.dart
import 'package:flutter/foundation.dart';

import '../infra/app_texts.dart';

@immutable
class CirugiaAgendaItem {
  final int id;
  final int idPersona;
  final String nombrePaciente;
  final String? documento;
  final String salaNombre;
  final String? fechaHoraInicio;
  final String? fechaHoraFinEstimada;
  final String estado;
  final String estadoLabel;

  const CirugiaAgendaItem({
    required this.id,
    required this.idPersona,
    required this.nombrePaciente,
    this.documento,
    required this.salaNombre,
    this.fechaHoraInicio,
    this.fechaHoraFinEstimada,
    required this.estado,
    required this.estadoLabel,
  });

  factory CirugiaAgendaItem.fromJson(Map<String, dynamic> json) {
    final paciente = json['paciente'] is Map
        ? Map<String, dynamic>.from(json['paciente'] as Map)
        : <String, dynamic>{};
    final genericFields = <String, dynamic>{};
    final sections = json['sections'];
    if (sections is List) {
      for (final section in sections.whereType<Map>()) {
        if (section['key'] != 'surgery_schedule' ||
            section['fields'] is! List) {
          continue;
        }
        for (final field in (section['fields'] as List).whereType<Map>()) {
          final key = field['key']?.toString() ?? '';
          if (key.isNotEmpty && field['value'] != null) {
            genericFields[key] = field['value'];
          }
        }
      }
    }

    dynamic value(String key, dynamic legacy) => genericFields[key] ?? legacy;
    int asInt(dynamic raw) {
      if (raw is int) return raw;
      return int.tryParse(raw?.toString() ?? '') ?? 0;
    }

    return CirugiaAgendaItem(
      id: asInt(value('id', json['id'])),
      idPersona: asInt(value('id_persona', json['id_persona'])),
      nombrePaciente:
          value('nombre_paciente', paciente['nombre_completo'])?.toString() ??
          AppTexts.unnamedPatient,
      documento: value('documento', paciente['documento'])?.toString(),
      salaNombre: value('sala_nombre', json['sala_nombre'])?.toString() ?? '',
      fechaHoraInicio: value(
        'fecha_hora_inicio',
        json['fecha_hora_inicio'],
      )?.toString(),
      fechaHoraFinEstimada: value(
        'fecha_hora_fin_estimada',
        json['fecha_hora_fin_estimada'],
      )?.toString(),
      estado: value('estado', json['estado'])?.toString() ?? '',
      estadoLabel:
          value('estado_label', json['estado_label'])?.toString() ?? '',
    );
  }
}
