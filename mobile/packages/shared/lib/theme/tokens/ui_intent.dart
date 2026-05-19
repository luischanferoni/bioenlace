import 'package:flutter/material.dart';

import 'paper_palette.dart';

/// Intenciones semánticas (Bootstrap-like): primary, secondary, success, danger,
/// warning, info, neutral, dark. Cada componente consume `IntentPalette.of(intent)`
/// para tomar `base`, `onBase`, `softBg`, `softFg`, `border`.
enum UiIntent {
  primary,
  secondary,
  success,
  danger,
  warning,
  info,
  neutral,
  dark,
}

/// Paleta resuelta por [UiIntent]. Mezcla acentos BioEnlace con la escala
/// neutra "papel" para soft backgrounds (sin saturar).
@immutable
class IntentPalette {
  const IntentPalette({
    required this.base,
    required this.onBase,
    required this.softBg,
    required this.softFg,
    required this.border,
  });

  /// Color base de la intención (fondo de botón filled, badge, ícono).
  final Color base;

  /// Color del texto/ícono sobre [base].
  final Color onBase;

  /// Fondo "soft" (12–14% del base, sobre fondo papel).
  final Color softBg;

  /// Texto/ícono sobre [softBg] (más oscuro que [base] para legibilidad).
  final Color softFg;

  /// Color del borde (outline / softBorder).
  final Color border;

  static IntentPalette of(UiIntent intent) {
    switch (intent) {
      case UiIntent.primary:
        return _primary;
      case UiIntent.secondary:
        return _secondary;
      case UiIntent.success:
        return _success;
      case UiIntent.danger:
        return _danger;
      case UiIntent.warning:
        return _warning;
      case UiIntent.info:
        return _info;
      case UiIntent.neutral:
        return _neutral;
      case UiIntent.dark:
        return _dark;
    }
  }

  // Acentos BioEnlace
  static const _primaryBase = Color(0xFF0081A7);
  static const _secondaryBase = Color(0xFF00AFB9);
  static const _successBase = Color(0xFF28A745);
  static const _dangerBase = Color(0xFFF07167);
  static const _warningBase = Color(0xFFE08A3F); // ajustado para legibilidad sobre papel
  static const _infoBase = Color(0xFF00AFB9);

  static const _primary = IntentPalette(
    base: _primaryBase,
    onBase: PaperPalette.paper50,
    softBg: Color(0xFFE3EFF4),
    softFg: Color(0xFF005F7A),
    border: Color(0xFFB6D6E0),
  );

  static const _secondary = IntentPalette(
    base: _secondaryBase,
    onBase: PaperPalette.paper50,
    softBg: Color(0xFFE0F4F5),
    softFg: Color(0xFF00808A),
    border: Color(0xFFB7E1E4),
  );

  static const _success = IntentPalette(
    base: _successBase,
    onBase: PaperPalette.paper50,
    softBg: Color(0xFFE6F3E9),
    softFg: Color(0xFF1B6E2E),
    border: Color(0xFFC2DFC8),
  );

  static const _danger = IntentPalette(
    base: _dangerBase,
    onBase: PaperPalette.paper50,
    softBg: Color(0xFFFBE5E2),
    softFg: Color(0xFFB04A41),
    border: Color(0xFFF1C4BF),
  );

  static const _warning = IntentPalette(
    base: _warningBase,
    onBase: PaperPalette.paper900,
    softBg: Color(0xFFFAEAD4),
    softFg: Color(0xFF8A4F18),
    border: Color(0xFFEED2A8),
  );

  static const _info = IntentPalette(
    base: _infoBase,
    onBase: PaperPalette.paper50,
    softBg: Color(0xFFE0F4F5),
    softFg: Color(0xFF00808A),
    border: Color(0xFFB7E1E4),
  );

  static const _neutral = IntentPalette(
    base: PaperPalette.paper600,
    onBase: PaperPalette.paper50,
    softBg: PaperPalette.paper100,
    softFg: PaperPalette.paper700,
    border: PaperPalette.paper300,
  );

  static const _dark = IntentPalette(
    base: PaperPalette.paper900,
    onBase: PaperPalette.paper50,
    softBg: PaperPalette.paper150,
    softFg: PaperPalette.paper900,
    border: PaperPalette.paper400,
  );
}
