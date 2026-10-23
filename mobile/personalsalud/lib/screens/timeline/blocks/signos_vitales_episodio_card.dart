import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../../infra/app_texts.dart';
import '../../../services/historia_clinica_service.dart';

/// Signos vitales del episodio de captura: últimos valores y series.
class SignosVitalesEpisodioCard extends StatelessWidget {
  const SignosVitalesEpisodioCard({super.key, required this.data});

  final SignosVitalesEpisodio? data;

  @override
  Widget build(BuildContext context) {
    final sv = data;
    final tiene = sv != null && sv.tieneDatos;
    final ultimos =
        sv?.ultimos ?? const <String, SignosVitalesEpisodioUltimo>{};
    final chips = <Widget>[];
    void addChip(String text, UiIntent intent) {
      chips.add(BioBadge(label: text, intent: intent));
    }
  
    for (final ultimo in ultimos.values) {
      if (ultimo.value == null || ultimo.label == null) continue;
      final unit = ultimo.unit ?? '';
      addChip(
        '${ultimo.label}: ${ultimo.value}'
        '${unit.isNotEmpty ? ' $unit' : ''}'
        '${ultimo.at != null ? ' · ${ultimo.at}' : ''}',
        ultimo.intent,
      );
    }
  
    final genericFields = sv?.sections
        .expand((section) => section.fields)
        .where((field) => field.value != null)
        .toList();
    if (genericFields != null && genericFields.isNotEmpty) {
      chips.clear();
      for (final field in genericFields) {
        final latest = ultimos[field.key];
        final numericValue = field.value!;
        final value =
            field.format == 'integer' &&
                numericValue == numericValue.truncateToDouble()
            ? numericValue.toInt().toString()
            : numericValue.toString();
        addChip(
          '${field.label}: $value'
          '${field.unit.isNotEmpty ? ' ${field.unit}' : ''}'
          '${latest?.at != null ? ' · ${latest!.at}' : ''}',
          field.intent,
        );
      }
    }
  
    return BioCard.intent(
      intent: UiIntent.info,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  AppTexts.vitalsCurrent,
                  style: BioTypography.title,
                ),
              ),
              if (sv != null && sv.totalPoints > 0)
                Text(
                  '${sv.totalPoints} medición${sv.totalPoints == 1 ? '' : 'es'}',
                  style: BioTypography.caption.copyWith(
                    color: context.bio.textMuted,
                  ),
                ),
            ],
          ),
          BioSpacing.gapH(BioSpacing.md),
          if (!tiene)
            Text(
              AppTexts.vitalsEmptyEpisode,
              style: BioTypography.bodySm.copyWith(
                color: context.bio.textMuted,
              ),
            )
          else ...[
            if (chips.isNotEmpty)
              Wrap(
                spacing: BioSpacing.xs,
                runSpacing: BioSpacing.xs,
                children: chips,
              ),
            if (sv.series.isNotEmpty) ...[
              BioSpacing.gapH(BioSpacing.md),
              ExpansionTile(
                tilePadding: EdgeInsets.zero,
                title: Text(
                  AppTexts.vitalsHistoryMeasurements(sv.totalPoints),
                  style: BioTypography.title,
                ),
                children: [
                  for (final s in sv.series) ...[
                    Padding(
                      padding: const EdgeInsets.only(bottom: BioSpacing.xs),
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: BioBadge(
                          label:
                              '${s.label.isNotEmpty ? s.label : s.metric}'
                              '${s.unit.isNotEmpty ? ' (${s.unit})' : ''}',
                          intent: s.intent,
                        ),
                      ),
                    ),
                    for (final p in s.points.reversed.take(5))
                      Padding(
                        padding: const EdgeInsets.only(bottom: 2),
                        child: Align(
                          alignment: Alignment.centerLeft,
                          child: Text(
                            '${p.at}: ${p.value}${s.unit.isNotEmpty ? ' ${s.unit}' : ''}'
                            '${p.source.isNotEmpty ? ' · ${p.source}' : ''}',
                            style: BioTypography.caption.copyWith(
                              color: context.bio.textMuted,
                            ),
                          ),
                        ),
                      ),
                    BioSpacing.gapH(BioSpacing.xs),
                  ],
                ],
              ),
            ],
          ],
        ],
      ),
    );
  }
}
