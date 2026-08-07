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

/// Fecha de episodio (guardia / internación): `dd/MM/yyyy` o `dd/MM/yyyy HH:mm`.
/// Acepta ISO/MySQL o un valor ya formateado.
String formatEpisodioFecha(dynamic value) {
  if (value == null) return '';
  final s = value.toString().trim();
  if (s.isEmpty) return '';
  if (RegExp(r'^\d{2}/\d{2}/\d{4}( \d{2}:\d{2})?$').hasMatch(s)) {
    return s;
  }
  final d = parseBioDateTime(s);
  if (d == null) return s;
  final hasTime = RegExp(r'\d{1,2}:\d{2}').hasMatch(s);
  final fecha = '${_pad2(d.day)}/${_pad2(d.month)}/${d.year}';
  if (!hasTime && d.hour == 0 && d.minute == 0 && d.second == 0) {
    return fecha;
  }
  return '$fecha ${_horaCorta(d)}';
}

/// Duración a partir de minutos enteros: `45 min`, `1 h 30 min`, `2 d 3 h`.
String formatDuracionMinutos(num? minutos) {
  final total = minutos == null ? 0 : minutos.round();
  final n = total < 0 ? 0 : total;
  if (n < 60) return '$n min';

  final days = n ~/ 1440;
  final hours = (n % 1440) ~/ 60;
  final mins = n % 60;
  final parts = <String>[];
  if (days > 0) parts.add('$days d');
  if (hours > 0) parts.add('$hours h');
  if (mins > 0 && days == 0) parts.add('$mins min');
  return parts.isEmpty ? '0 min' : parts.join(' ');
}
