import '../../theme/tokens/ui_intent.dart';

UiIntent uiIntentFromContract(dynamic value) {
  return switch ((value ?? '').toString()) {
    'primary' => UiIntent.primary,
    'secondary' => UiIntent.secondary,
    'success' => UiIntent.success,
    'danger' => UiIntent.danger,
    'warning' => UiIntent.warning,
    'info' => UiIntent.info,
    'dark' => UiIntent.dark,
    _ => UiIntent.neutral,
  };
}

int _asInt(dynamic v) => v is int ? v : int.tryParse(v?.toString() ?? '') ?? 0;

/// Campo `{key,label,value,unit,format,intent,order}` del contrato de secciones.
class ClinicalField {
  const ClinicalField({
    required this.key,
    required this.label,
    required this.value,
    this.unit = '',
    this.format = 'text',
    this.intent = UiIntent.neutral,
    this.order = 0,
  });

  final String key;
  final String label;
  final String value;
  final String unit;
  final String format;
  final UiIntent intent;
  final int order;

  factory ClinicalField.fromJson(Map<String, dynamic> json) {
    var value = json['value']?.toString().trim() ?? '';
    final format = json['format']?.toString() ?? 'text';
    final numeric = double.tryParse(value);
    if (format == 'integer' && numeric != null && numeric == numeric.truncateToDouble()) {
      value = numeric.toInt().toString();
    }
    return ClinicalField(
      key: json['key']?.toString() ?? '',
      label: json['label']?.toString() ?? '',
      value: value,
      unit: json['unit']?.toString().trim() ?? '',
      format: format,
      intent: uiIntentFromContract(json['intent']),
      order: _asInt(json['order']),
    );
  }
}

/// Sección de una historia clínica: título más campos o ítems.
class ClinicalSection {
  const ClinicalSection({
    required this.key,
    required this.title,
    this.order = 0,
    this.intent = UiIntent.neutral,
    this.items = const [],
    this.fields = const [],
  });

  final String key;
  final String title;
  final int order;
  final UiIntent intent;
  final List<String> items;
  final List<ClinicalField> fields;

  bool get isEmpty => items.isEmpty && fields.isEmpty;

  factory ClinicalSection.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'];
    final rawFields = json['fields'];
    return ClinicalSection(
      key: json['key']?.toString() ?? '',
      title: (json['title'] ?? json['label'] ?? json['titulo'] ?? '').toString(),
      order: _asInt(json['order']),
      intent: uiIntentFromContract(json['intent']),
      items: rawItems is List
          ? rawItems.map((e) => e.toString().trim()).where((e) => e.isNotEmpty).toList()
          : const [],
      fields: rawFields is List
          ? (rawFields
                .whereType<Map>()
                .map((f) => ClinicalField.fromJson(Map<String, dynamic>.from(f)))
                .where((f) => f.value.isNotEmpty)
                .toList()
              ..sort((a, b) => a.order.compareTo(b.order)))
          : const [],
    );
  }

  /// Acepta `sections` (canónico) o `secciones` (alias temporal); ordena por `order`.
  static List<ClinicalSection> listFromJson(Map<String, dynamic>? json) {
    final raw = json == null ? null : (json['sections'] is List ? json['sections'] : json['secciones']);
    if (raw is! List) return const [];
    final sections = raw
        .whereType<Map>()
        .map((s) => ClinicalSection.fromJson(Map<String, dynamic>.from(s)))
        .toList();
    sections.sort((a, b) => a.order.compareTo(b.order));
    return sections;
  }
}
