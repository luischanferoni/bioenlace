import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';
import '../ui/bio_card.dart';
import 'encounter_journey_navigation.dart';

/// Hub «Preparar tu consulta»: lista fases pendientes leyendo solo `journey` del turno.
class PrepararConsultaHubScreen extends StatelessWidget {
  final Map<String, dynamic> turno;
  final String? authToken;
  final int? subjectPersonaId;
  final AbrirMotivosConsulta onOpenMotivos;

  const PrepararConsultaHubScreen({
    super.key,
    required this.turno,
    this.authToken,
    this.subjectPersonaId,
    required this.onOpenMotivos,
  });

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final pendientes = prepararConsultaFasesPendientes(turno);
    final fecha = turno['fecha']?.toString() ?? '';
    final hora = turno['hora']?.toString() ?? '';
    final horaCorta = hora.length >= 5 ? hora.substring(0, 5) : hora;
    final subtituloTurno = fecha.isNotEmpty
        ? (horaCorta.isNotEmpty ? '$fecha · $horaCorta' : fecha)
        : null;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Preparar tu consulta'),
      ),
      body: pendientes.isEmpty
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
                Text(
                  'Completá estos pasos antes de tu consulta.',
                  style: BioTypography.body,
                ),
                BioSpacing.gapH(BioSpacing.md),
                ...pendientes.map((entry) {
                  final phaseId = entry.key;
                  final phase = entry.value;
                  final label =
                      phase['label']?.toString() ?? 'Paso del recorrido';
                  final enabled = phase['enabled'] == true;
                  final hint = subtituloFaseJourney(phase);

                  return Padding(
                    padding: const EdgeInsets.only(bottom: BioSpacing.sm),
                    child: BioCard(
                      child: ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: Icon(
                          _iconoFase(phaseId),
                          color: enabled
                              ? tokens.intentPalette(UiIntent.primary).base
                              : tokens.textMuted,
                        ),
                        title: Text(label, style: BioTypography.title),
                        subtitle: hint != null
                            ? Text(
                                hint,
                                style: BioTypography.bodySm.copyWith(
                                  color: tokens.textMuted,
                                ),
                              )
                            : null,
                        trailing: enabled
                            ? Icon(
                                Icons.chevron_right,
                                color: tokens.textMuted,
                              )
                            : null,
                        onTap: enabled
                            ? () {
                                abrirFaseEncounterJourney(
                                  context: context,
                                  turno: turno,
                                  phaseId: phaseId,
                                  authToken: authToken,
                                  subjectPersonaId: subjectPersonaId,
                                  onOpenMotivos: onOpenMotivos,
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
      case kEncounterJourneyPhaseMotivos:
        return Icons.edit_note;
      case kEncounterJourneyPhaseAsistencia:
        return Icons.fact_check_outlined;
      default:
        return Icons.checklist_outlined;
    }
  }
}

void abrirPrepararConsultaHub({
  required BuildContext context,
  required Map<String, dynamic> turno,
  String? authToken,
  int? subjectPersonaId,
  required AbrirMotivosConsulta onOpenMotivos,
}) {
  Navigator.of(context).push(
    MaterialPageRoute(
      builder: (_) => PrepararConsultaHubScreen(
        turno: turno,
        authToken: authToken,
        subjectPersonaId: subjectPersonaId,
        onOpenMotivos: onOpenMotivos,
      ),
    ),
  );
}
