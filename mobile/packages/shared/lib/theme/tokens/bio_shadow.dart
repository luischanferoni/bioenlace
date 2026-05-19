import 'package:flutter/material.dart';

import 'paper_palette.dart';

/// Sombras bajas y cálidas. En el sistema "papel" preferimos bordes sobre
/// sombras; usar shadow XOR border, no ambos.
@immutable
class BioShadow {
  const BioShadow();

  static const List<BoxShadow> none = <BoxShadow>[];

  static List<BoxShadow> get xs => <BoxShadow>[
        BoxShadow(
          color: PaperPalette.paper900.withValues(alpha: 0.04),
          blurRadius: 4,
          offset: const Offset(0, 1),
        ),
      ];

  static List<BoxShadow> get sm => <BoxShadow>[
        BoxShadow(
          color: PaperPalette.paper900.withValues(alpha: 0.06),
          blurRadius: 8,
          offset: const Offset(0, 2),
        ),
      ];

  static List<BoxShadow> get md => <BoxShadow>[
        BoxShadow(
          color: PaperPalette.paper900.withValues(alpha: 0.08),
          blurRadius: 16,
          offset: const Offset(0, 4),
        ),
      ];
}
