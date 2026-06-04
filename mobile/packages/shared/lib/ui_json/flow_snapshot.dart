/// Contexto legible capturado durante flows del asistente (turnos, etc.).
/// No se envía al backend: solo alimenta el mensaje de éxito al colapsar el flow.

/// Etiquetas PAC_* (alineadas con {@see TurnoCancelacionRazones} en PHP).
const Map<String, String> kTurnoCancelacionPacienteLabels = {
  'PAC_ENFERMEDAD': 'Enfermedad o síntomas: no puedo asistir',
  'PAC_OTRO_COMPROMISO': 'Otro compromiso u obligación',
  'PAC_YA_MEJORE': 'Ya mejoré / ya no necesito esta consulta',
  'PAC_RESERVA_ERRONEA': 'Reservé el turno por error',
  'PAC_OTRO_TURNO_EN_OTRO_LUGAR': 'Conseguí otro turno (misma u otra institución)',
  'PAC_TRANSPORTE': 'Dificultades de transporte o distancia',
  'PAC_LABORAL_ACADEMICO': 'Motivos laborales o de estudio',
  'PAC_OTRO': 'Otro motivo',
};

String etiquetaRazonCancelacionPaciente(String code) {
  return kTurnoCancelacionPacienteLabels[code] ?? code;
}

/// Fecha Y-m-d → encabezado amigable (Hoy, Mañana, lunes 15/5, …).
String friendlyDateEs(String fechaYmd) {
  final raw = fechaYmd.trim();
  if (raw.length < 10) return raw;
  final d = DateTime.tryParse(raw.substring(0, 10));
  if (d == null) return raw;
  final today = DateTime(DateTime.now().year, DateTime.now().month, DateTime.now().day);
  final slot = DateTime(d.year, d.month, d.day);
  final diff = slot.difference(today).inDays;
  if (diff == 0) return 'Hoy';
  if (diff == 1) return 'Mañana';
  if (diff == 2) return 'Pasado mañana';
  const weekdays = [
    'domingo',
    'lunes',
    'martes',
    'miércoles',
    'jueves',
    'viernes',
    'sábado',
  ];
  final nombre = weekdays[slot.weekday % 7];
  return '$nombre ${slot.day}/${slot.month}';
}

String formatHoraCorta(String? hora) {
  final h = hora?.trim() ?? '';
  if (h.isEmpty) return '';
  return h.length > 5 ? h.substring(0, 5) : h;
}

String formatCuandoDesdeFechaHora(String? fecha, String? hora) {
  final f = fecha?.trim() ?? '';
  final h = formatHoraCorta(hora);
  if (f.isEmpty && h.isEmpty) return '';
  if (f.isEmpty) return h;
  final dia = friendlyDateEs(f);
  return h.isNotEmpty ? '$dia · $h' : dia;
}

void applyFlowPickToSnapshot(
  Map<String, dynamic> snap,
  String draftField,
  Map<String, dynamic> item,
) {
  final label = (item['name'] ?? item['label'] ?? item['id'])?.toString().trim() ?? '';
  switch (draftField) {
    case 'id':
      snap['turno'] = {
        'id': item['id']?.toString(),
        if (label.isNotEmpty) 'label': label,
      };
      break;
    case 'slot_id':
      final meta = item['meta'] is Map ? Map<String, dynamic>.from(item['meta'] as Map) : <String, dynamic>{};
      final fecha = meta['fecha']?.toString() ?? '';
      final hora = meta['hora']?.toString() ?? '';
      final cuando = formatCuandoDesdeFechaHora(
        fecha.isNotEmpty ? fecha : null,
        hora.isNotEmpty ? hora : null,
      );
      snap['slot'] = {
        'id': item['id']?.toString(),
        if (label.isNotEmpty) 'label': label,
        if (fecha.isNotEmpty) 'fecha': fecha,
        if (hora.isNotEmpty) 'hora': hora,
        if (cuando.isNotEmpty) 'cuando': cuando,
        if (meta['servicio'] is Map) 'servicio': meta['servicio'],
      };
      break;
    case 'razon_cancelacion':
      final code = (item['code'] ?? item['value'] ?? item['id'])?.toString().trim() ?? '';
      final motivoLabel = (item['label'] ?? item['name'])?.toString().trim() ??
          (code.isNotEmpty ? etiquetaRazonCancelacionPaciente(code) : '');
      snap['motivo'] = {
        if (code.isNotEmpty) 'code': code,
        if (motivoLabel.isNotEmpty) 'label': motivoLabel,
      };
      break;
    case 'eleccion':
      snap['eleccion'] = {
        'value': (item['value'] ?? item['id'])?.toString(),
        if (label.isNotEmpty) 'label': label,
      };
      break;
    case 'id_servicio_asignado':
      snap['servicio'] = {
        'id': item['id']?.toString(),
        if (label.isNotEmpty) 'label': label,
      };
      break;
    case 'id_efector':
      snap['efector'] = {
        'id': item['id']?.toString(),
        if (label.isNotEmpty) 'label': label,
      };
      break;
    case 'id_profesional_efector_servicio':
      snap['profesional'] = {
        'id': item['id']?.toString(),
        if (label.isNotEmpty) 'label': label,
      };
      break;
    default:
      if (label.isNotEmpty) {
        snap[draftField] = {'id': item['id']?.toString(), 'label': label};
      }
  }
}

