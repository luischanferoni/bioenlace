import 'package:flutter/material.dart';

import 'paper_palette.dart';

/// Intenciones semánticas (Bootstrap-like): primary, secondary, success, danger,
/// warning, info, neutral, dark. Cada componente consume `IntentPalette.of(intent)`
/// para tomar `base`, `onBase`, `softBg`, `softFg`, `border`.
///
/// Colores base alineados con la web:
/// `web/frontend/web/custom-template/css/bootstrap-custom.css` (`--bioenlace-*`).
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

/// Paleta resuelta por [UiIntent]. Acentos = Bootstrap Bioenlace (web);
/// [neutral] y [dark] siguen en escala "papel".
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

  /// Fondo "soft" (~10% del base sobre blanco, como `--bs-*-bg-subtle` en web).
  final Color softBg;

  /// Texto/ícono sobre [softBg] (hover web `--bioenlace-*-hover`).
  final Color softFg;

  /// Color del borde (outline / softBorder, ~20% como `--bs-*-border-subtle`).
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

  // --bioenlace-* (bootstrap-custom.css)
  static const _onLightBase = Color(0xFFFFFFFF);
  static const _primaryBase = Color(0xFF54A0FF);
  static const _secondaryBase = Color(0xFF6C757D);
  static const _successBase = Color(0xFF10AC84);
  static const _dangerBase = Color(0xFFEE5253);
  static const _warningBase = Color(0xFFFF9800);
  static const _infoBase = Color(0xFF0ABDE3);

  static const _primary = IntentPalette(
    base: _primaryBase,
    onBase: _onLightBase,
    softBg: Color(0xFFECF5FF),
    softFg: Color(0xFF0876FF),
    border: Color(0xFFB3D4FF),
  );

  static const _secondary = IntentPalette(
    base: _secondaryBase,
    onBase: _onLightBase,
    softBg: Color(0xFFF4F7FA),
    softFg: Color(0xFF5C636A),
    border: Color(0xFFD3D7DB),
  );

  static const _success = IntentPalette(
    base: _successBase,
    onBase: _onLightBase,
    softBg: Color(0xFFE8F7F2),
    softFg: Color(0xFF0D9670),
    border: Color(0xFFB8E8DC),
  );

  static const _danger = IntentPalette(
    base: _dangerBase,
    onBase: _onLightBase,
    softBg: Color(0xFFFDECEC),
    softFg: Color(0xFFE63C3C),
    border: Color(0xFFF5C4C4),
  );

  static const _warning = IntentPalette(
    base: _warningBase,
    onBase: PaperPalette.paper900,
    softBg: Color(0xFFFFF4E5),
    softFg: Color(0xFFE68900),
    border: Color(0xFFFFE0B2),
  );

  static const _info = IntentPalette(
    base: _infoBase,
    onBase: _onLightBase,
    softBg: Color(0xFFE6F9FD),
    softFg: Color(0xFF0998C4),
    border: Color(0xFFB3EBF5),
  );

  static const _neutral = IntentPalette(
    base: PaperPalette.paper600,
    onBase: PaperPalette.paper50,
    softBg: PaperPalette.paper100,
    softFg: PaperPalette.paper700,
    border: PaperPalette.paper300,
  );

  static const _dark = IntentPalette(
    base: Color(0xFF222222),
    onBase: PaperPalette.paper50,
    softBg: PaperPalette.paper150,
    softFg: PaperPalette.paper900,
    border: PaperPalette.paper400,
  );
}
