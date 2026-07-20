import 'package:flutter/material.dart';

import '../theme/tokens/bio_spacing.dart';
import '../theme/tokens/border_width.dart';

/// Presets de tamaño/forma para tiles de bloques `kind: list` (contrato `presentation`).
@immutable
class UiJsonListPresentationMetrics {
  const UiJsonListPresentationMetrics({
    required this.rowHeight,
    required this.tileWidth,
    required this.maxLines,
  });

  final double rowHeight;
  final double tileWidth;
  final int maxLines;

  static UiJsonListPresentationMetrics fromBlock(Map<String, dynamic> block) {
    final raw = block['presentation'];
    final p = raw is Map ? Map<String, dynamic>.from(raw) : const <String, dynamic>{};
    final tile = _norm(p['tile']?.toString(), _tiles, 'medium');
    final shape = _norm(p['shape']?.toString(), _shapes, 'wide');
    return _metrics(tile, shape);
  }

  /// Altura de fila que cubre el ítem más alto (wrap de nombre ± subtítulo).
  static double resolveRowHeight({
    required UiJsonListPresentationMetrics pres,
    required List<dynamic> items,
    required TextStyle nameStyle,
    required TextStyle subtitleStyle,
    required TextScaler textScaler,
  }) {
    final innerWidth = (pres.tileWidth - (BioSpacing.sm * 2) - (BorderWidth.medium * 2))
        .clamp(48.0, pres.tileWidth);
    var maxTileHeight = pres.rowHeight;

    for (final raw in items) {
      if (raw is! Map) continue;
      final item = Map<String, dynamic>.from(raw);
      final name = (item['name'] ?? item['label'] ?? item['id'] ?? '').toString().trim();
      if (name.isEmpty) continue;
      final subtitle = (item['subtitle']?.toString() ?? '').trim();

      final nameLines = subtitle.isEmpty ? pres.maxLines : 2;
      final subtitleLines =
          subtitle.isEmpty ? 0 : (pres.maxLines - nameLines).clamp(1, 2);

      var contentHeight = 0.0;
      contentHeight += _measureTextBlock(
        text: name,
        style: nameStyle,
        maxLines: nameLines,
        maxWidth: innerWidth,
        textScaler: textScaler,
      );
      if (subtitle.isNotEmpty && subtitleLines > 0) {
        contentHeight += 2;
        contentHeight += _measureTextBlock(
          text: subtitle,
          style: subtitleStyle,
          maxLines: subtitleLines,
          maxWidth: innerWidth,
          textScaler: textScaler,
        );
      }

      final tileHeight =
          contentHeight + (BioSpacing.sm * 2) + (BorderWidth.medium * 2) + 2;
      if (tileHeight > maxTileHeight) {
        maxTileHeight = tileHeight;
      }
    }

    return maxTileHeight.ceilToDouble();
  }

  static double _measureTextBlock({
    required String text,
    required TextStyle style,
    required int maxLines,
    required double maxWidth,
    required TextScaler textScaler,
  }) {
    if (text.isEmpty || maxLines <= 0) return 0;
    final painter = TextPainter(
      text: TextSpan(text: text, style: style),
      textDirection: TextDirection.ltr,
      textScaler: textScaler,
      maxLines: maxLines,
    )..layout(maxWidth: maxWidth);
    return painter.height;
  }

  static const _tiles = {'compact', 'medium', 'large'};
  static const _shapes = {'square', 'wide', 'auto'};

  static String _norm(String? v, Set<String> allowed, String fallback) {
    final s = (v ?? '').trim().toLowerCase();
    return allowed.contains(s) ? s : fallback;
  }

  /// Altura mínima de fila para que el texto haga wrap hasta [maxLines] sin overflow.
  static double _rowHeightFor(int maxLines, double presetHeight) {
    // bodySmall + padding vertical del tile + borde (~18px de “chrome”).
    const linePx = 18.0;
    const chromePx = 18.0;
    final needed = maxLines * linePx + chromePx;
    return needed > presetHeight ? needed : presetHeight;
  }

  static UiJsonListPresentationMetrics _metrics(String tile, String shape) {
    final (h, squareW, wideW, autoW, lines) = switch (tile) {
      'compact' => (52.0, 56.0, 96.0, 80.0, 2),
      'large' => (104.0, 104.0, 200.0, 168.0, 4),
      // medium: wide −10% ancho / −20% alto; square −20% ancho y alto (base 88×88 / 148 wide)
      _ => (70.0, 70.0, 133.0, 108.0, 4),
    };
    final w = switch (shape) {
      'square' => squareW,
      'auto' => autoW,
      _ => wideW,
    };
    // large+wide/auto: preset visual compacto, pero respetar wrap hasta maxLines.
    final presetRowHeight = tile == 'large' && shape != 'square' ? 70.0 : h;
    final rowHeight = _rowHeightFor(lines, presetRowHeight);
    return UiJsonListPresentationMetrics(
      rowHeight: rowHeight,
      tileWidth: w,
      maxLines: lines,
    );
  }
}