/// Fusiona `draft_delta` en [draft] y actualiza [flowSnapshot] con picks `_flow_item_*`.
({Map<String, dynamic> draft, Map<String, dynamic> flowSnapshot}) applyDraftDelta({
  required Map<String, dynamic> draft,
  required Map<String, dynamic> flowSnapshot,
  required Map<String, dynamic> delta,
}) {
  final nextDraft = Map<String, dynamic>.from(draft);
  final snap = Map<String, dynamic>.from(flowSnapshot);

  for (final e in delta.entries) {
    final k = e.key;
    if (k.startsWith('_flow_item_')) {
      final field = k.substring('_flow_item_'.length);
      if (e.value is Map) {
        applyFlowPickToSnapshot(
          snap,
          field,
          Map<String, dynamic>.from(e.value as Map),
        );
      }
    } else if (k != 'flow_snapshot') {
      nextDraft[k] = e.value;
    }
  }

  return (draft: nextDraft, flowSnapshot: snap);
}

String formatFlowSubmitSummary({
  String? intentId,
  Map<String, dynamic>? submitData,
  Map<String, dynamic>? flowSnapshot,
}) {
  final snap = flowSnapshot ?? {};
  final data = submitData ?? {};
  final iid = intentId?.trim() ?? '';

  switch (iid) {
    case 'turnos.cancelar-como-paciente-flow':
      return _summaryCancelar(snap, data);
    case 'turnos.modificar-como-paciente-flow':
      return _summaryModificar(snap, data);
    case 'turnos.conflicto-agenda-flow':
      return _summaryConflictoAgenda(snap, data);
    case 'turnos.reubicar-como-paciente-flow':
      return _summaryReubicar(snap, data);
    case 'atencion.necesito-atencion':
    case 'turnos.crear-como-paciente':
      return _summaryCrear(snap, data);
    default:
      return _summaryGeneric(data);
  }
}

String _summaryCancelar(Map<String, dynamic> snap, Map<String, dynamic> data) {
  final lines = <String>['Cancelamos tu turno.'];
  final turno = snap['turno'];
  if (turno is Map) {
    final lbl = turno['label']?.toString().trim();
    if (lbl != null && lbl.isNotEmpty) {
      lines.add(lbl);
    }
  }
  var motivoLbl = '';
  final motivo = snap['motivo'];
  if (motivo is Map) {
    motivoLbl = motivo['label']?.toString().trim() ?? '';
  }
  if (motivoLbl.isEmpty) {
    final code = data['razon_cancelacion']?.toString().trim() ?? '';
    motivoLbl = data['razon_cancelacion_label']?.toString().trim() ??
        (code.isNotEmpty ? etiquetaRazonCancelacionPaciente(code) : '');
  }
  if (motivoLbl.isNotEmpty) {
    lines.add('Motivo: $motivoLbl');
  }
  return lines.join('\n');
}

String _summaryConflictoAgenda(Map<String, dynamic> snap, Map<String, dynamic> data) {
  final lines = <String>['Actualizamos tu turno según el cambio de agenda.'];
  final turno = snap['turno'];
  if (turno is Map) {
    final lbl = turno['label']?.toString().trim();
    if (lbl != null && lbl.isNotEmpty) {
      lines.add(lbl);
    }
  }
  final eleccion = snap['eleccion'];
  if (eleccion is Map) {
    final el = eleccion['label']?.toString().trim();
    if (el != null && el.isNotEmpty) {
      lines.add('Opción: $el');
    }
  }
  final nuevo = _nuevoHorarioLinea(snap, data);
  if (nuevo.isNotEmpty) {
    lines.add('Nuevo horario: $nuevo');
  }
  final msg = data['message']?.toString().trim() ?? '';
  if (msg.isNotEmpty && lines.length == 1) {
    lines.add(msg);
  }
  return lines.join('\n');
}

