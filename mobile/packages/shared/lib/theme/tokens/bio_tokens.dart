import 'package:flutter/material.dart';

import 'paper_palette.dart';
import 'ui_intent.dart';

/// Agregador de tokens accesible desde el contexto:
/// `Theme.of(context).extension<BioTokens>()` o `context.bio`.
///
/// La mayoría de los tokens son `static const`/`static get` (paper, radius,
/// spacing, etc.); esta extensión registra los que sí dependen del theme y
/// devuelve helpers semánticos (`intentPalette`, `backdrop`).
@immutable
class BioTokens extends ThemeExtension<BioTokens> {
  const BioTokens({
    this.paperBackground = PaperPalette.paper50,
    this.paperSurface = PaperPalette.paper50,
    this.paperSurfaceSunken = PaperPalette.paper100,
    this.paperBorderDefault = PaperPalette.paper300,
    this.paperBorderEmphasis = PaperPalette.paper700,
    this.paperDividerSubtle = PaperPalette.paper150,
    this.paperDividerDefault = PaperPalette.paper200,
    this.textTitle = PaperPalette.paper700,
    this.textBody = PaperPalette.paper600,
    this.textMuted = PaperPalette.paper500,
    this.textDisabled = PaperPalette.paper400,
    this.backdropColor = const Color(0x4D1A1916), // paper900 @ 30%
  });

  final Color paperBackground;
  final Color paperSurface;
  final Color paperSurfaceSunken;
  final Color paperBorderDefault;
  final Color paperBorderEmphasis;
  final Color paperDividerSubtle;
  final Color paperDividerDefault;

  final Color textTitle;
  final Color textBody;
  final Color textMuted;
  final Color textDisabled;

  /// Backdrop translúcido cálido (modales, drawers).
  final Color backdropColor;

  /// Atajo para `IntentPalette.of(...)`.
  IntentPalette intentPalette(UiIntent intent) => IntentPalette.of(intent);

  static const BioTokens light = BioTokens();

  @override
  BioTokens copyWith({
    Color? paperBackground,
    Color? paperSurface,
    Color? paperSurfaceSunken,
    Color? paperBorderDefault,
    Color? paperBorderEmphasis,
    Color? paperDividerSubtle,
    Color? paperDividerDefault,
    Color? textTitle,
    Color? textBody,
    Color? textMuted,
    Color? textDisabled,
    Color? backdropColor,
  }) {
    return BioTokens(
      paperBackground: paperBackground ?? this.paperBackground,
      paperSurface: paperSurface ?? this.paperSurface,
      paperSurfaceSunken: paperSurfaceSunken ?? this.paperSurfaceSunken,
      paperBorderDefault: paperBorderDefault ?? this.paperBorderDefault,
      paperBorderEmphasis: paperBorderEmphasis ?? this.paperBorderEmphasis,
      paperDividerSubtle: paperDividerSubtle ?? this.paperDividerSubtle,
      paperDividerDefault: paperDividerDefault ?? this.paperDividerDefault,
      textTitle: textTitle ?? this.textTitle,
      textBody: textBody ?? this.textBody,
      textMuted: textMuted ?? this.textMuted,
      textDisabled: textDisabled ?? this.textDisabled,
      backdropColor: backdropColor ?? this.backdropColor,
    );
  }

  @override
  BioTokens lerp(ThemeExtension<BioTokens>? other, double t) {
    if (other is! BioTokens) return this;
    return BioTokens(
      paperBackground: Color.lerp(paperBackground, other.paperBackground, t)!,
      paperSurface: Color.lerp(paperSurface, other.paperSurface, t)!,
      paperSurfaceSunken:
          Color.lerp(paperSurfaceSunken, other.paperSurfaceSunken, t)!,
      paperBorderDefault:
          Color.lerp(paperBorderDefault, other.paperBorderDefault, t)!,
      paperBorderEmphasis:
          Color.lerp(paperBorderEmphasis, other.paperBorderEmphasis, t)!,
      paperDividerSubtle:
          Color.lerp(paperDividerSubtle, other.paperDividerSubtle, t)!,
      paperDividerDefault:
          Color.lerp(paperDividerDefault, other.paperDividerDefault, t)!,
      textTitle: Color.lerp(textTitle, other.textTitle, t)!,
      textBody: Color.lerp(textBody, other.textBody, t)!,
      textMuted: Color.lerp(textMuted, other.textMuted, t)!,
      textDisabled: Color.lerp(textDisabled, other.textDisabled, t)!,
      backdropColor: Color.lerp(backdropColor, other.backdropColor, t)!,
    );
  }
}

extension BioTokensContext on BuildContext {
  /// Acceso rápido a [BioTokens] desde el contexto.
  /// Si no estuviera registrado, devuelve [BioTokens.light] como fallback.
  BioTokens get bio =>
      Theme.of(this).extension<BioTokens>() ?? BioTokens.light;
}
