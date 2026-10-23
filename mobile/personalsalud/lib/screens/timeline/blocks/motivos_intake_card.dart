import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../../infra/app_texts.dart';
import '../../../services/historia_clinica_service.dart';

/// Preguntas previas respondidas por el paciente (intake) para el profesional.
class MotivosIntakeCard extends StatelessWidget {
  const MotivosIntakeCard({super.key, required this.intake});

  final MotivosIntakeStaff intake;

  @override
  Widget build(BuildContext context) {
    return BioCard.intent(
      intent: UiIntent.info,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(AppTexts.timelineIntakeTitle, style: BioTypography.title),
          if (intake.title != null && intake.title!.trim().isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              intake.title!.trim(),
              style: BioTypography.caption.copyWith(color: context.bio.textMuted),
            ),
          ],
          BioSpacing.gapH(BioSpacing.md),
          if (intake.notesForStaff != null && intake.notesForStaff!.trim().isNotEmpty) ...[
            Text(AppTexts.timelineGuidance, style: BioTypography.overline),
            BioSpacing.gapH(BioSpacing.xs),
            Text(intake.notesForStaff!.trim(), style: BioTypography.bodySm),
            BioSpacing.gapH(BioSpacing.md),
          ],
          if (intake.answers.isEmpty)
            Text(
              intake.status == 'submitted'
                  ? 'Respuestas registradas sin detalle disponible.'
                  : 'El paciente aún no completó las preguntas previas.',
              style: BioTypography.bodySm,
            )
          else ...[
            Text(AppTexts.timelinePatientAnswers, style: BioTypography.overline),
            BioSpacing.gapH(BioSpacing.sm),
            ...intake.answers.map(
              (a) => Padding(
                padding: const EdgeInsets.only(bottom: BioSpacing.sm),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(a.question, style: BioTypography.bodySm),
                    BioSpacing.gapH(BioSpacing.xs),
                    Text(a.answer, style: BioTypography.body),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