String _summaryReubicar(Map<String, dynamic> snap, Map<String, dynamic> data) {
  final lines = <String>['Reubicamos tu turno.'];
  final turno = snap['turno'];
  if (turno is Map) {
    final lbl = turno['label']?.toString().trim();
    if (lbl != null && lbl.isNotEmpty) {
      lines.add('Turno anterior: $lbl');
    }
  }
  final svc = snap['servicio'];
  if (svc is Map) {
    final s = svc['label']?.toString().trim();
    if (s != null && s.isNotEmpty) {
      lines.add('Servicio: $s');
    }
  }
  final ef = snap['efector'];
  if (ef is Map) {
    final e = ef['label']?.toString().trim();
    if (e != null && e.isNotEmpty) {
      lines.add('Centro: $e');
    }
  }
  final prof = snap['profesional'];
  if (prof is Map) {
    final p = prof['label']?.toString().trim();
    if (p != null && p.isNotEmpty) {
      lines.add('Profesional: $p');
    }
  }
  final nuevo = _nuevoHorarioLinea(snap, data);
  if (nuevo.isNotEmpty) {
    lines.add('Nuevo horario: $nuevo');
  }
  return lines.join('\n');
}

String _summaryModificar(Map<String, dynamic> snap, Map<String, dynamic> data) {
  final lines = <String>['Reprogramamos tu turno.'];
  final turno = snap['turno'];
  if (turno is Map) {
    final lbl = turno['label']?.toString().trim();
    if (lbl != null && lbl.isNotEmpty) {
      lines.add('Turno anterior: $lbl');
    }
  }
  final nuevo = _nuevoHorarioLinea(snap, data);
  if (nuevo.isNotEmpty) {
    lines.add('Nuevo horario: $nuevo');
  }
  return lines.join('\n');
}

String _nuevoHorarioLinea(Map<String, dynamic> snap, Map<String, dynamic> data) {
  final slot = snap['slot'];
  if (slot is Map) {
    final cuando = slot['cuando']?.toString().trim();
    if (cuando != null && cuando.isNotEmpty) {
      return cuando;
    }
    final lbl = slot['label']?.toString().trim();
    if (lbl != null && lbl.isNotEmpty) {
      return lbl;
    }
    final built = formatCuandoDesdeFechaHora(
      slot['fecha']?.toString(),
      slot['hora']?.toString(),
    );
    if (built.isNotEmpty) return built;
  }
  return formatCuandoDesdeFechaHora(
    data['fecha']?.toString(),
    data['hora']?.toString(),
  );
}

String _summaryCrear(Map<String, dynamic> snap, Map<String, dynamic> data) {
  final msg = data['mensaje']?.toString().trim() ?? data['message']?.toString().trim();
  if (msg != null && msg.isNotEmpty) {
    return msg;
  }
  final servicio = snap['servicio'];
  String? svcNombre;
  if (servicio is Map) {
    svcNombre = servicio['label']?.toString().trim();
  }
  final sd = data['servicio_detalle'];
  if ((svcNombre == null || svcNombre.isEmpty) && sd is Map) {
    svcNombre = sd['nombre']?.toString().trim() ?? sd['descripcion']?.toString().trim();
  }
  final cuando = _nuevoHorarioLinea(snap, data);
  if (svcNombre != null && svcNombre.isNotEmpty && cuando.isNotEmpty) {
    return 'Reservamos tu turno de $svcNombre ($cuando).';
  }
  if (cuando.isNotEmpty) {
    return 'Reservamos tu turno ($cuando).';
  }
  return _summaryGeneric(data);
}

String _summaryGeneric(Map<String, dynamic> data) {
  if (data.isEmpty) return 'Listo.';
  final lines = <String>[];
  final primary = data['mensaje']?.toString().trim() ?? data['message']?.toString().trim();
  if (primary != null && primary.isNotEmpty) {
    lines.add(primary);
  }
  final razonLabel = data['razon_cancelacion_label']?.toString().trim();
  if (razonLabel != null && razonLabel.isNotEmpty) {
    lines.add('Motivo: $razonLabel');
  }
  final cuando = formatCuandoDesdeFechaHora(
    data['fecha']?.toString(),
    data['hora']?.toString(),
  );
  if (cuando.isNotEmpty) {
    lines.add('Fecha: $cuando');
  }
  return lines.isEmpty ? 'Listo.' : lines.join('\n');
}
