/// Calcula horarios de recordatorio a partir de la primera toma y la frecuencia prescrita.
class CarePlanReminderTimeCalculator {
  /// Expande la hora de la primera toma en [dosesPerDay] recordatorios del día.
  static List<String> expandFromFirstDose({
    required String firstTime,
    required int dosesPerDay,
    required int intervalHours,
  }) {
    final anchor = _parseTime(firstTime);
    if (anchor == null) {
      return [];
    }

    final doses = dosesPerDay.clamp(1, 12);
    if (doses <= 1) {
      return [_formatTime(anchor)];
    }

    final intervalMinutes = (intervalHours.clamp(1, 24) * 60);
    final times = <int>{};
    for (var i = 0; i < doses; i++) {
      times.add((anchor + i * intervalMinutes) % (24 * 60));
    }

    final sorted = times.toList()..sort((a, b) => a.compareTo(b));
    return sorted.map(_formatTime).toList();
  }

  static String frequencyHint({
    required int dosesPerDay,
    required int intervalHours,
  }) {
    if (dosesPerDay <= 1) {
      return 'Una toma por día';
    }
    if (24 % dosesPerDay == 0 && intervalHours == 24 ~/ dosesPerDay) {
      return '$dosesPerDay tomas por día (cada ${24 ~/ dosesPerDay} h)';
    }
    return '$dosesPerDay tomas por día (cada $intervalHours h)';
  }

  static int? _parseTime(String value) {
    final parts = value.trim().split(':');
    if (parts.length < 2) {
      return null;
    }
    final h = int.tryParse(parts[0]);
    final m = int.tryParse(parts[1]);
    if (h == null || m == null || h < 0 || h > 23 || m < 0 || m > 59) {
      return null;
    }
    return h * 60 + m;
  }

  static String _formatTime(int minutes) {
    final normalized = minutes % (24 * 60);
    final h = normalized ~/ 60;
    final m = normalized % 60;
    return '${h.toString().padLeft(2, '0')}:${m.toString().padLeft(2, '0')}';
  }
}
