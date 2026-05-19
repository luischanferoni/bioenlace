import 'package:flutter/material.dart';

/// Espaciado en múltiplos de 4 (escala web estándar). Usar siempre estos tokens
/// en paddings/margins; evitar números crudos.
@immutable
class BioSpacing {
  const BioSpacing();

  static const double xs = 4;
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 24;
  static const double xxl = 32;
  static const double xxxl = 48;

  static const EdgeInsets pageHorizontal = EdgeInsets.symmetric(horizontal: lg);
  static const EdgeInsets pageAll = EdgeInsets.all(lg);
  static const EdgeInsets card = EdgeInsets.all(md);
  static const EdgeInsets sectionGapY = EdgeInsets.symmetric(vertical: lg);

  static SizedBox gapH(double size) => SizedBox(height: size);
  static SizedBox gapW(double size) => SizedBox(width: size);
}
