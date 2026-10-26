import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../../infra/app_texts.dart';

/// Barra inferior de la captura en revisión: guardar o volver a las notas.
class CaptureActionsBar extends StatelessWidget {
  const CaptureActionsBar({super.key, required this.isSaving, required this.canConfirm, required this.onConfirm, required this.onBackToNotes});

  final bool isSaving;
  final bool canConfirm;
  final VoidCallback onConfirm;
  final VoidCallback onBackToNotes;

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
          if (!canConfirm && !isSaving) ...[
            BioAlert.warning(
              message: 'Faltan datos requeridos para guardar. Completá las categorías marcadas.',
            ),
            BioSpacing.gapH(BioSpacing.sm),
          ],
          BioButton.primary(
            label: isSaving ? 'Guardando…' : 'Guardar',
            icon: isSaving ? null : Icons.check,
            onPressed: canConfirm ? onConfirm : null,
          ),
          BioSpacing.gapH(BioSpacing.sm),
          BioButton(
            label: AppTexts.noteBackToNotes,
            intent: UiIntent.neutral,
            variant: BioButtonVariant.soft,
            onPressed: isSaving ? null : onBackToNotes,
          ),
        ],
      ),
    );
  }
}
