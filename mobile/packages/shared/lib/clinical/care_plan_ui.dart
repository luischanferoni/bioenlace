import '../theme/tokens/tokens.dart';

/// Utilidades de presentación para care plans en clientes móvil.
class CarePlanUi {
  CarePlanUi._();

  static int? idFromMap(Map<String, dynamic> plan) {
    final raw = plan['id'];
    if (raw is int) return raw > 0 ? raw : null;
    return int.tryParse(raw?.toString() ?? '');
  }

  static String categoryLabel(Map<String, dynamic> plan) {
    return plan['categoryLabel']?.toString().trim().isNotEmpty == true
        ? plan['categoryLabel'].toString()
        : 'Tratamiento';
  }

  static String statusLabel(Map<String, dynamic> plan) {
    final label = plan['statusLabel']?.toString();
    if (label != null && label.isNotEmpty) return label;
    return _statusFallback(plan['status']?.toString());
  }

  static UiIntent intentForStatus(String? status) {
    switch (status) {
      case 'on-hold':
        return UiIntent.warning;
      case 'completed':
      case 'revoked':
        return UiIntent.neutral;
      default:
        return UiIntent.info;
    }
  }

  static List<String> activitySummaries(Map<String, dynamic> plan, {int? max}) {
    final raw = plan['activitySummaries'];
    if (raw is! List) return [];
    final lines = raw
        .map((e) => e.toString().trim())
        .where((s) => s.isNotEmpty)
        .toList();
    if (max != null && lines.length > max) {
      return lines.take(max).toList();
    }
    return lines;
  }

  static String? subtitleForList(Map<String, dynamic> plan) {
    final lines = activitySummaries(plan, max: 2);
    if (lines.isEmpty) return null;
    return lines.join(' · ');
  }

  static String _statusFallback(String? status) {
    switch (status) {
      case 'active':
        return 'Activo';
      case 'on-hold':
        return 'En pausa';
      case 'completed':
        return 'Completado';
      case 'revoked':
        return 'Revocado';
      case 'draft':
        return 'Borrador';
      default:
        return status?.isNotEmpty == true ? status! : 'Activo';
    }
  }
}
