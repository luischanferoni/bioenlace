import 'package:flutter/material.dart';

import '../../theme/tokens/tokens.dart';
import 'clinical_section.dart';
import 'clinical_section_views.dart';

/// Renderiza una lista de secciones (título + campos o ítems) sin lógica por especialidad.
class ClinicalSectionList extends StatelessWidget {
  const ClinicalSectionList({super.key, required this.sections});

  final List<ClinicalSection> sections;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (final s in sections.where((s) => !s.isEmpty))
          Padding(
            padding: const EdgeInsets.only(bottom: BioSpacing.md),
            child: s.fields.isNotEmpty ? buildFieldsSection(context, s) : buildListSection(context, s),
          ),
      ],
    );
  }
}
