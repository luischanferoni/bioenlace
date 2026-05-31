/// Detección de lista embebible con un solo ítem (auto-avance de flow).
class UiJsonSingleListPick {
  const UiJsonSingleListPick({
    required this.draftField,
    required this.itemId,
    required this.item,
  });

  final String draftField;
  final String itemId;
  final Map<String, dynamic> item;

  static UiJsonSingleListPick? fromDefinition(Map<String, dynamic> definition) {
    final blocks = definition['blocks'];
    if (blocks is! List) return null;

    final ordered = _blocksOrderedForRender(blocks);

    for (final bRaw in ordered) {
      if (bRaw is! Map) continue;
      final b = Map<String, dynamic>.from(bRaw);
      if (b['kind']?.toString() != 'list') continue;

      final itemsRaw = b['items'];
      final pickable = _pickableItems(b, itemsRaw is List ? itemsRaw : const []);
      if (pickable.length != 1) continue;

      final selection = b['selection'] is Map
          ? Map<String, dynamic>.from(b['selection'] as Map)
          : const <String, dynamic>{};
      if (selection['requires_confirmation'] == true) continue;

      final draftField = b['draft_field']?.toString() ?? '';
      if (draftField.isEmpty) continue;

      final it = pickable.first;
      final item = Map<String, dynamic>.from(it);

      final itemId = itemIdFromBlock(b, item);
      if (itemId == null || itemId.isEmpty) continue;

      return UiJsonSingleListPick(
        draftField: draftField,
        itemId: itemId,
        item: item,
      );
    }

    return null;
  }

  static List<Map<String, dynamic>> _pickableItems(
    Map<String, dynamic> block,
    List<dynamic> itemsRaw,
  ) {
    final out = <Map<String, dynamic>>[];
    for (final it in itemsRaw) {
      if (it is! Map) continue;
      final item = Map<String, dynamic>.from(it);
      final id = itemIdFromBlock(block, item);
      if (id != null && id.isNotEmpty) {
        out.add(item);
      }
    }
    return out;
  }

  Map<String, dynamic> toDraftDelta() {
    return {
      draftField: itemId,
      '_flow_item_$draftField': item,
    };
  }

  /// Id estable del ítem de un bloque `kind: list` (respeta `item.id_field`).
  static String? itemIdFromBlock(Map<String, dynamic> block, Map<String, dynamic> item) {
    final itemConfig = block['item'] is Map
        ? Map<String, dynamic>.from(block['item'] as Map)
        : const <String, dynamic>{};
    final idField = itemConfig['id_field']?.toString().trim();
    final idKeys = <String>[
      if (idField != null && idField.isNotEmpty) idField,
      'id',
      'value',
    ];
    for (final key in idKeys) {
      final raw = item[key];
      if (raw == null) continue;
      final s = raw.toString().trim();
      if (s.isNotEmpty) {
        return s;
      }
    }
    return null;
  }

  static List<dynamic> _blocksOrderedForRender(List<dynamic> raw) {
    if (raw.isEmpty) return raw;
    if (!raw.every((b) => b is Map && (b as Map)['display_order'] != null)) {
      return raw;
    }
    int orderKey(dynamic v) {
      if (v is int) return v;
      return int.tryParse(v?.toString() ?? '') ?? 0;
    }

    final out = List<dynamic>.from(raw);
    out.sort((a, b) =>
        orderKey((a as Map)['display_order']).compareTo(orderKey((b as Map)['display_order'])));
    return out;
  }
}
