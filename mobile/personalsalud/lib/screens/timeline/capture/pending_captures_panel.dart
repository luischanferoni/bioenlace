import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../../infra/app_texts.dart';
import '../../../models/pending_encounter_capture.dart';

String pendingPrimaryActionLabel(PendingEncounterCapture item) {
  switch (item.status) {
    case PendingEncounterCaptureStatus.draft:
      return 'Generar Notas Clínicas';
    case PendingEncounterCaptureStatus.pendingUpload:
      return 'Subir';
    case PendingEncounterCaptureStatus.pendingStt:
      return 'Transcribir';
    case PendingEncounterCaptureStatus.pendingAnalyze:
      return 'Generar Notas Clínicas';
    case PendingEncounterCaptureStatus.pendingSave:
      return 'Revisar';
    case PendingEncounterCaptureStatus.failedSave:
      return 'Guardar';
  }
}

String pendingStatusLabel(PendingEncounterCapture item) {
  final serverLabel = item.serverStageLabel?.trim();
  if (serverLabel != null && serverLabel.isNotEmpty) return serverLabel;
  switch (item.status) {
    case PendingEncounterCaptureStatus.draft:
      return 'Borrador';
    case PendingEncounterCaptureStatus.pendingUpload:
      return 'Sin subir';
    case PendingEncounterCaptureStatus.pendingStt:
      return 'Sin transcribir';
    case PendingEncounterCaptureStatus.pendingAnalyze:
      return 'Sin analizar';
    case PendingEncounterCaptureStatus.pendingSave:
      return 'Para revisar';
    case PendingEncounterCaptureStatus.failedSave:
      return 'No guardado';
  }
}

UiIntent pendingStatusIntent(String? serverIntent) {
  return switch (serverIntent) {
    'primary' => UiIntent.primary,
    'secondary' => UiIntent.secondary,
    'success' => UiIntent.success,
    'danger' => UiIntent.danger,
    'warning' => UiIntent.warning,
    'info' => UiIntent.info,
    'neutral' => UiIntent.neutral,
    'dark' => UiIntent.dark,
    _ => UiIntent.neutral,
  };
}

/// Cola local de notas pendientes de subir, transcribir, analizar o guardar.
class PendingCapturesPanel extends StatelessWidget {
  const PendingCapturesPanel({super.key, required this.items, required this.busy, required this.onPrimaryAction, required this.onDelete});

  final List<PendingEncounterCapture> items;
  final bool busy;
  final ValueChanged<PendingEncounterCapture> onPrimaryAction;
  final ValueChanged<PendingEncounterCapture> onDelete;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          'Notas pendientes en el teléfono',
          style: BioTypography.overline.copyWith(color: context.bio.textMuted),
        ),
        BioSpacing.gapH(BioSpacing.sm),
        for (final item in items) ...[
          _row(context, item),
          if (item != items.last) BioSpacing.gapH(BioSpacing.sm),
        ],
      ],
    );
  }

  Widget _row(BuildContext context, PendingEncounterCapture item) {
    final previewRaw = item.texto.trim();
    final preview = previewRaw.isEmpty || previewRaw.startsWith('(audio')
        ? (item.hasAudio ? 'Audio' : 'Sin texto')
        : (previewRaw.length > 90
              ? '${previewRaw.substring(0, 90)}…'
              : previewRaw);
    final primary = pendingPrimaryActionLabel(item);
    final rawError = item.lastError?.trim() ?? '';
    final friendlyError =
        rawError.isNotEmpty &&
            item.status != PendingEncounterCaptureStatus.pendingSave
        ? userFriendlyErrorMessage(rawError)
        : null;
    final warning = IntentPalette.of(UiIntent.warning);

    // Cola local: fondo hundido + borde suave, no BioCard.intent (evita
    // confundirse con condición / signos / motivos).
    return Container(
      padding: const EdgeInsets.all(BioSpacing.md),
      decoration: BoxDecoration(
        color: PaperPalette.paper100,
        borderRadius: BorderRadius.circular(BioRadius.sm),
        border: Border.all(color: warning.border, width: BorderWidth.thin),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.cloud_off_outlined, size: 18, color: warning.softFg),
              BioSpacing.gapW(BioSpacing.sm),
              Expanded(
                child: Text(
                  'Nota pendiente',
                  style: BioTypography.title.copyWith(color: warning.softFg),
                ),
              ),
              BioBadge(
                label: pendingStatusLabel(item),
                intent: item.serverStageIntent == null
                    ? UiIntent.warning
                    : pendingStatusIntent(item.serverStageIntent),
              ),
            ],
          ),
          BioSpacing.gapH(BioSpacing.xs),
          Text(
            preview,
            style: BioTypography.caption.copyWith(color: context.bio.textMuted),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          if (friendlyError != null) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              friendlyError,
              style: BioTypography.caption.copyWith(color: warning.softFg),
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
            ),
          ],
          BioSpacing.gapH(BioSpacing.sm),
          Row(
            children: [
              Expanded(
                child: BioButton(
                  label: primary,
                  intent: UiIntent.primary,
                  variant: BioButtonVariant.soft,
                  onPressed: busy
                      ? null
                      : () => onPrimaryAction(item),
                ),
              ),
              BioSpacing.gapW(BioSpacing.sm),
              BioButton(
                label: 'Eliminar',
                intent: UiIntent.danger,
                variant: BioButtonVariant.soft,
                onPressed: busy
                    ? null
                    : () => onDelete(item),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Indicador de análisis de la nota en curso.
class AnalyzingCaptureCard extends StatelessWidget {
  const AnalyzingCaptureCard({super.key});

  @override
  Widget build(BuildContext context) {
  return BioCard.intent(
    intent: UiIntent.info,
    child: Row(
      children: [
        const SizedBox(
          width: 22,
          height: 22,
          child: CircularProgressIndicator(strokeWidth: 2),
        ),
        BioSpacing.gapW(BioSpacing.md),
        Expanded(child: Text(AppTexts.captureAnalyzing, style: BioTypography.body)),
      ],
    ),
  );
  }
}
