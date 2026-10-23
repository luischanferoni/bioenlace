import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../../infra/app_texts.dart';

/// Botón único para analizar todas las notas del borrador.
class CaptureAnalyzeBar extends StatelessWidget {
  const CaptureAnalyzeBar({
    super.key,
    required this.noteCount,
    required this.enabled,
    required this.analyzing,
    required this.onAnalyze,
    this.blockedHint,
  });

  final int noteCount;
  final bool enabled;
  final bool analyzing;
  final VoidCallback onAnalyze;
  final String? blockedHint;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(
        BioSpacing.md,
        BioSpacing.sm,
        BioSpacing.md,
        BioSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: context.bio.paperSurface,
        border: Border(top: BorderSide(color: context.bio.paperBorderDefault)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (blockedHint != null)
            Padding(
              padding: const EdgeInsets.only(bottom: BioSpacing.xs),
              child: Text(
                blockedHint!,
                style: BioTypography.caption.copyWith(
                  color: context.bio.textMuted,
                ),
              ),
            ),
          BioButton.primary(
            label: analyzing
                ? AppTexts.captureAnalyzing
                : AppTexts.noteAnalyzeButton(noteCount),
            icon: analyzing ? null : Icons.fact_check_outlined,
            onPressed: enabled && !analyzing ? onAnalyze : null,
          ),
        ],
      ),
    );
  }
}
