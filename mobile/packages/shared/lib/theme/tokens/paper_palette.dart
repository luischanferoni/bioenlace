import 'package:flutter/material.dart';

/// Escala monocroma cálida ("papel"): blanco hueso → negro suave.
///
/// Roles principales:
/// - `paper25`: fondo de pantalla (más cercano al blanco).
/// - `paper50`: superficie principal (AppBar, bottom nav, cards).
/// - `paper100`: surface secundaria (cards "hundidas", soft bg).
/// - `paper150`: divider interno ultra suave.
/// - `paper200`: divider default (líneas de lista, separadores).
/// - `paper300`: border default de cards / inputs / badges outline.
/// - `paper400`: border de énfasis (AppBar bottom, BottomNav top, card seleccionada).
/// - `paper500`: texto secundario.
/// - `paper600`: texto cuerpo.
/// - `paper700`: títulos.
/// - `paper900`: texto enfático / base de sombras (nunca `#000000`).
@immutable
class PaperPalette {
  const PaperPalette();

  static const Color paper25 = Color(0xFFFDFCFB);
  static const Color paper50 = Color(0xFFFAF8F3);
  static const Color paper100 = Color(0xFFF2EFE8);
  static const Color paper150 = Color(0xFFECE8E0);
  static const Color paper200 = Color(0xFFE7E3DA);
  static const Color paper300 = Color(0xFFD5D0C6);
  static const Color paper400 = Color(0xFFA8A29A);
  static const Color paper500 = Color(0xFF6E6A63);
  static const Color paper600 = Color(0xFF4A4742);
  static const Color paper700 = Color(0xFF2E2C28);
  static const Color paper900 = Color(0xFF1A1916);
}
