import 'package:flutter/material.dart';

/// Radios de esquina. En el sistema "papel" preferimos `sm` y `md`; evitar `pill`
/// salvo CTAs grandes excepcionales o tags muy pequeños.
@immutable
class BioRadius {
  const BioRadius();

  static const double none = 0;

  /// 4 — chips, badges.
  static const double xs = 4;

  /// 6 — default: inputs, cards "papel", botones.
  static const double sm = 6;

  /// 10 — contenedores grandes, CTAs grandes.
  static const double md = 10;

  /// 14 — hero / banners.
  static const double lg = 14;

  /// Pill — usar con criterio (avatares, tags pequeños).
  static const double pill = 999;

  static BorderRadius all(double r) => BorderRadius.circular(r);

  static BorderRadius top(double r) => BorderRadius.only(
        topLeft: Radius.circular(r),
        topRight: Radius.circular(r),
      );

  static BorderRadius bottom(double r) => BorderRadius.only(
        bottomLeft: Radius.circular(r),
        bottomRight: Radius.circular(r),
      );
}
