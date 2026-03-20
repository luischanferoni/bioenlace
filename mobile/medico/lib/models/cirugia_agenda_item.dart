// lib/models/cirugia_agenda_item.dart
import 'package:flutter/foundation.dart';

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
    final paciente = json['paciente'] as Map<String, dynamic>?;
    return CirugiaAgendaItem(
      id: json['id'] as int,
      idPersona: json['id_persona'] as int,
      nombrePaciente: paciente != null
          ? (paciente['nombre_completo'] as String? ?? 'Sin paciente')
          : 'Sin paciente',
      documento: paciente?['documento'] as String?,
      salaNombre: json['sala_nombre'] as String? ?? '',
      fechaHoraInicio: json['fecha_hora_inicio'] as String?,
      fechaHoraFinEstimada: json['fecha_hora_fin_estimada'] as String?,
      estado: json['estado'] as String? ?? '',
      estadoLabel: json['estado_label'] as String? ?? '',
    );
  }
}
