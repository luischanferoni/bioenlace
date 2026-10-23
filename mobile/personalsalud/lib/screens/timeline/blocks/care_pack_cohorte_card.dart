import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../../infra/app_texts.dart';
import '../../../services/historia_clinica_service.dart';

/// Cuestionario de preconsulta de la cohorte (care pack) respondido por el paciente.
class CarePackCohorteCard extends StatelessWidget {
  const CarePackCohorteCard({super.key, required this.cohorte});

  final CarePackCohorteStaff cohorte;

  @override
  Widget build(BuildContext context) {
    final assistance = cohorte.assistance;
    final profile = cohorte.cohortProfile;
    final profileParts = <String>[];
    if (profile != null) {
      for (final key in [
        'life_stage',
        'sexo',
        'motive_cluster',
        'jurisdiction',
      ]) {
        final val = profile[key]?.toString().trim();
        if (val != null && val.isNotEmpty) {
          profileParts.add(val);
        }
      }
    }

    return BioCard.intent(
      intent: UiIntent.info,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(AppTexts.timelinePreconsult, style: BioTypography.title),
          if (cohorte.cohortKeyShort != null &&
              cohorte.cohortKeyShort!.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              'Cohorte ${cohorte.cohortKeyShort}',
              style: BioTypography.caption.copyWith(
                color: context.bio.textMuted,
              ),
            ),
          ],
          if (profileParts.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(profileParts.join(' · '), style: BioTypography.bodySm),
          ],
          BioSpacing.gapH(BioSpacing.md),
          if (assistance.notesForStaff != null &&
              assistance.notesForStaff!.trim().isNotEmpty) ...[
            Text(AppTexts.timelineGuidance, style: BioTypography.overline),
            BioSpacing.gapH(BioSpacing.xs),
            Text(assistance.notesForStaff!.trim(), style: BioTypography.bodySm),
            BioSpacing.gapH(BioSpacing.md),
          ],
          if (assistance.answers.isEmpty)
            Text(
              assistance.status == 'submitted'
                  ? 'Respuestas registradas sin detalle disponible.'
                  : 'El paciente aún no completó el cuestionario.',
              style: BioTypography.bodySm,
            )
          else ...[
            Text(AppTexts.timelinePatientAnswers, style: BioTypography.overline),
            BioSpacing.gapH(BioSpacing.sm),
            ...assistance.answers.map(
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
          if (assistance.deltaRequested) ...[
            BioSpacing.gapH(BioSpacing.sm),
            BioBadge.warning('Requiere adaptación del pack'),
          ],
        ],
      ),
    );
  }
}
