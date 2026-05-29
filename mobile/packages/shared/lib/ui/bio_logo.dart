import 'package:flutter/material.dart';

/// Logo horizontal de Bioenlace (`assets/branding/logo.png`).
class BioLogo extends StatelessWidget {
  const BioLogo({
    super.key,
    this.height = 48,
    this.semanticLabel = 'Bioenlace',
  });

  final double height;
  final String? semanticLabel;

  static const String _assetPath = 'assets/branding/logo.png';

  @override
  Widget build(BuildContext context) {
    return Image.asset(
      _assetPath,
      package: 'shared',
      height: height,
      fit: BoxFit.contain,
      semanticLabel: semanticLabel,
      filterQuality: FilterQuality.high,
    );
  }
}
