import '../theme/tokens/tokens.dart';

/// Utilidades de presentación para condiciones clínicas en clientes móvil.
class ConditionUi {
  ConditionUi._();

  static int? idFromMap(Map<String, dynamic> condition) {
    final raw = condition['id'];
    if (raw is int) return raw > 0 ? raw : null;
    return int.tryParse(raw?.toString() ?? '');
  }

  static String label(Map<String, dynamic> condition) {
    final label = condition['label']?.toString().trim();
    if (label != null && label.isNotEmpty) return label;
    final display = condition['display']?.toString().trim();
    if (display != null && display.isNotEmpty) return display;
    final codigo = condition['codigo']?.toString().trim();
    if (codigo != null && codigo.isNotEmpty) return codigo;
    return 'Condición';
  }

  static String statusLabel(Map<String, dynamic> condition) {
    final label = condition['statusLabel']?.toString().trim();
    if (label != null && label.isNotEmpty) return label;
    return _statusFallback(condition['clinical_status']?.toString());
  }

  static UiIntent intentForStatus(String? status) {
    switch ((status ?? '').toUpperCase()) {
      case 'RECURRENCE':
      case 'RELAPSE':
        return UiIntent.warning;
      case 'INACTIVE':
      case 'REMISSION':
      case 'RESOLVED':
        return UiIntent.neutral;
      default:
        return UiIntent.info;
    }
  }

  static String? codigo(Map<String, dynamic> condition) {
    final c = condition['codigo']?.toString().trim();
    return (c != null && c.isNotEmpty) ? c : null;
  }

  static String? subtitleForList(Map<String, dynamic> condition) {
    final protocol = condition['protocol_title']?.toString().trim();
    if (protocol != null && protocol.isNotEmpty) return protocol;
    final codigo = ConditionUi.codigo(condition);
    if (codigo != null) return codigo;
    return null;
  }

  static String controlHubAnchor(Map<String, dynamic> condition) {
    final anchor = condition['control_hub_anchor']?.toString().trim();
    if (anchor != null && anchor.isNotEmpty) return anchor;
    final codigo = ConditionUi.codigo(condition);
    if (codigo != null) return 'diag:$codigo';
    final id = idFromMap(condition);
    if (id != null) return 'diag:$id';
    return '';
  }

  static String _statusFallback(String? status) {
    switch ((status ?? '').toUpperCase()) {
      case 'ACTIVE':
        return 'Activa';
      case 'RECURRENCE':
        return 'Recurrencia';
      case 'RELAPSE':
        return 'Recaída';
      case 'RESOLVED':
        return 'Resuelta';
      default:
        return status?.isNotEmpty == true ? status! : 'Activa';
    }
  }
}
