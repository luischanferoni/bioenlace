import 'package:flutter/material.dart';

/// Duraciones y curvas estandarizadas para animaciones / feedback táctil.
@immutable
class BioMotion {
  const BioMotion();

  static const Duration instant = Duration(milliseconds: 80);
  static const Duration fast = Duration(milliseconds: 150);
  static const Duration normal = Duration(milliseconds: 220);
  static const Duration slow = Duration(milliseconds: 350);

  static const Curve standard = Curves.easeOutCubic;
  static const Curve emphasized = Curves.easeInOutCubic;
  static const Curve decelerate = Curves.easeOut;
}
