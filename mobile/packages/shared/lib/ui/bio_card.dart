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
  })  : _ribbonColor = null,
        _ribbonWidth = 0;

  // Constructor privado: borde uniforme + cinta lateral dibujada con un
  // overlay para respetar `borderRadius` sin violar la regla de Flutter
  // "A borderRadius can only be given on borders with uniform colors".
  const BioCard._ribbon({
    super.key,
    required this.child,
    this.padding = BioSpacing.card,
    this.margin = EdgeInsets.zero,
    this.onTap,
    required Color ribbonColor,
    double ribbonWidth = 4,
  })  : border = null,
        borderRadius = null,
        shadow = null,
        color = null,
        _ribbonColor = ribbonColor,
        _ribbonWidth = ribbonWidth;

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
    return BioCard._ribbon(
      key: key,
      padding: padding,
      margin: margin,
      onTap: onTap,
      ribbonColor: palette.base,
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
  final Color? _ribbonColor;
  final double _ribbonWidth;

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final radius = borderRadius ?? BorderRadius.circular(BioRadius.sm);
    final effectiveBorder = border ?? BioBorder.paperDefault;

    Widget body = Padding(padding: padding, child: child);

    // Cinta lateral del intent dibujada como banda posicionada, dentro del
    // ClipRRect (queda recortada por las esquinas redondeadas).
    if (_ribbonColor != null) {
      body = Stack(
        children: [
          body,
          Positioned(
            left: 0,
            top: 0,
            bottom: 0,
            width: _ribbonWidth,
            child: ColoredBox(color: _ribbonColor),
          ),
        ],
      );
    }

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
        child: body,
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
