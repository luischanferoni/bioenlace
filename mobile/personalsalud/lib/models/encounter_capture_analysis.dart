/// Resultado estructurado de POST /clinical/encounter/analizar para revisión en móvil.
class EncounterCaptureAnalysis {
  EncounterCaptureAnalysis({
    required this.textoOriginal,
    this.textoProcesado,
    required this.tieneDatosFaltantes,
    required this.categories,
    this.systemError,
    this.defaultStagedItemIds = const [],
    this.puedeConfirmar = true,
    this.datosFaltantesMensaje,
    this.incompleteItems = const [],
    this.missingCategories = const [],
    this.issues = const [],
    this.openProblems,
    this.advisories = const [],
    this.episodeNoteDuplicate = false,
  });

  final String textoOriginal;
  final String? textoProcesado;
  final bool tieneDatosFaltantes;
  final List<EncounterCaptureCategory> categories;
  final String? systemError;
  final List<String> defaultStagedItemIds;
  final bool puedeConfirmar;
  /// Mensaje del backend con categorías/campos faltantes.
  final String? datosFaltantesMensaje;
  final List<EncounterIncompleteItem> incompleteItems;
  final List<String> missingCategories;
  /// Issues resolubles (opciones sin seleccionar por defecto).
  final List<EncounterCaptureIssue> issues;
  /// Problemas/planes abiertos del paciente (cierre opcional).
  final EncounterOpenProblems? openProblems;
  /// Avisos soft del dominio (p. ej. ítems ya activos / nota casi idéntica).
  final List<EncounterCaptureAdvisory> advisories;
  /// Nota parecida a una evolución previa (aviso; no bloquea el guardado).
  final bool episodeNoteDuplicate;

  bool get hasUnresolvedIssues => issues.isNotEmpty;
  bool get hasOpenProblems =>
      openProblems != null && openProblems!.isNotEmpty;

  EncounterIncompleteItem? incompleteForItem(String itemId) {
    for (final item in incompleteItems) {
      if (item.itemId == itemId) return item;
    }
    return null;
  }

  List<EncounterCaptureIssue> issuesForItem(String itemId) {
    final prefix = '$itemId:';
    return issues.where((i) => i.id.startsWith(prefix)).toList();
  }

  List<EncounterCaptureIssue> get orphanIssues {
    final claimed = <String>{};
    for (final cat in categories) {
      for (final item in cat.items) {
        for (final issue in issuesForItem(item.id)) {
          claimed.add(issue.id);
        }
      }
    }
    return issues.where((i) => !claimed.contains(i.id)).toList();
  }

  /// Huérfanos cuyo ítem está tildado (o el id no parsea). Destildados no se muestran.
  List<EncounterCaptureIssue> orphanIssuesForStaged(Set<String> stagedItemIds) {
    final itemIdRe = RegExp(r'^(.*)::(\d+):');
    return orphanIssues.where((issue) {
      final m = itemIdRe.firstMatch(issue.id);
      if (m == null) return true;
      return stagedItemIds.contains('${m.group(1)}::${m.group(2)}');
    }).toList();
  }

  bool get hasExtractedContent =>
      categories.any((c) => c.items.isNotEmpty) && systemError == null;

  /// Ítems anclados en el texto del profesional (los sugeridos por IA no cuentan:
  /// vienen sin tildar y confirmarlos es opcional). Excluye ya activos en episodio.
  bool get hasClinicalItems =>
      categories.any((c) =>
          c.items.any((i) => i.isFromClinicalText && !i.alreadyActive)) &&
      systemError == null;

  bool get canConfirmSave {
    if (systemError != null) return false;
    if (episodeNoteDuplicate) return false;
    if (textoOriginal.trim().isEmpty) return false;
    return true;
  }

  List<EncounterCaptureItem> get allItems =>
      categories.expand((c) => c.items).toList();

  factory EncounterCaptureAnalysis.fromApiResponse(Map<String, dynamic> res) {
    final captureReview = res['capture_review'];
    if (captureReview is Map) {
      return EncounterCaptureAnalysis.fromCaptureReview(
        Map<String, dynamic>.from(captureReview),
      );
    }
    return EncounterCaptureAnalysis._fromLegacyResponse(res);
  }

