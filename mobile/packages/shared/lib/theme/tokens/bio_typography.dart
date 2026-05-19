import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import 'paper_palette.dart';

/// Escala tipográfica única (Open Sans). Colores por defecto: títulos `paper700`,
/// body `paper600`, caption/overline `paper500`. Si se necesita otro color,
/// usar `.copyWith(color: ...)` puntualmente.
@immutable
class BioTypography {
  const BioTypography();

  static TextStyle get display => GoogleFonts.openSans(
        fontSize: 36,
        fontWeight: FontWeight.w700,
        color: PaperPalette.paper700,
        height: 1.15,
      );

  static TextStyle get h1 => GoogleFonts.openSans(
        fontSize: 28,
        fontWeight: FontWeight.w700,
        color: PaperPalette.paper700,
        height: 1.2,
      );

  static TextStyle get h2 => GoogleFonts.openSans(
        fontSize: 22,
        fontWeight: FontWeight.w600,
        color: PaperPalette.paper700,
        height: 1.25,
      );

  static TextStyle get h3 => GoogleFonts.openSans(
        fontSize: 18,
        fontWeight: FontWeight.w600,
        color: PaperPalette.paper700,
        height: 1.3,
      );

  static TextStyle get title => GoogleFonts.openSans(
        fontSize: 16,
        fontWeight: FontWeight.w600,
        color: PaperPalette.paper700,
        height: 1.35,
      );

  static TextStyle get body => GoogleFonts.openSans(
        fontSize: 14,
        fontWeight: FontWeight.w400,
        color: PaperPalette.paper600,
        height: 1.45,
      );

  static TextStyle get bodySm => GoogleFonts.openSans(
        fontSize: 13,
        fontWeight: FontWeight.w400,
        color: PaperPalette.paper600,
        height: 1.45,
      );

  static TextStyle get caption => GoogleFonts.openSans(
        fontSize: 12,
        fontWeight: FontWeight.w400,
        color: PaperPalette.paper500,
        height: 1.4,
      );

  static TextStyle get overline => GoogleFonts.openSans(
        fontSize: 11,
        fontWeight: FontWeight.w600,
        color: PaperPalette.paper500,
        height: 1.35,
        letterSpacing: 0.8,
      );

  /// Construye un [TextTheme] de Material a partir de la escala.
  static TextTheme materialTextTheme() {
    return TextTheme(
      displayLarge: display,
      displayMedium: h1,
      displaySmall: h2,
      headlineLarge: h1,
      headlineMedium: h2,
      headlineSmall: h3,
      titleLarge: h3,
      titleMedium: title,
      titleSmall: title.copyWith(fontSize: 14),
      bodyLarge: body,
      bodyMedium: body,
      bodySmall: bodySm,
      labelLarge: title.copyWith(fontSize: 14),
      labelMedium: caption,
      labelSmall: overline,
    );
  }
}
