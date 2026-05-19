import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';

/// Variantes Bootstrap-like:
/// - `filled`: equivalente a `btn-{intent}`.
/// - `outline`: equivalente a `btn-outline-{intent}`.
/// - `soft`: equivalente a `btn-{intent}-subtle` (Bootstrap 5.3).
enum BioButtonVariant { filled, outline, soft }

/// Tamaños: `sm`, `md` (default), `lg`.
enum BioButtonSize { sm, md, lg }

/// Botón unificado del sistema: intent × variant × size.
///
/// Uso:
/// ```dart
/// BioButton(label: 'Guardar', intent: UiIntent.primary, onPressed: ...);
/// BioButton(label: 'Cancelar', intent: UiIntent.neutral, variant: BioButtonVariant.outline, onPressed: ...);
/// BioButton.primary(label: 'Reservar', onPressed: ...);
/// BioButton.outlinePrimary(label: 'Ver más', onPressed: ...);
/// ```
class BioButton extends StatelessWidget {
  const BioButton({
    super.key,
    required this.label,
    this.onPressed,
    this.intent = UiIntent.primary,
    this.variant = BioButtonVariant.filled,
    this.size = BioButtonSize.md,
    this.icon,
    this.iconRight,
    this.fullWidth = false,
    this.loading = false,
  });

  // Atajos tipo Bootstrap ------------------------------------------------------

  factory BioButton.primary({
    Key? key,
    required String label,
    VoidCallback? onPressed,
    BioButtonSize size = BioButtonSize.md,
    IconData? icon,
    bool fullWidth = false,
    bool loading = false,
  }) =>
      BioButton(
        key: key,
        label: label,
        onPressed: onPressed,
        intent: UiIntent.primary,
        variant: BioButtonVariant.filled,
        size: size,
        icon: icon,
        fullWidth: fullWidth,
        loading: loading,
      );

  factory BioButton.outlinePrimary({
    Key? key,
    required String label,
    VoidCallback? onPressed,
    BioButtonSize size = BioButtonSize.md,
    IconData? icon,
    bool fullWidth = false,
  }) =>
      BioButton(
        key: key,
        label: label,
        onPressed: onPressed,
        intent: UiIntent.primary,
        variant: BioButtonVariant.outline,
        size: size,
        icon: icon,
        fullWidth: fullWidth,
      );

  factory BioButton.softPrimary({
    Key? key,
    required String label,
    VoidCallback? onPressed,
    BioButtonSize size = BioButtonSize.md,
    IconData? icon,
    bool fullWidth = false,
  }) =>
      BioButton(
        key: key,
        label: label,
        onPressed: onPressed,
        intent: UiIntent.primary,
        variant: BioButtonVariant.soft,
        size: size,
        icon: icon,
        fullWidth: fullWidth,
      );

  factory BioButton.danger({
    Key? key,
    required String label,
    VoidCallback? onPressed,
    BioButtonSize size = BioButtonSize.md,
    IconData? icon,
    bool fullWidth = false,
  }) =>
      BioButton(
        key: key,
        label: label,
        onPressed: onPressed,
        intent: UiIntent.danger,
        variant: BioButtonVariant.filled,
        size: size,
        icon: icon,
        fullWidth: fullWidth,
      );

  factory BioButton.outlineDanger({
    Key? key,
    required String label,
    VoidCallback? onPressed,
    BioButtonSize size = BioButtonSize.md,
    IconData? icon,
    bool fullWidth = false,
  }) =>
      BioButton(
        key: key,
        label: label,
        onPressed: onPressed,
        intent: UiIntent.danger,
        variant: BioButtonVariant.outline,
        size: size,
        icon: icon,
        fullWidth: fullWidth,
      );

  factory BioButton.neutral({
    Key? key,
    required String label,
    VoidCallback? onPressed,
    BioButtonSize size = BioButtonSize.md,
    IconData? icon,
    bool fullWidth = false,
  }) =>
      BioButton(
        key: key,
        label: label,
        onPressed: onPressed,
        intent: UiIntent.neutral,
        variant: BioButtonVariant.outline,
        size: size,
        icon: icon,
        fullWidth: fullWidth,
      );

  // ---------------------------------------------------------------------------

  final String label;
  final VoidCallback? onPressed;
  final UiIntent intent;
  final BioButtonVariant variant;
  final BioButtonSize size;
  final IconData? icon;
  final IconData? iconRight;
  final bool fullWidth;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    final palette = IntentPalette.of(intent);
    final disabled = onPressed == null || loading;
    final dims = _dimsFor(size);