  factory EncounterCaptureAnalysis.fromCaptureReview(
    Map<String, dynamic> review,
  ) {
    String? systemError;
    final err = review['system_error'];
    if (err is Map) {
      final texto = err['texto']?.toString() ?? '';
      final detalle = err['detalle']?.toString() ?? '';
      final joined = [texto, detalle].where((s) => s.isNotEmpty).join(' ');
      if (joined.isNotEmpty) {
        systemError = joined;
      }
    }

    final categoriesRaw = review['categories'];
    final categories = categoriesRaw is List
        ? categoriesRaw
            .whereType<Map>()
            .map((e) => EncounterCaptureCategory.fromCaptureReview(
                  Map<String, dynamic>.from(e),
                ))
            .toList()
        : <EncounterCaptureCategory>[];

    final defaultIdsRaw = review['default_staged_item_ids'];
    final defaultIds = defaultIdsRaw is List
        ? defaultIdsRaw.map((e) => e.toString()).toList()
        : categories
            .expand((c) => c.items)
            .where((i) => i.isFromClinicalText && !i.alreadyActive)
            .map((i) => i.id)
            .toList();

    String? faltantesMsg;
    var incompleteItems = const <EncounterIncompleteItem>[];
    var missingCategories = const <String>[];
    final detalle = review['datos_faltantes_detalle'];
    if (detalle is Map) {
      final m = detalle['message']?.toString().trim();
      if (m != null && m.isNotEmpty) faltantesMsg = m;
      incompleteItems = _parseIncompleteItems(detalle['incomplete_items']);
      missingCategories = _parseMissingCategories(detalle['missing_categories']);
    }

    final issues = _parseIssues(review['issues'] ??
        (detalle is Map ? detalle['issues'] : null));

    final advisories = _parseAdvisories(review['advisories']);
    final noteDup = (detalle is Map && detalle['episode_note_duplicate'] == true) ||
        advisories.any((a) => a.code == 'episode_note_duplicate');

    return EncounterCaptureAnalysis(
      textoOriginal: (review['texto_original'] ?? '').toString(),
      textoProcesado: review['texto_procesado']?.toString(),
      tieneDatosFaltantes: review['tiene_datos_faltantes'] == true,
      categories: categories,
      systemError: systemError,
      defaultStagedItemIds: defaultIds,
      puedeConfirmar: review['puede_confirmar'] != false,
      datosFaltantesMensaje: faltantesMsg,
      incompleteItems: incompleteItems,
      missingCategories: missingCategories,
      issues: issues,
      openProblems: EncounterOpenProblems.fromJson(review['open_problems']),
      advisories: advisories,
      episodeNoteDuplicate: noteDup,
    );
  }

  factory EncounterCaptureAnalysis._fromLegacyResponse(
    Map<String, dynamic> res,
  ) {
    final extraidos = _resolveExtraidos(res['datos']);
    final categoriasRaw = res['categorias'];
    final categorias = categoriasRaw is List
        ? categoriasRaw
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList()
        : <Map<String, dynamic>>[];

    String? systemError;
    final err = extraidos['Error'];
    if (err is Map) {
      final tipo = err['tipo']?.toString() ?? '';
      if (tipo == 'error_sistema' ||
          tipo == 'error_ia' ||
          tipo == 'error_configuracion') {
        final texto = err['texto']?.toString() ?? '';
        final detalle = err['detalle']?.toString() ?? '';
        systemError = [texto, detalle].where((s) => s.isNotEmpty).join(' ');
      }
    }

    final categories = <EncounterCaptureCategory>[];
    if (categorias.isNotEmpty) {
      for (final cat in categorias) {
        final title = cat['titulo']?.toString() ?? '';
        if (title.isEmpty) continue;
        final required = cat['requerido'] == true;
        final items = _parseCategoryItems(
          categoryTitle: title,
          raw: extraidos[title],
          camposRequeridos: _camposRequeridosFromCat(cat),
        );
        categories.add(
          EncounterCaptureCategory(
            title: title,
            required: required,
            items: items,
          ),
        );
      }
    } else {
      for (final entry in extraidos.entries) {
        if (entry.key == 'Error') continue;
        final items = _parseCategoryItems(
          categoryTitle: entry.key,
          raw: entry.value,
        );
        if (items.isEmpty) continue;
        categories.add(
          EncounterCaptureCategory(
            title: entry.key,
            required: false,
            items: items,
          ),
        );
      }
    }

    final defaultIds = categories
        .expand((c) => c.items)
        .where((i) => i.isFromClinicalText && !i.alreadyActive)
        .map((i) => i.id)
        .toList();

    String? faltantesMsg;
    var incompleteItems = const <EncounterIncompleteItem>[];
    var missingCategories = const <String>[];
    final detalle = res['datos_faltantes_detalle'];
    if (detalle is Map) {
      final m = detalle['message']?.toString().trim();
      if (m != null && m.isNotEmpty) faltantesMsg = m;
      incompleteItems = _parseIncompleteItems(detalle['incomplete_items']);
      missingCategories = _parseMissingCategories(detalle['missing_categories']);
    }

    final issues = _parseIssues(res['issues'] ??
        (detalle is Map ? detalle['issues'] : null));

    final advisories = _parseAdvisories(res['advisories']);
    final noteDup = (detalle is Map && detalle['episode_note_duplicate'] == true) ||
        advisories.any((a) => a.code == 'episode_note_duplicate');

    return EncounterCaptureAnalysis(
      textoOriginal: (res['texto_original'] ?? '').toString(),
      textoProcesado: res['texto_procesado']?.toString(),
      tieneDatosFaltantes: res['tiene_datos_faltantes'] == true,
      categories: categories,
      systemError: systemError,
      defaultStagedItemIds: defaultIds,
      puedeConfirmar: res['puede_confirmar'] != false && systemError == null,
      datosFaltantesMensaje: faltantesMsg,
      incompleteItems: incompleteItems,
      missingCategories: missingCategories,
      issues: issues,
      openProblems: EncounterOpenProblems.fromJson(res['open_problems']),
      advisories: advisories,
      episodeNoteDuplicate: noteDup,
    );
  }

