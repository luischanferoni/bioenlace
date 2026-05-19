import 'package:flutter/material.dart';

import 'border_width.dart';
import 'paper_palette.dart';
import 'ui_intent.dart';

/// Helper para construir [Border] con la nomenclatura Bootstrap-like.
///
/// Combina ancho ([BorderWidth]) + color ([PaperPalette] o [IntentPalette])
/// + lado (all / top / bottom / left / right / x / y).
abstract class BioBorder {
  const BioBorder._();

  static Border all(double width, Color color) {
    return Border.all(color: color, width: width);
  }

  static Border top(double width, Color color) {
    return Border(top: BorderSide(color: color, width: width));
  }

  static Border bottom(double width, Color color) {
    return Border(bottom: BorderSide(color: color, width: width));
  }

  static Border left(double width, Color color) {
    return Border(left: BorderSide(color: color, width: width));
  }

  static Border right(double width, Color color) {
    return Border(right: BorderSide(color: color, width: width));
  }

  static Border x(double width, Color color) {
    return Border(
      left: BorderSide(color: color, width: width),
      right: BorderSide(color: color, width: width),
    );
  }

  static Border y(double width, Color color) {
    return Border(
      top: BorderSide(color: color, width: width),
      bottom: BorderSide(color: color, width: width),
    );
  }

  /// Borde según [UiIntent] (`border-primary` en Bootstrap).
  static Border intent(UiIntent intent, {double width = BorderWidth.thin}) {
    final color = IntentPalette.of(intent).base;
    return Border.all(color: color, width: width);
  }

  /// Borde suave según [UiIntent] (color con menos saturación).
  static Border intentSoft(UiIntent intent, {double width = BorderWidth.thin}) {
    final color = IntentPalette.of(intent).border;
    return Border.all(color: color, width: width);
  }

  /// Default papel (cards, inputs).
  static Border get paperDefault =>
      Border.all(color: PaperPalette.paper300, width: BorderWidth.thin);

  /// Énfasis papel (AppBar bottom, BottomNav top, card seleccionada).
  static Border get paperEmphasis =>
      Border.all(color: PaperPalette.paper400, width: BorderWidth.medium);
}
