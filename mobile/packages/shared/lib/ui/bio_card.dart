import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';

/// Contenedor "papel": fondo claro, borde sutil, sin sombra por default.
///
/// Apariencias:
/// - `BioCard(...)` → default (`paper300` thin).
/// - `BioCard.emphasis(...)` → seleccionada / activa (`paper400` medium).
/// - `BioCard.intent(...)` → con cinta lateral del intent (post-it).
class BioCard extends StatelessWidget {
  const BioCard({
    super.key,
    required this.child,
    this.padding = BioSpacing.card,
    this.margin = EdgeInsets.zero,
    this.border,
    this.borderRadius,
    this.shadow,
    this.onTap,
    this.color,
  });

  factory BioCard.emphasis({
    Key? key,
    required Widget child,
    EdgeInsets padding = BioSpacing.card,
    EdgeInsets margin = EdgeInsets.zero,
    VoidCallback? onTap,
  }) =>
      BioCard(
        key: key,
        padding: padding,
        margin: margin,
        onTap: onTap,
        border: BioBorder.paperEmphasis,
        child: child,
      );

  factory BioCard.intent({
    Key? key,
    required Widget child,
    required UiIntent intent,
    EdgeInsets padding = BioSpacing.card,
    EdgeInsets margin = EdgeInsets.zero,
    VoidCallback? onTap,
  }) {
    final palette = IntentPalette.of(intent);
    return BioCard(
      key: key,
      padding: padding,
      margin: margin,
      onTap: onTap,
      border: Border(
        top: const BorderSide(
          color: PaperPalette.paper300,
          width: BorderWidth.thin,
        ),
        right: const BorderSide(
          color: PaperPalette.paper300,
          width: BorderWidth.thin,
        ),
        bottom: const BorderSide(
          color: PaperPalette.paper300,
          width: BorderWidth.thin,
        ),
        left: BorderSide(color: palette.base, width: BorderWidth.thick),
      ),
      child: child,
    );
  }

  final Widget child;
  final EdgeInsets padding;
  final EdgeInsets margin;
  final Border? border;
  final BorderRadius? borderRadius;
  final List<BoxShadow>? shadow;
  final VoidCallback? onTap;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final radius = borderRadius ?? BorderRadius.circular(BioRadius.sm);
    final effectiveBorder = border ?? BioBorder.paperDefault;

    final inner = Container(
      margin: margin,
      decoration: BoxDecoration(
        color: color ?? tokens.paperSurface,
        borderRadius: radius,
        border: effectiveBorder,
        boxShadow: shadow,
      ),
      child: ClipRRect(
        borderRadius: radius,
        child: Padding(padding: padding, child: child),
      ),
    );

    if (onTap == null) return inner;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: radius,
        onTap: onTap,
        splashColor: PaperPalette.paper300.withValues(alpha: 0.35),
        highlightColor: PaperPalette.paper200,
        child: inner,
      ),
    );
  }
}
