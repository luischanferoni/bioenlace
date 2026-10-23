import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../../infra/app_texts.dart';
import '../../../models/pending_encounter_capture.dart';
import 'draft_notes.dart';
import 'pending_captures_panel.dart';

/// Lista de notas del borrador: solo se escuchan (audio) y se borran; no se editan.
class DraftNotesPanel extends StatelessWidget {
  const DraftNotesPanel({
    super.key,
    required this.notes,
    required this.busy,
    required this.playingId,
    required this.onTogglePlay,
    required this.onDelete,
    required this.onRetry,
  });

  final List<PendingEncounterCapture> notes;
  final bool busy;
  final String? playingId;
  final ValueChanged<PendingEncounterCapture> onTogglePlay;
  final ValueChanged<PendingEncounterCapture> onDelete;
  final ValueChanged<PendingEncounterCapture> onRetry;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (var i = 0; i < notes.length; i++) ...[
          if (i > 0) BioSpacing.gapH(BioSpacing.sm),
          _tile(context, i, notes[i]),
        ],
      ],
    );
  }

  Widget _tile(BuildContext context, int index, PendingEncounterCapture note) {
    final ready = isNoteReady(note);
    final text = note.texto.trim();
    final hasText = text.isNotEmpty && !text.startsWith('(audio');
    final error = note.lastError?.trim() ?? '';
    final playing = playingId == note.id;

    return Container(
      padding: const EdgeInsets.all(BioSpacing.md),
      decoration: BoxDecoration(
        color: PaperPalette.paper100,
        borderRadius: BorderRadius.circular(BioRadius.sm),
        border: Border.all(
          color: context.bio.paperBorderDefault,
          width: BorderWidth.thin,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                note.hasAudio ? Icons.mic_none : Icons.notes,
                size: 18,
                color: context.bio.textMuted,
              ),
              BioSpacing.gapW(BioSpacing.sm),
              Expanded(
                child: Text(
                  '${AppTexts.noteOrdinal(index + 1)} · '
                  '${note.hasAudio ? AppTexts.noteAudio : AppTexts.noteText}',
                  style: BioTypography.title,
                ),
              ),
              if (!ready)
                BioBadge(
                  label: pendingStatusLabel(note),
                  intent: UiIntent.warning,
                ),
            ],
          ),
          BioSpacing.gapH(BioSpacing.xs),
          Text(
            hasText ? text : AppTexts.noteWaitingText,
            style: BioTypography.body.copyWith(
              color: hasText ? null : context.bio.textMuted,
            ),
          ),
          if (!ready && error.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              userFriendlyErrorMessage(error),
              style: BioTypography.caption.copyWith(
                color: IntentPalette.of(UiIntent.warning).softFg,
              ),
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
            ),
          ],
          BioSpacing.gapH(BioSpacing.sm),
          Row(
            children: [
              if (note.hasAudio) ...[
                BioButton(
                  label: playing ? AppTexts.noteStop : AppTexts.notePlay,
                  icon: playing ? Icons.stop : Icons.play_arrow,
                  intent: UiIntent.primary,
                  variant: BioButtonVariant.soft,
                  onPressed: () => onTogglePlay(note),
                ),
                BioSpacing.gapW(BioSpacing.sm),
              ],
              if (!ready) ...[
                BioButton(
                  label: AppTexts.noteRetry,
                  icon: Icons.refresh,
                  intent: UiIntent.neutral,
                  variant: BioButtonVariant.soft,
                  onPressed: busy ? null : () => onRetry(note),
                ),
                BioSpacing.gapW(BioSpacing.sm),
              ],
              const Spacer(),
              BioButton(
                label: AppTexts.noteDelete,
                icon: Icons.delete_outline,
                intent: UiIntent.danger,
                variant: BioButtonVariant.soft,
                onPressed: busy ? null : () => onDelete(note),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
