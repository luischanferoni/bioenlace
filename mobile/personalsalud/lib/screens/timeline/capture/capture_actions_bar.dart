import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

/// Barra inferior de la captura en revisión: guardar, editar texto o eliminar.
class CaptureActionsBar extends StatelessWidget {
  const CaptureActionsBar({super.key, required this.isSaving, required this.canConfirm, required this.onConfirm, required this.onEdit, this.onDelete});

  final bool isSaving;
  final bool canConfirm;
  final VoidCallback onConfirm;
  final VoidCallback onEdit;
  final VoidCallback? onDelete;

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
          BioButton.primary(
            label: isSaving ? 'Guardando…' : 'Guardar',
            icon: isSaving ? null : Icons.check,
            onPressed: canConfirm ? onConfirm : null,
          ),
          BioSpacing.gapH(BioSpacing.sm),
          Row(
            children: [
              Expanded(
                child: BioButton(
                  label: 'Editar texto',
                  intent: UiIntent.neutral,
                  variant: BioButtonVariant.soft,
                  onPressed: isSaving ? null : onEdit,
                ),
              ),
              BioSpacing.gapW(BioSpacing.sm),
              Expanded(
                child: BioButton(
                  label: 'Eliminar',
                  intent: UiIntent.danger,
                  variant: BioButtonVariant.soft,
                  onPressed: isSaving || onDelete == null
                      ? null
                      : onDelete,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
