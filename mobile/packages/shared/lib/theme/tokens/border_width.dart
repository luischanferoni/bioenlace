import 'package:flutter/material.dart';

/// Anchos de borde estandarizados. Útil para alimentar [BorderSide.width].
@immutable
class BorderWidth {
  const BorderWidth();

  /// 0.5 — separadores internos en listas densas.
  static const double hairline = 0.5;

  /// 1.0 — default de cards, inputs, badges outline.
  static const double thin = 1.0;

  /// 1.5 — énfasis (AppBar bottom, BottomNav top, foco de input).
  static const double medium = 1.5;

  /// 2.5 — outline de botones grandes, error con foco.
  static const double thick = 2.5;

  /// 4.0 — alerta crítica, raro.
  static const double heavy = 4.0;
}