    final Color bg;
    final Color fg;
    final BorderSide side;
    final double radius = (size == BioButtonSize.lg)
        ? BioRadius.md
        : BioRadius.sm;

    switch (variant) {
      case BioButtonVariant.filled:
        bg = palette.base;
        fg = palette.onBase;
        side = BorderSide.none;
        break;
      case BioButtonVariant.outline:
        bg = Colors.transparent;
        fg = palette.base;
        side = BorderSide(
          color: palette.base,
          width: size == BioButtonSize.lg
              ? BorderWidth.medium
              : BorderWidth.thin,
        );
        break;
      case BioButtonVariant.soft:
        bg = palette.softBg;
        fg = palette.softFg;
        side = BorderSide(color: palette.border, width: BorderWidth.thin);
        break;
    }

    final child = _buildChild(fg, dims);

    final style = ButtonStyle(
      backgroundColor: WidgetStateProperty.resolveWith((states) {
        if (states.contains(WidgetState.disabled)) {
          return bg.withValues(alpha: bg.a * 0.5);
        }
        return bg;
      }),
      foregroundColor: WidgetStateProperty.resolveWith((states) {
        if (states.contains(WidgetState.disabled)) {
          return fg.withValues(alpha: fg.a * 0.5);
        }
        return fg;
      }),
      overlayColor: WidgetStateProperty.resolveWith((states) {
        if (states.contains(WidgetState.pressed)) {
          return PaperPalette.paper300.withValues(alpha: 0.35);
        }
        if (states.contains(WidgetState.hovered)) {
          return PaperPalette.paper200;
        }
        return null;
      }),
      animationDuration: BioMotion.fast,
      padding: WidgetStateProperty.all(dims.padding),
      minimumSize: WidgetStateProperty.all(
        Size(fullWidth ? double.infinity : 0, dims.height),
      ),
      shape: WidgetStateProperty.all(
        RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radius),
          side: side,
        ),
      ),
      textStyle: WidgetStateProperty.all(dims.textStyle),
      elevation: WidgetStateProperty.all(0),
      splashFactory: InkRipple.splashFactory,
      visualDensity: VisualDensity.standard,
    );

    return TextButton(
      onPressed: disabled ? null : onPressed,
      style: style,
      child: child,
    );
  }

  Widget _buildChild(Color fg, _ButtonDims dims) {
    if (loading) {
      return SizedBox(
        height: dims.iconSize,
        width: dims.iconSize,
        child: CircularProgressIndicator(
          strokeWidth: 2,
          valueColor: AlwaysStoppedAnimation<Color>(fg),
        ),
      );
    }
    final hasLeft = icon != null;
    final hasRight = iconRight != null;
    if (!hasLeft && !hasRight) {
      return Text(label);
    }
    return Row(
      mainAxisSize: MainAxisSize.min,
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        if (hasLeft) ...[
          Icon(icon, size: dims.iconSize),
          SizedBox(width: dims.iconGap),
        ],
        Flexible(child: Text(label, overflow: TextOverflow.ellipsis)),
        if (hasRight) ...[
          SizedBox(width: dims.iconGap),
          Icon(iconRight, size: dims.iconSize),
        ],
      ],
    );
  }

  _ButtonDims _dimsFor(BioButtonSize size) {
    switch (size) {
      case BioButtonSize.sm:
        return _ButtonDims(
          height: 32,
          padding: const EdgeInsets.symmetric(horizontal: BioSpacing.md),
          textStyle: BioTypography.bodySm.copyWith(fontWeight: FontWeight.w600),
          iconSize: 16,
          iconGap: BioSpacing.xs,
        );
      case BioButtonSize.md:
        return _ButtonDims(
          height: 44,
          padding: const EdgeInsets.symmetric(horizontal: BioSpacing.lg),
          textStyle: BioTypography.body.copyWith(fontWeight: FontWeight.w600),
          iconSize: 18,
          iconGap: BioSpacing.sm,
        );
      case BioButtonSize.lg:
        return _ButtonDims(
          height: 52,
          padding: const EdgeInsets.symmetric(horizontal: BioSpacing.xl),
          textStyle: BioTypography.body.copyWith(
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
          iconSize: 20,
          iconGap: BioSpacing.sm,
        );
    }
  }
}

class _ButtonDims {
  const _ButtonDims({
    required this.height,
    required this.padding,
    required this.textStyle,
    required this.iconSize,
    required this.iconGap,
  });

  final double height;
  final EdgeInsets padding;
  final TextStyle textStyle;
  final double iconSize;
  final double iconGap;
}
