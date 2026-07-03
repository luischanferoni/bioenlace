import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';
import '../ui/bio_card.dart';
import 'encounter_journey_navigation.dart';

/// Hub de fases del recorrido encounter (pre o post consulta), leyendo solo `journey`.
class EncounterJourneyHubScreen extends StatelessWidget {
  final String title;
  final String intro;
  final Map<String, dynamic> turno;
  final List<String> phaseIds;
  final String? authToken;
  final int? subjectPersonaId;
  final AbrirMotivosConsulta onOpenMotivos;
  final String appClient;

  const EncounterJourneyHubScreen({
    super.key,
    required this.title,
    required this.intro,
    required this.turno,
    required this.phaseIds,
    this.authToken,
    this.subjectPersonaId,
    required this.onOpenMotivos,
    this.appClient = 'paciente-flutter',
  });

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final entries = journeyHubEntries(turno, phaseIds);
    final fecha = turno['fecha']?.toString() ?? '';
    final hora = turno['hora']?.toString() ?? '';
    final horaCorta = hora.length >= 5 ? hora.substring(0, 5) : hora;
    final subtituloTurno = fecha.isNotEmpty
        ? (horaCorta.isNotEmpty ? '$fecha · $horaCorta' : fecha)
        : null;

    return Scaffold(
      appBar: AppBar(
        title: Text(title),
      ),
      body: entries.isEmpty
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(BioSpacing.lg),
                child: Text(
                  'No hay acciones pendientes para este turno.',
                  textAlign: TextAlign.center,
                  style: BioTypography.body.copyWith(color: tokens.textMuted),
                ),
              ),
            )
          : ListView(
              padding: const EdgeInsets.all(BioSpacing.md),
              children: [
                if (subtituloTurno != null) ...[
                  Text(
                    'Turno del $subtituloTurno',
                    style: BioTypography.bodySm.copyWith(
                      color: tokens.textMuted,
                    ),
                  ),
                  BioSpacing.gapH(BioSpacing.md),
                ],
                Text(intro, style: BioTypography.body),
                BioSpacing.gapH(BioSpacing.md),
                ...entries.map((entry) {
                  return Padding(
                    padding: const EdgeInsets.only(bottom: BioSpacing.sm),
                    child: BioCard(
                      child: ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: Icon(
                          _iconoFase(entry.phaseId),
                          color: entry.enabled
                              ? tokens.intentPalette(UiIntent.primary).base
                              : tokens.textMuted,
                        ),
                        title: Text(entry.label, style: BioTypography.title),
                        subtitle: entry.subtitle != null
                            ? Text(
                                entry.subtitle!,
                                style: BioTypography.bodySm.copyWith(
                                  color: tokens.textMuted,
                                ),
                              )
                            : null,
                        trailing: entry.enabled
                            ? Icon(
                                Icons.chevron_right,
                                color: tokens.textMuted,
                              )
                            : null,
                        onTap: entry.enabled
                            ? () {
                                abrirJourneyHubEntry(
                                  context: context,
                                  turno: turno,
                                  entry: entry,
                                  authToken: authToken,
                                  subjectPersonaId: subjectPersonaId,
                                  onOpenMotivos: onOpenMotivos,
                                  appClient: appClient,
                                );
                              }
                            : null,
                      ),
                    ),
                  );
                }),
              ],
            ),
    );
  }

  IconData _iconoFase(String phaseId) {
    switch (phaseId) {
      case kEncounterJourneyPhaseMotivosIntake:
        return Icons.quiz_outlined;
      case kEncounterJourneyPhaseMotivos:
        return Icons.edit_note;
      case kEncounterJourneyPhaseAsistencia:
        return Icons.fact_check_outlined;
      case kEncounterJourneyPhasePostConsulta:
        return Icons.health_and_safety_outlined;
      default:
        return Icons.checklist_outlined;
    }
  }
}

void abrirEncounterJourneyHub({
  required BuildContext context,
  required String title,
  required String intro,
  required Map<String, dynamic> turno,
  required List<String> phaseIds,
  String? authToken,
  int? subjectPersonaId,
  required AbrirMotivosConsulta onOpenMotivos,
  String appClient = 'paciente-flutter',
}) {
  Navigator.of(context).push(
    MaterialPageRoute(
      builder: (_) => EncounterJourneyHubScreen(
        title: title,
        intro: intro,
        turno: turno,
        phaseIds: phaseIds,
        authToken: authToken,
        subjectPersonaId: subjectPersonaId,
        onOpenMotivos: onOpenMotivos,
        appClient: appClient,
      ),
    ),
  );
}

void abrirPrepararConsultaHub({
  required BuildContext context,
  required Map<String, dynamic> turno,
  String? authToken,
  int? subjectPersonaId,
  required AbrirMotivosConsulta onOpenMotivos,
  String appClient = 'paciente-flutter',
}) {
  abrirEncounterJourneyHub(
    context: context,
    title: 'Preparar tu consulta',
    intro: 'Completá estos pasos antes de tu consulta.',
    turno: turno,
    phaseIds: kEncounterJourneyPreTurnoPhases,
    authToken: authToken,
    subjectPersonaId: subjectPersonaId,
    onOpenMotivos: onOpenMotivos,
    appClient: appClient,
  );
}

void abrirSeguimientoPostConsultaHub({
  required BuildContext context,
  required Map<String, dynamic> turno,
  String? authToken,
  int? subjectPersonaId,
  required AbrirMotivosConsulta onOpenMotivos,
  String appClient = 'paciente-flutter',
}) {
  abrirEncounterJourneyHub(
    context: context,
    title: 'Seguimiento post-consulta',
    intro: 'Contanos cómo seguís después de tu atención.',
    turno: turno,
    phaseIds: kEncounterJourneyPostTurnoPhases,
    authToken: authToken,
    subjectPersonaId: subjectPersonaId,
    onOpenMotivos: onOpenMotivos,
    appClient: appClient,
  );
}