  static List<EncounterCaptureAdvisory> _parseAdvisories(dynamic raw) {
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map((e) =>
            EncounterCaptureAdvisory.fromJson(Map<String, dynamic>.from(e)))
        .where((a) => a.message.isNotEmpty)
        .toList();
  }

  static List<EncounterCaptureIssue> _parseIssues(dynamic raw) {
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map((e) => EncounterCaptureIssue.fromJson(Map<String, dynamic>.from(e)))
        .where((i) => i.id.isNotEmpty)
        .toList();
  }

  static List<EncounterIncompleteItem> _parseIncompleteItems(dynamic raw) {
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map((e) =>
            EncounterIncompleteItem.fromJson(Map<String, dynamic>.from(e)))
        .where((i) => i.category.isNotEmpty)
        .toList();
  }

  static List<String> _parseMissingCategories(dynamic raw) {
    if (raw is! List) return const [];
    return raw
        .map((e) => e.toString().trim())
        .where((s) => s.isNotEmpty)
        .toList();
  }

  static Map<String, dynamic> _resolveExtraidos(dynamic datos) {
    if (datos is! Map) return {};
    final map = Map<String, dynamic>.from(datos);
    final inner = map['datosExtraidos'];
    if (inner is Map) {
      return Map<String, dynamic>.from(inner);
    }
    return map;
  }

  static List<String> _camposRequeridosFromCat(Map<String, dynamic> cat) {
    final raw = cat['campos_requeridos'];
    if (raw is! List) return const [];
    return raw.map((e) => e.toString()).where((s) => s.isNotEmpty).toList();
  }

  static List<EncounterCaptureItem> _parseCategoryItems({
    required String categoryTitle,
    required dynamic raw,
    List<String> camposRequeridos = const [],
  }) {
    if (raw == null) return [];
    if (raw is String && raw.trim().isNotEmpty) {
      return [
        EncounterCaptureItem(
          id: '$categoryTitle::0',
          categoryTitle: categoryTitle,
          label: raw.trim(),
          raw: {'texto': raw.trim()},
        ),
      ];
    }
    if (raw is! List) return [];

    final out = <EncounterCaptureItem>[];
    for (var i = 0; i < raw.length; i++) {
      final row = raw[i];
      if (row is String && row.trim().isNotEmpty) {
        out.add(
          EncounterCaptureItem(
            id: '$categoryTitle::$i',
            categoryTitle: categoryTitle,
            label: row.trim(),
            raw: {'texto': row.trim()},
          ),
        );
        continue;
      }
      if (row is Map) {
        final m = Map<String, dynamic>.from(row);
        final label = _labelFromMap(m, camposRequeridos);
        if (label.isEmpty) continue;
        out.add(
          EncounterCaptureItem(
            id: '$categoryTitle::$i',
            categoryTitle: categoryTitle,
            label: label,
            subtitle: _subtitleFromMap(m, camposRequeridos, label),
            raw: m,
          ),
        );
      }
    }
    return out;
  }

