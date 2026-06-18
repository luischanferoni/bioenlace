import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';
import '../ui/bio_card.dart';
import 'asistente_service.dart';

/// Lista de atajos del asistente en formato tarjeta (bienvenida y menú Atajos).
class AtajoShortcutCards extends StatelessWidget {
  const AtajoShortcutCards({
    super.key,
    required this.categorias,
    required this.onTap,
    this.enabled = true,
    this.padding = const EdgeInsets.symmetric(horizontal: BioSpacing.lg),
    this.showCategoryTitles = true,
  });

  final List<AtajoCategoria> categorias;
  final void Function(String intentId, String title) onTap;
  final bool enabled;
  final EdgeInsets padding;
  final bool showCategoryTitles;

  @override
  Widget build(BuildContext context) {
    if (categorias.isEmpty) {
      return const SizedBox.shrink();
    }
    final tokens = context.bio;

    return Padding(
      padding: padding,
      child: LayoutBuilder(
        builder: (context, constraints) {
          final twoCols = constraints.maxWidth >= 520;
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              for (final cat in categorias) ...[
                if (showCategoryTitles) ...[
                  Text(
                    cat.titulo,
                    style: BioTypography.title.copyWith(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: tokens.textMuted,
                      letterSpacing: 0.2,
                    ),
                  ),
                  BioSpacing.gapH(BioSpacing.sm),
                ],
                _ShortcutGrid(
                  items: cat.items,
                  twoColumns: twoCols,
                  enabled: enabled,
                  onTap: onTap,
                ),
                BioSpacing.gapH(BioSpacing.md),
              ],
            ],
          );
        },
      ),
    );
  }
}

class _ShortcutGrid extends StatelessWidget {
  const _ShortcutGrid({
    required this.items,
    required this.twoColumns,
    required this.enabled,
    required this.onTap,
  });

  final List<AtajoItem> items;
  final bool twoColumns;
  final bool enabled;
  final void Function(String intentId, String title) onTap;

  @override
  Widget build(BuildContext context) {
    if (!twoColumns) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final item in items) ...[
            _ShortcutCard(
              item: item,
              enabled: enabled,
              onTap: onTap,
            ),
            BioSpacing.gapH(BioSpacing.sm),
          ],
        ],
      );
    }

    final rows = <Widget>[];
    for (var i = 0; i < items.length; i += 2) {
      final left = items[i];
      final right = i + 1 < items.length ? items[i + 1] : null;
      rows.add(
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: _ShortcutCard(item: left, enabled: enabled, onTap: onTap),
            ),
            BioSpacing.gapW(BioSpacing.sm),
            Expanded(
              child: right == null
                  ? const SizedBox.shrink()
                  : _ShortcutCard(item: right, enabled: enabled, onTap: onTap),
            ),
          ],
        ),
      );
      rows.add(BioSpacing.gapH(BioSpacing.sm));
    }
    return Column(children: rows);
  }
}

class _ShortcutCard extends StatelessWidget {
  const _ShortcutCard({
    required this.item,
    required this.enabled,
    required this.onTap,
  });

  final AtajoItem item;
  final bool enabled;
  final void Function(String intentId, String title) onTap;

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final primary = IntentPalette.of(UiIntent.primary).base;

    return BioCard.intent(
      intent: UiIntent.primary,
      padding: const EdgeInsets.symmetric(
        horizontal: BioSpacing.md,
        vertical: BioSpacing.sm + 4,
      ),
      onTap: enabled ? () => onTap(item.intentId, item.title) : null,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  item.title,
                  style: BioTypography.title.copyWith(
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                    color: primary,
                    height: 1.25,
                  ),
                ),
                if (item.description.isNotEmpty) ...[
                  BioSpacing.gapH(BioSpacing.xs),
                  Text(
                    item.description,
                    style: BioTypography.bodySm.copyWith(
                      color: tokens.textMuted,
                      height: 1.35,
                    ),
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ],
            ),
          ),
          BioSpacing.gapW(BioSpacing.xs),
          Icon(
            Icons.chevron_right_rounded,
            size: 22,
            color: tokens.textMuted.withValues(alpha: 0.75),
          ),
        ],
      ),
    );
  }
}
