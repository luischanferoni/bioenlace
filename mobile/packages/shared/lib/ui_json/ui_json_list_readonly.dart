import 'package:flutter/material.dart';

/// Listas `kind: list` sin selección (p. ej. DataAccess /listar).
bool uiJsonListIsReadOnly(Map<String, dynamic> block) {
  final selection = block['selection'];
  if (selection is Map) {
    final mode = selection['mode']?.toString().trim().toLowerCase() ?? '';
    if (mode == 'none') {
      return true;
    }
  }
  final pres = block['presentation'];
  if (pres is Map) {
    if (pres['layout']?.toString().trim().toLowerCase() == 'table') {
      return true;
    }
  }
  final draft = block['draft_field']?.toString().trim() ?? '';
  return draft.isEmpty;
}

List<Map<String, String>> uiJsonListColumnsFromBlock(Map<String, dynamic> block) {
  final raw = block['columns'];
  if (raw is! List) {
    return const [
      {'field': 'name', 'label': 'Nombre'},
    ];
  }
  final out = <Map<String, String>>[];
  for (final col in raw) {
    if (col is! Map) {
      continue;
    }
    final field = col['field']?.toString().trim() ?? '';
    if (field.isEmpty) {
      continue;
    }
    final label = col['label']?.toString().trim() ?? field;
    out.add({'field': field, 'label': label});
  }
  if (out.isEmpty) {
    return const [
      {'field': 'name', 'label': 'Nombre'},
    ];
  }
  return out;
}

String uiJsonListCellValue(Map<String, dynamic> item, String field) {
  if (item.containsKey(field)) {
    final v = item[field];
    if (v == null) {
      return '';
    }
    return v.toString().trim();
  }
  if (field.startsWith('meta.')) {
    final meta = item['meta'];
    if (meta is Map) {
      final key = field.substring(5);
      final v = meta[key];
      if (v == null) {
        return '';
      }
      return v.toString().trim();
    }
  }
  return '';
}

/// Tabla compacta para listados informativos (staff).
Widget uiJsonReadOnlyListTable({
  required Map<String, dynamic> block,
  required List<dynamic> items,
  required TextTheme textTheme,
}) {
  final title = block['title']?.toString().trim() ?? '';
  final emptyMessage = (block['empty_message'] ?? block['list_empty_message'])?.toString().trim() ?? '';
  final columns = uiJsonListColumnsFromBlock(block);

  if (items.isEmpty) {
    final msg = emptyMessage.isNotEmpty
        ? emptyMessage
        : 'No hay registros que coincidan con los filtros.';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (title.isNotEmpty) ...[
          Text(title, style: textTheme.titleSmall),
          const SizedBox(height: 4),
        ],
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 4),
          child: Text(msg, style: textTheme.bodyMedium),
        ),
      ],
    );
  }

  return Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      if (title.isNotEmpty) ...[
        Text(title, style: textTheme.titleSmall),
        const SizedBox(height: 6),
      ],
      LayoutBuilder(
        builder: (context, constraints) {
          return SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: ConstrainedBox(
              constraints: BoxConstraints(minWidth: constraints.maxWidth),
              child: DataTable(
                headingRowHeight: 36,
                dataRowMinHeight: 40,
                dataRowMaxHeight: 56,
                columnSpacing: 16,
                horizontalMargin: 8,
                headingTextStyle: textTheme.labelMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
                columns: [
                  for (final col in columns)
                    DataColumn(label: Text(col['label'] ?? col['field'] ?? '')),
                ],
                rows: [
                  for (final raw in items)
                    if (raw is Map)
                      DataRow(
                        cells: [
                          for (final col in columns)
                            DataCell(
                              Text(
                                uiJsonListCellValue(Map<String, dynamic>.from(raw), col['field'] ?? ''),
                                style: textTheme.bodySmall,
                              ),
                            ),
                        ],
                      ),
                ],
              ),
            ),
          );
        },
      ),
    ],
  );
}