  static String _labelFromMap(
    Map<String, dynamic> m, [
    List<String> camposRequeridos = const [],
  ]) {
    for (final key in camposRequeridos) {
      final v = m[key]?.toString().trim();
      if (v != null && v.isNotEmpty) return v;
    }
    for (final key in [
      'termino',
      'descripcion',
      'texto',
      'nombre',
      'display',
      'medicamento',
      'label',
    ]) {
      final v = m[key]?.toString().trim();
      if (v != null && v.isNotEmpty) return v;
    }
    final parts = <String>[];
    m.forEach((k, v) {
      if (v == null) return;
      final s = v.toString().trim();
      if (s.isEmpty) return;
      parts.add('$k: $s');
    });
    return parts.take(3).join(' · ');
  }

  static String? _subtitleFromMap(
    Map<String, dynamic> m, [
    List<String> camposRequeridos = const [],
    String label = '',
  ]) {
    for (final key in ['codigo', 'codigo_cie10', 'cie10', 'conceptId']) {
      final v = m[key]?.toString().trim();
      if (v != null && v.isNotEmpty) return v;
    }
    final parts = <String>[];
    final remaining = camposRequeridos.length > 1
        ? camposRequeridos.sublist(1)
        : <String>[];
    if (remaining.isEmpty) {
      for (final entry in m.entries) {
        if (_isStructuralSubtitleKey(entry.key)) continue;
        final s = _formatSubtitleValue(entry.key, entry.value);
        if (s.isEmpty || s == label || _isStructuralSubtitleValue(s)) continue;
        parts.add(s);
        if (parts.length >= 4) break;
      }
    } else {
      for (final key in remaining) {
        if (_isStructuralSubtitleKey(key)) continue;
        final s = _formatSubtitleValue(key, m[key]);
        if (s.isEmpty || s == label || _isStructuralSubtitleValue(s)) continue;
        parts.add(s);
        if (parts.length >= 4) break;
      }
    }
    if (parts.isEmpty) return null;
    return parts.join(' · ');
  }

  static bool _isStructuralSubtitleKey(String key) {
    final folded = key.toLowerCase().replaceAll(RegExp(r'\s+'), '');
    return const {
      'tipo',
      'type',
      'category',
      'kind',
      'source',
    }.contains(folded);
  }

  static bool _isStructuralSubtitleValue(String value) {
    final folded =
        value.trim().toLowerCase().replaceAll('-', '_').replaceAll(' ', '_');
    return const {
      'follow_up',
      'followup',
      'counseling',
      'counselling',
      'conditional',
      'ordered',
      'mentioned',
      'order',
    }.contains(folded);
  }

  static String _formatSubtitleValue(String key, dynamic value) {
    if (value == null) return '';
    final text = value.toString().trim();
    if (text.isEmpty) return '';
    final foldedKey = key.toLowerCase().replaceAll(RegExp(r'\s+'), '');
    if (const {
          'plazodias',
          'plazo_dias',
          'delaydays',
          'delay_days',
        }.contains(foldedKey) &&
        RegExp(r'^\d+$').hasMatch(text)) {
      return '$text días';
    }
    return text;
  }

  /// Reconstruye `datosExtraidos` solo con ítems incluidos en el guardado.
  Map<String, dynamic> toDatosExtraidos(Set<String> stagedIds) {
    final out = <String, dynamic>{};
    for (final cat in categories) {
      final rows = <dynamic>[];
      for (final item in cat.items) {
        if (!stagedIds.contains(item.id)) continue;
        final payload = item.raw.isNotEmpty
            ? Map<String, dynamic>.from(item.raw)
            : <String, dynamic>{'texto': item.label};
        if ((payload['texto']?.toString().trim().isEmpty ?? true) &&
            item.label.trim().isNotEmpty &&
            !payload.values.any((v) => v != null && v.toString().trim().isNotEmpty)) {
          payload['texto'] = item.label;
        }
        rows.add(payload);
      }
      if (rows.isEmpty) continue;
      out[cat.title] = rows;
    }
    return out;
  }

