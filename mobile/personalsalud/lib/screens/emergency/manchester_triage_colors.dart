import 'package:flutter/material.dart';

/// Semáforo Manchester (niveles 1–5), alineado con web `guardia-tablero.css`.
abstract final class ManchesterTriageColors {
  static const Color level1 = Color(0xFFC0392B);
  static const Color level2 = Color(0xFFE67E22);
  static const Color level3 = Color(0xFFF1C40F);
  static const Color level4 = Color(0xFF27AE60);
  static const Color level5 = Color(0xFF3498DB);
  static const Color none = Color(0xFFADB5BD);

  /// Índice 0 = nivel 1 … índice 4 = nivel 5 (chips de triage).
  static const List<Color> levels = [
    level1,
    level2,
    level3,
    level4,
    level5,
  ];

  static Color backgroundForLevel(int? level) {
    switch (level) {
      case 1:
        return level1;
      case 2:
        return level2;
      case 3:
        return level3;
      case 4:
        return level4;
      case 5:
        return level5;
      default:
        return none;
    }
  }

  /// Texto sobre badge sólido (amarillo usa tinta oscura, como en web).
  static Color foregroundForLevel(int? level) {
    if (level == 3) return const Color(0xFF7D6608);
    if (level == null || level < 1 || level > 5) {
      return const Color(0xFF212529);
    }
    return Colors.white;
  }
}
