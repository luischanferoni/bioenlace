import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../../infra/app_texts.dart';
import '../../../services/historia_clinica_service.dart';

/// Feed de hitos del episodio con filtros; el filtro elegido lo conserva quien lo usa.
class EpisodioFeedSection extends StatelessWidget {
  const EpisodioFeedSection({super.key, required this.feed, required this.selectedFilterId, required this.onFilterChanged});

  final TimelineEpisodioFeed? feed;
  final String selectedFilterId;
  final ValueChanged<String> onFilterChanged;

  @override
  Widget build(BuildContext context) {
    final all = feed?.items ?? const <TimelineEpisodioItem>[];
    final filters = feed?.filters ?? const <TimelineEpisodioFilter>[];
    TimelineEpisodioFilter? selectedFilter;
    for (final filter in filters) {
      if (filter.id == selectedFilterId) {
        selectedFilter = filter;
        break;
      }
    }
    selectedFilter ??= filters.isEmpty ? null : filters.first;
    final allowed = selectedFilter?.types;
    final items = allowed == null
        ? all
        : all.where((e) => allowed.contains(e.type)).toList();
  
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(AppTexts.timelineEpisodeRecord, style: BioTypography.title),
            ),
            if (all.isNotEmpty)
              Text(
                '${all.length} hito${all.length == 1 ? '' : 's'}',
                style: BioTypography.caption.copyWith(
                  color: context.bio.textMuted,
                ),
              ),
          ],
        ),
        BioSpacing.gapH(BioSpacing.sm),
        Wrap(
          spacing: BioSpacing.xs,
          runSpacing: BioSpacing.xs,
          children: [
            for (final f in filters)
              ChoiceChip(
                label: Text(f.label),
                selected: selectedFilter?.id == f.id,
                onSelected: (_) {
                  onFilterChanged(f.id);
                },
              ),
          ],
        ),
        BioSpacing.gapH(BioSpacing.sm),
        if (items.isEmpty)
          Text(
            all.isEmpty
                ? 'Sin hitos registrados en este episodio.'
                : 'Sin hitos para este filtro.',
            style: BioTypography.bodySm.copyWith(color: context.bio.textMuted),
          )
        else
          for (final it in items) ...[
            BioCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Wrap(
                    spacing: BioSpacing.xs,
                    runSpacing: BioSpacing.xs,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      BioBadge(label: it.typeLabel, intent: it.intent),
                      if (it.occurredAt.isNotEmpty)
                        Text(
                          formatEpisodioFecha(it.occurredAt),
                          style: BioTypography.caption.copyWith(
                            color: context.bio.textMuted,
                          ),
                        ),
                      if (it.actorNombre != null && it.actorNombre!.isNotEmpty)
                        Text(
                          it.actorNombre!,
                          style: BioTypography.caption.copyWith(
                            color: context.bio.textMuted,
                          ),
                        ),
                    ],
                  ),
                  BioSpacing.gapH(BioSpacing.xs),
                  Text(it.summary, style: BioTypography.body),
                ],
              ),
            ),
            BioSpacing.gapH(BioSpacing.sm),
          ],
      ],
    );
  }
}
