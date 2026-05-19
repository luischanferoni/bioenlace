import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';

/// Línea separadora "papel".
///
/// Apariencias:
/// - `BioDivider()`            → default (paper200, hairline).
/// - `BioDivider.subtle()`     → ultra suave (paper150).
/// - `BioDivider.emphasis()`   → marcada (paper400, medium).
class BioDivider extends StatelessWidget {
  const BioDivider({
    super.key,
    this.color,
    this.thickness = BorderWidth.hairline,
    this.indent = 0,
    this.endIndent = 0,
    this.height,
  });

  factory BioDivider.subtle({double? height}) => BioDivider(
        height: height,
        color: PaperPalette.paper150,
        thickness: BorderWidth.hairline,
      );

  factory BioDivider.emphasis({double? height}) => BioDivider(
        height: height,
        color: PaperPalette.paper400,
        thickness: BorderWidth.medium,
      );

  final Color? color;
  final double thickness;
  final double indent;
  final double endIndent;
  final double? height;

  @override
  Widget build(BuildContext context) {
    return Divider(
      height: height ?? thickness,
      thickness: thickness,
      color: color ?? context.bio.paperDividerDefault,
      indent: indent,
      endIndent: endIndent,
    );
  }
}
