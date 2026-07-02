import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';

/// Opción de un [BioSegmentedTabs].
class BioSegmentedTab {
  const BioSegmentedTab({
    required this.label,
    this.icon,
  });

  final String label;
  final IconData? icon;
}

/// Pestañas segmentadas (track único). Alternativa a [BioChip] cuando el
/// control ocupa todo el ancho y no debe verse como badges sueltos.
class BioSegmentedTabs extends StatelessWidget {
  const BioSegmentedTabs({
    super.key,
    required this.tabs,
    required this.selectedIndex,
    required this.onSelected,
    this.intent = UiIntent.primary,
  });

  final List<BioSegmentedTab> tabs;
  final int selectedIndex;
  final ValueChanged<int> onSelected;
  final UiIntent intent;

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final palette = IntentPalette.of(intent);

    return DecoratedBox(
      decoration: BoxDecoration(
        color: tokens.paperSurfaceSunken,
        borderRadius: BioRadius.all(BioRadius.sm),
        border: Border.all(
          color: tokens.paperBorderDefault,
          width: BorderWidth.hairline,
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.all(3),
        child: Row(
          children: [
            for (var i = 0; i < tabs.length; i++) ...[
              if (i > 0) const SizedBox(width: 2),
              Expanded(
                child: _Segment(
                  tab: tabs[i],
                  selected: selectedIndex == i,
                  palette: palette,
                  tokens: tokens,
                  onTap: () => onSelected(i),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _Segment extends StatelessWidget {
  const _Segment({
    required this.tab,
    required this.selected,
    required this.palette,
    required this.tokens,
    required this.onTap,
  });

  final BioSegmentedTab tab;
  final bool selected;
  final IntentPalette palette;
  final BioTokens tokens;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final fg = selected ? palette.softFg : tokens.textMuted;
    final bg = selected ? tokens.paperSurface : Colors.transparent;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BioRadius.all(BioRadius.xs),
        splashColor: PaperPalette.paper300.withValues(alpha: 0.35),
        child: AnimatedContainer(
          duration: BioMotion.fast,
          curve: BioMotion.standard,
          padding: const EdgeInsets.symmetric(
            horizontal: BioSpacing.sm,
            vertical: BioSpacing.sm,
          ),
          decoration: BoxDecoration(
            color: bg,
            borderRadius: BioRadius.all(BioRadius.xs),
            boxShadow: selected
                ? [
                    BoxShadow(
                      color: PaperPalette.paper900.withValues(alpha: 0.06),
                      blurRadius: 4,
                      offset: const Offset(0, 1),
                    ),
                  ]
                : null,
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (tab.icon != null) ...[
                Icon(tab.icon, size: 16, color: fg),
                const SizedBox(width: BioSpacing.xs),
              ],
              Flexible(
                child: Text(
                  tab.label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: BioTypography.bodySm.copyWith(
                    color: fg,
                    fontWeight:
                        selected ? FontWeight.w600 : FontWeight.w500,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
