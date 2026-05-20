import 'package:flutter/foundation.dart';

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

  static const _tiles = {'compact', 'medium', 'large'};
  static const _shapes = {'square', 'wide', 'auto'};

  static String _norm(String? v, Set<String> allowed, String fallback) {
    final s = (v ?? '').trim().toLowerCase();
    return allowed.contains(s) ? s : fallback;
  }

  static UiJsonListPresentationMetrics _metrics(String tile, String shape) {
    final (h, squareW, wideW, autoW, lines) = switch (tile) {
      'compact' => (52.0, 56.0, 96.0, 80.0, 2),
      'large' => (104.0, 104.0, 200.0, 168.0, 4),
      _ => (88.0, 88.0, 148.0, 120.0, 4), // medium
    };
    final w = switch (shape) {
      'square' => squareW,
      'auto' => autoW,
      _ => wideW,
    };
    return UiJsonListPresentationMetrics(
      rowHeight: h,
      tileWidth: w,
      maxLines: lines,
    );
  }
}
