import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';

/// Item de [BioBottomNav].
class BioBottomNavItem {
  const BioBottomNavItem({
    required this.icon,
    required this.label,
  });

  final IconData icon;
  final String label;
}

/// Bottom nav suspendida: márgenes laterales/inferiores, radio y borde papel.
/// Color activo = primary (token).
class BioBottomNav extends StatelessWidget {
  const BioBottomNav({
    super.key,
    required this.items,
    required this.currentIndex,
    required this.onTap,
    this.borderWidth = BorderWidth.medium,
    this.borderColor,
  });

  final List<BioBottomNavItem> items;
  final int currentIndex;
  final ValueChanged<int> onTap;

  final double borderWidth;
  final Color? borderColor;

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final resolvedBorderColor = borderColor ?? tokens.paperBorderDefault;
    final activeColor = IntentPalette.of(UiIntent.primary).base;
    final inactiveColor = tokens.textMuted;
    final radius = BioRadius.all(BioRadius.xl);

    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          BioSpacing.xl,
          0,
          BioSpacing.xl,
          BioSpacing.sm,
        ),
        child: Material(
          color: tokens.paperSurface,
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: radius,
            side: BorderSide(
              color: resolvedBorderColor,
              width: borderWidth,
            ),
          ),
          clipBehavior: Clip.antiAlias,
          child: SizedBox(
            height: 64,
            child: Row(
              children: List<Widget>.generate(items.length, (i) {
                final item = items[i];
                final selected = i == currentIndex;
                final color = selected ? activeColor : inactiveColor;
                return Expanded(
                  child: InkWell(
                    onTap: () => onTap(i),
                    splashColor:
                        PaperPalette.paper300.withValues(alpha: 0.4),
                    highlightColor: PaperPalette.paper200,
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        vertical: BioSpacing.sm,
                      ),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          AnimatedScale(
                            scale: selected ? 1.05 : 1.0,
                            duration: BioMotion.fast,
                            curve: BioMotion.standard,
                            child: Icon(item.icon, size: 24, color: color),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            item.label,
                            style: BioTypography.caption.copyWith(
                              color: color,
                              fontWeight: selected
                                  ? FontWeight.w600
                                  : FontWeight.w400,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              }),
            ),
          ),
        ),
      ),
    );
  }
}
