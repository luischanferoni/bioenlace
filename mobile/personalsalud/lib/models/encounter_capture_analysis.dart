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

  bool get hasExtractedContent =>
      categories.any((c) => c.items.isNotEmpty) && systemError == null;

  bool get canConfirmSave {
    if (!puedeConfirmar || systemError != null) return false;
    if (textoOriginal.trim().isEmpty) return false;
    // Hard stop: categorías/campos requeridos incompletos.
    if (tieneDatosFaltantes) return false;
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
        : categories.expand((c) => c.items.map((i) => i.id)).toList();

    String? faltantesMsg;
    final detalle = review['datos_faltantes_detalle'];
    if (detalle is Map) {
      final m = detalle['message']?.toString().trim();
      if (m != null && m.isNotEmpty) faltantesMsg = m;
    }

    return EncounterCaptureAnalysis(
      textoOriginal: (review['texto_original'] ?? '').toString(),
      textoProcesado: review['texto_procesado']?.toString(),
      tieneDatosFaltantes: review['tiene_datos_faltantes'] == true,
      categories: categories,
      systemError: systemError,
      defaultStagedItemIds: defaultIds,
      puedeConfirmar: review['puede_confirmar'] != false,
      datosFaltantesMensaje: faltantesMsg,
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

    final defaultIds = categories.expand((c) => c.items.map((i) => i.id)).toList();

    String? faltantesMsg;
    final detalle = res['datos_faltantes_detalle'];
    if (detalle is Map) {
      final m = detalle['message']?.toString().trim();
      if (m != null && m.isNotEmpty) faltantesMsg = m;
    }

    return EncounterCaptureAnalysis(
      textoOriginal: (res['texto_original'] ?? '').toString(),
      textoProcesado: res['texto_procesado']?.toString(),
      tieneDatosFaltantes: res['tiene_datos_faltantes'] == true,
      categories: categories,
      systemError: systemError,
      defaultStagedItemIds: defaultIds,
      puedeConfirmar: res['puede_confirmar'] != false && systemError == null,
      datosFaltantesMensaje: faltantesMsg,
    );
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
        final s = entry.value?.toString().trim() ?? '';
        if (s.isEmpty || s == label) continue;
        parts.add(s);
        if (parts.length >= 4) break;
      }
    } else {
      for (final key in remaining) {
        final v = m[key]?.toString().trim();
        if (v == null || v.isEmpty || v == label) continue;
        parts.add(v);
        if (parts.length >= 4) break;
      }
    }
    if (parts.isEmpty) return null;
    return parts.join(' · ');
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
      final model = cat.model.trim();
      if (model.isNotEmpty && model != cat.title) {
        out[model] = rows;
      }
    }
    return out;
  }

  /// Ítems a forzar en el guardado además del stage UI.
  /// Incluye extracción completa: source=ai excluía medicación/indicaciones y el
  /// backend solo terminaba codificando el diagnóstico.
  Set<String> get clinicalItemIds => allItems.map((e) => e.id).toSet();

  /// Stage efectivo = selección del usuario ∪ extracción completa.
  Set<String> effectiveSaveItemIds(Set<String> stagedIds) => {
        ...stagedIds,
        ...clinicalItemIds,
      };
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
  });

  final String id;
  final String categoryTitle;
  final String label;
  final String? subtitle;
  final Map<String, dynamic> raw;

  /// null = legacy / sin dato → se trata como texto clínico.
  final EncounterCaptureItemSource? source;

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
    );
  }
}

