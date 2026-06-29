/// Fechas legibles en castellano (notificaciones, listados).
library;

/// Parsea ISO o MySQL `YYYY-MM-DD HH:MM:SS` como hora local.
DateTime? parseBioDateTime(dynamic value) {
  if (value == null) return null;
  final s = value.toString().trim();
  if (s.isEmpty) return null;

  final withTime = RegExp(
    r'^(\d{4})-(\d{2})-(\d{2})[\sT](\d{2}):(\d{2})(?::(\d{2}))?$',
  ).firstMatch(s);
  if (withTime != null) {
    return DateTime(
      int.parse(withTime.group(1)!),
      int.parse(withTime.group(2)!),
      int.parse(withTime.group(3)!),
      int.parse(withTime.group(4)!),
      int.parse(withTime.group(5)!),
      int.parse(withTime.group(6) ?? '0'),
    );
  }

  final onlyDate = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch(s);
  if (onlyDate != null) {
    return DateTime(
      int.parse(onlyDate.group(1)!),
      int.parse(onlyDate.group(2)!),
      int.parse(onlyDate.group(3)!),
    );
  }

  return DateTime.tryParse(s);
}

String _pad2(int n) => n.toString().padLeft(2, '0');

String _horaCorta(DateTime d) => '${_pad2(d.hour)}:${_pad2(d.minute)}';

const _weekdays = [
  'domingo',
  'lunes',
  'martes',
  'miércoles',
  'jueves',
  'viernes',
  'sábado',
];

/// Etiqueta relativa para bandeja de notificaciones.
String formatNotificacionFecha(dynamic value) {
  final d = parseBioDateTime(value);
  if (d == null) return value?.toString() ?? '';

  final now = DateTime.now();
  final diff = now.difference(d);
  if (diff.isNegative) {
    return _formatDateTimeAmigable(d);
  }

  final minutes = diff.inMinutes;
  if (minutes < 1) return 'Ahora';
  if (minutes < 60) {
    return 'Hace $minutes ${minutes == 1 ? 'minuto' : 'minutos'}';
  }

  final hora = _horaCorta(d);
  final today = DateTime(now.year, now.month, now.day);
  final thatDay = DateTime(d.year, d.month, d.day);
  final diffDays = thatDay.difference(today).inDays;

  if (diffDays == 0) return 'Hoy, $hora';
  if (diffDays == -1) return 'Ayer, $hora';
  if (diffDays >= -6 && diffDays < 0) {
    return '${_weekdays[d.weekday % 7]}, $hora';
  }

  return _formatDateTimeAmigable(d);
}

String _formatDateTimeAmigable(DateTime d) {
  final hora = _horaCorta(d);
  var fecha = '${_pad2(d.day)}/${_pad2(d.month)}';
  if (d.year != DateTime.now().year) {
    fecha += '/${d.year}';
  }
  return '$fecha, $hora';
}
