import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';

enum BioBadgeVariant { filled, soft, outline }

/// Etiqueta de estado (turno cancelado, paciente activo, etc.).
///
/// Equivalente a `badge bg-{intent}` / `badge text-bg-{intent}-subtle`.
class BioBadge extends StatelessWidget {
  const BioBadge({
    super.key,
    required this.label,
    this.intent = UiIntent.neutral,
    this.variant = BioBadgeVariant.soft,
    this.icon,
  });

  factory BioBadge.warning(String label, {IconData? icon}) =>
      BioBadge(label: label, intent: UiIntent.warning, icon: icon);

  factory BioBadge.danger(String label, {IconData? icon}) =>
      BioBadge(label: label, intent: UiIntent.danger, icon: icon);

  factory BioBadge.success(String label, {IconData? icon}) =>
      BioBadge(label: label, intent: UiIntent.success, icon: icon);

  factory BioBadge.info(String label, {IconData? icon}) =>
      BioBadge(label: label, intent: UiIntent.info, icon: icon);

  factory BioBadge.neutral(String label, {IconData? icon}) =>
      BioBadge(label: label, intent: UiIntent.neutral, icon: icon);

  final String label;
  final UiIntent intent;
  final BioBadgeVariant variant;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final palette = IntentPalette.of(intent);
    final Color bg;
    final Color fg;
    BorderSide side = BorderSide.none;

    switch (variant) {
      case BioBadgeVariant.filled:
        bg = palette.base;
        fg = palette.onBase;
        break;
      case BioBadgeVariant.soft:
        bg = palette.softBg;
        fg = palette.softFg;
        side = BorderSide(color: palette.border, width: BorderWidth.thin);
        break;
      case BioBadgeVariant.outline:
        bg = Colors.transparent;
        fg = palette.base;
        side = BorderSide(color: palette.base, width: BorderWidth.thin);
        break;
    }

    return Container(
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(BioRadius.xs),
        border: side == BorderSide.none ? null : Border.fromBorderSide(side),
      ),
      padding: const EdgeInsets.symmetric(
        horizontal: BioSpacing.sm,
        vertical: 2,
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 12, color: fg),
            const SizedBox(width: BioSpacing.xs),
          ],
          Text(
            label,
            style: BioTypography.caption.copyWith(
              color: fg,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}
