import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';

/// Chip de filtro/selección.
///
/// - Inactivo: borde paper300, fondo papel.
/// - Activo: borde + softBg del intent.
class BioChip extends StatelessWidget {
  const BioChip({
    super.key,
    required this.label,
    this.selected = false,
    this.onTap,
    this.icon,
    this.intent = UiIntent.primary,
  });

  final String label;
  final bool selected;
  final VoidCallback? onTap;
  final IconData? icon;
  final UiIntent intent;

  @override
  Widget build(BuildContext context) {
    final palette = IntentPalette.of(intent);
    final tokens = context.bio;
    final Color bg = selected ? palette.softBg : tokens.paperSurface;
    final Color fg = selected ? palette.softFg : tokens.textBody;
    final Color border =
        selected ? palette.border : tokens.paperBorderDefault;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(BioRadius.xs),
        onTap: onTap,
        splashColor: PaperPalette.paper300.withValues(alpha: 0.35),
        highlightColor: PaperPalette.paper200,
        child: AnimatedContainer(
          duration: BioMotion.fast,
          curve: BioMotion.standard,
          padding: const EdgeInsets.symmetric(
            horizontal: BioSpacing.md,
            vertical: BioSpacing.xs + 2,
          ),
          decoration: BoxDecoration(
            color: bg,
            borderRadius: BorderRadius.circular(BioRadius.xs),
            border: Border.all(color: border, width: BorderWidth.thin),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (icon != null) ...[
                Icon(icon, size: 14, color: fg),
                const SizedBox(width: BioSpacing.xs),
              ],
              Text(
                label,
                style: BioTypography.bodySm.copyWith(
                  color: fg,
                  fontWeight:
                      selected ? FontWeight.w600 : FontWeight.w400,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
