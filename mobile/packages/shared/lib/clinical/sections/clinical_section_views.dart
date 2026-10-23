import 'package:flutter/material.dart';

import '../../theme/tokens/tokens.dart';
import 'clinical_section.dart';

String clinicalFieldText(ClinicalField f) => [
      if (f.label.isNotEmpty) '${f.label}:',
      f.value,
      if (f.unit.isNotEmpty) f.unit,
    ].join(' ');

Widget _sectionTitle(ClinicalSection s) => s.title.isEmpty
    ? const SizedBox.shrink()
    : Padding(
        padding: const EdgeInsets.only(bottom: BioSpacing.xs),
        child: Text(s.title, style: BioTypography.overline),
      );

/// Renderizador por defecto de campos: una línea `etiqueta: valor unidad` por campo.
Widget buildFieldsSection(BuildContext context, ClinicalSection s) {
  return Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      _sectionTitle(s),
      for (final f in s.fields)
        Padding(
          padding: const EdgeInsets.only(bottom: BioSpacing.xs),
          child: Text(clinicalFieldText(f), style: BioTypography.body),
        ),
    ],
  );
}

/// Renderizador por defecto de listas de texto.
Widget buildListSection(BuildContext context, ClinicalSection s) {
  return Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      _sectionTitle(s),
      for (final item in s.items)
        Padding(
          padding: const EdgeInsets.only(bottom: BioSpacing.xs),
          child: Text(item, style: BioTypography.body),
        ),
    ],
  );
}