  /// Ítems anclados en texto clínico que aún no están activos en el episodio.
  Set<String> get clinicalItemIds => allItems
      .where((i) => i.isFromClinicalText && !i.alreadyActive)
      .map((e) => e.id)
      .toSet();

  /// Stage efectivo = lo que el profesional tildó (destildar no se reintroduce).
  Set<String> effectiveSaveItemIds(Set<String> stagedIds) => {...stagedIds};
}

class EncounterCaptureCategory {
  const EncounterCaptureCategory({
    required this.title,
    required this.required,
    required this.items,
    this.model = '',
  });

  final String title;
  final String model;
  final bool required;
  final List<EncounterCaptureItem> items;

  factory EncounterCaptureCategory.fromCaptureReview(Map<String, dynamic> cat) {
    final title = cat['title']?.toString() ?? '';
    final itemsRaw = cat['items'];
    final items = itemsRaw is List
        ? itemsRaw
            .whereType<Map>()
            .map((e) => EncounterCaptureItem.fromCaptureReview(
                  title,
                  Map<String, dynamic>.from(e),
                ))
            .toList()
        : <EncounterCaptureItem>[];

    return EncounterCaptureCategory(
      title: title,
      model: cat['model']?.toString() ?? '',
      required: cat['required'] == true,
      items: items,
    );
  }
}

/// clinical: anclado en el texto del médico; ai: aporte/enriquecimiento de la IA.
enum EncounterCaptureItemSource { clinical, ai }

class EncounterCaptureItem {
  const EncounterCaptureItem({
    required this.id,
    required this.categoryTitle,
    required this.label,
    required this.raw,
    this.subtitle,
    this.source,
    this.alreadyActive = false,
  });

  final String id;
  final String categoryTitle;
  final String label;
  final String? subtitle;
  final Map<String, dynamic> raw;

  /// null = legacy / sin dato → se trata como texto clínico.
  final EncounterCaptureItemSource? source;

  /// Ya activo en el episodio (sale destildado; se puede tildar a mano).
  final bool alreadyActive;

  bool get isFromClinicalText => source != EncounterCaptureItemSource.ai;

  factory EncounterCaptureItem.fromCaptureReview(
    String categoryTitle,
    Map<String, dynamic> item,
  ) {
    final payload = item['payload'];
    final raw = payload is Map
        ? Map<String, dynamic>.from(payload)
        : <String, dynamic>{};
    final sourceRaw = item['source']?.toString().trim().toLowerCase();

    return EncounterCaptureItem(
      id: item['id']?.toString() ?? '$categoryTitle::0',
      categoryTitle: categoryTitle,
      label: item['label']?.toString() ?? '',
      subtitle: item['subtitle']?.toString(),
      raw: raw,
      source: sourceRaw == 'ai'
          ? EncounterCaptureItemSource.ai
          : EncounterCaptureItemSource.clinical,
      alreadyActive: item['already_active'] == true ||
          item['already_active'] == 1 ||
          item['already_active']?.toString() == '1',
    );
  }
}

class EncounterCaptureAdvisory {
  const EncounterCaptureAdvisory({
    required this.message,
    this.code,
    this.severity = 'warning',
  });

  final String message;
  final String? code;
  final String severity;

  factory EncounterCaptureAdvisory.fromJson(Map<String, dynamic> json) {
    return EncounterCaptureAdvisory(
      message: (json['message'] ?? '').toString().trim(),
      code: json['code']?.toString(),
      severity: (json['severity'] ?? 'warning').toString(),
    );
  }
}

class EncounterIncompleteItem {
  const EncounterIncompleteItem({
    required this.category,
    required this.index,
    required this.label,
    required this.missingFields,
  });

  final String category;
  final int index;
  final String label;
  final List<String> missingFields;

  String get itemId => '$category::$index';

  String get message {
    final fields = missingFields.join(', ');
    if (label.trim().isNotEmpty) {
      return 'En $category («$label») faltan: $fields.';
    }
    return 'En $category faltan: $fields.';
  }

  factory EncounterIncompleteItem.fromJson(Map<String, dynamic> json) {
    final fieldsRaw = json['missing_fields'];
    final fields = fieldsRaw is List
        ? fieldsRaw.map((e) => e.toString()).where((s) => s.isNotEmpty).toList()
        : <String>[];
    return EncounterIncompleteItem(
      category: (json['category'] ?? '').toString(),
      index: int.tryParse('${json['index'] ?? 0}') ?? 0,
      label: (json['label'] ?? '').toString(),
      missingFields: fields,
    );
  }
}

