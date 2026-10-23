import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../../infra/app_texts.dart';
import '../../../models/timeline_event.dart';

List<String> _terminos(Iterable<String?> items) =>
    items.map((t) => t?.trim()).whereType<String>().where((t) => t.isNotEmpty).toList();

/// Condición actual: diagnósticos recientes, condiciones crónicas y alergias.
class InformacionMedicaCard extends StatelessWidget {
  const InformacionMedicaCard({super.key, required this.info});

  final InformacionMedica info;

  @override
  Widget build(BuildContext context) {
    final secciones = <({String titulo, List<String> items})>[
      (
        titulo: AppTexts.recentDiagnoses,
        items: _terminos(info.condicionesActivas.map((c) => c.termino)),
      ),
      (
        titulo: AppTexts.chronicConditions,
        items: _terminos(info.condicionesCronicas.map((c) => c.termino)),
      ),
      (titulo: 'Alergias', items: _terminos(info.hallazgos.map((h) => h.termino))),
    ];

    return BioCard.intent(
      intent: UiIntent.warning,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(AppTexts.timelineCurrentCondition, style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.lg),
          LayoutBuilder(
            builder: (context, constraints) {
              const spacing = BioSpacing.md;
              const minCardWidth = 148.0;
              final maxColumns =
                  ((constraints.maxWidth + spacing) / (minCardWidth + spacing)).floor().clamp(1, 4);
              final cardWidth = (constraints.maxWidth - spacing * (maxColumns - 1)) / maxColumns;

              return Wrap(
                spacing: spacing,
                runSpacing: spacing,
                children: [
                  for (final seccion in secciones)
                    SizedBox(
                      width: cardWidth,
                      child: _InfoBox(titulo: seccion.titulo, items: seccion.items),
                    ),
                ],
              );
            },
          ),
        ],
      ),
    );
  }
}

class _InfoBox extends StatelessWidget {
  const _InfoBox({required this.titulo, required this.items});

  final String titulo;
  final List<String> items;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(BioSpacing.sm),
      decoration: BoxDecoration(
        color: context.bio.paperSurfaceSunken,
        borderRadius: BorderRadius.circular(BioRadius.sm),
        border: Border.all(
          color: context.bio.paperBorderDefault,
          width: BorderWidth.thin,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(titulo, style: BioTypography.overline),
          BioSpacing.gapH(BioSpacing.sm),
          if (items.isEmpty)
            Text(AppTexts.noData, style: BioTypography.bodySm)
          else
            Wrap(
              spacing: BioSpacing.xs,
              runSpacing: BioSpacing.xs,
              children: items.map((item) => BioBadge.info(item.toUpperCase())).toList(),
            ),
        ],
      ),
    );
  }
}