class EncounterCaptureIssue {
  const EncounterCaptureIssue({
    required this.id,
    required this.field,
    this.options = const [],
    this.allowCustom = false,
  });

  final String id;
  final String field;
  final List<EncounterCaptureIssueOption> options;
  final bool allowCustom;

  factory EncounterCaptureIssue.fromJson(Map<String, dynamic> json) {
    final optsRaw = json['options'];
    final options = optsRaw is List
        ? optsRaw
            .whereType<Map>()
            .map((e) => EncounterCaptureIssueOption.fromJson(
                  Map<String, dynamic>.from(e),
                ))
            .toList()
        : <EncounterCaptureIssueOption>[];

    return EncounterCaptureIssue(
      id: json['id']?.toString() ?? '',
      field: json['field']?.toString() ?? '',
      options: options,
      allowCustom: json['allow_custom'] == true,
    );
  }
}

class EncounterCaptureIssueOption {
  const EncounterCaptureIssueOption({
    required this.value,
    required this.label,
  });

  final dynamic value;
  final String label;

  factory EncounterCaptureIssueOption.fromJson(Map<String, dynamic> json) {
    return EncounterCaptureIssueOption(
      value: json['value'],
      label: (json['label'] ?? json['value'] ?? '').toString(),
    );
  }
}

class EncounterOpenProblems {
  const EncounterOpenProblems({
    this.conditions = const [],
    this.carePlans = const [],
  });

  final List<EncounterOpenProblemItem> conditions;
  final List<EncounterOpenProblemItem> carePlans;

  bool get isNotEmpty => conditions.isNotEmpty || carePlans.isNotEmpty;

  factory EncounterOpenProblems.fromJson(dynamic raw) {
    if (raw is! Map) return const EncounterOpenProblems();
    final map = Map<String, dynamic>.from(raw);
    final conditionOptions = _parseOptions(map['condition_options']);
    final carePlanOptions = _parseOptions(map['care_plan_options']);
    return EncounterOpenProblems(
      conditions: _parseItems(map['conditions'], conditionOptions),
      carePlans: _parseItems(map['care_plans'], carePlanOptions),
    );
  }

  static List<EncounterCaptureIssueOption> _parseOptions(dynamic raw) {
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map((e) => EncounterCaptureIssueOption.fromJson(
              Map<String, dynamic>.from(e),
            ))
        .toList();
  }

  static List<EncounterOpenProblemItem> _parseItems(
    dynamic raw,
    List<EncounterCaptureIssueOption> sharedOptions,
  ) {
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map((e) {
          final item = EncounterOpenProblemItem.fromJson(
            Map<String, dynamic>.from(e),
          );
          if (item.options.isNotEmpty || sharedOptions.isEmpty) {
            return item;
          }
          return EncounterOpenProblemItem(
            id: item.id,
            kind: item.kind,
            label: item.label,
            options: sharedOptions,
            statusLabel: item.statusLabel,
            detail: item.detail,
          );
        })
        .where((i) => i.id > 0)
        .toList();
  }
}

class EncounterOpenProblemItem {
  const EncounterOpenProblemItem({
    required this.id,
    required this.kind,
    required this.label,
    this.options = const [],
    this.statusLabel,
    this.detail,
  });

  final int id;
  final String kind;
  final String label;
  final List<EncounterCaptureIssueOption> options;
  final String? statusLabel;
  final String? detail;

  factory EncounterOpenProblemItem.fromJson(Map<String, dynamic> json) {
    final optsRaw = json['options'];
    final options = optsRaw is List
        ? optsRaw
            .whereType<Map>()
            .map((e) => EncounterCaptureIssueOption.fromJson(
                  Map<String, dynamic>.from(e),
                ))
            .toList()
        : <EncounterCaptureIssueOption>[];

    final detail = (json['detail'] ?? json['subtitle'])?.toString().trim();

    return EncounterOpenProblemItem(
      id: int.tryParse('${json['id']}') ?? 0,
      kind: (json['kind'] ?? 'condition').toString(),
      label: (json['label'] ?? '').toString(),
      options: options,
      statusLabel: (json['status_label'] ?? json['clinical_status'] ?? json['status'])
          ?.toString(),
      detail: (detail != null && detail.isNotEmpty) ? detail : null,
    );
  }
}

