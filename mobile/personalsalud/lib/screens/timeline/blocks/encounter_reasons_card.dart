import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../../infra/app_texts.dart';
import '../../../services/historia_clinica_service.dart';

/// Motivos de consulta del paciente con resumen, imágenes adjuntas y sugerencias clínicas.
class EncounterReasonsCard extends StatelessWidget {
  const EncounterReasonsCard({super.key, required this.hc, this.authToken});

  final HistoriaClinicaResponse hc;
  final String? authToken;

  @override
  Widget build(BuildContext context) {
    final mp = hc.patientEncounterReasons;
    final resumen =
        (mp.resumen ??
                mp.resumenIa ??
                hc.informacionMedica.encounterReasonSummary ??
                '')
            .trim();
    final hayResumen = resumen.isNotEmpty;
    final sugerencias = mp.sugerenciasClinicas;
    final turnoCtx = mp.turno;
    final subtituloTurno = turnoCtx != null && turnoCtx.etiquetaCorta.isNotEmpty
        ? 'Turno ${turnoCtx.etiquetaCorta}'
        : (mp.turnoId != null ? 'Turno #${mp.turnoId}' : null);

    return BioCard.intent(
      intent: UiIntent.success,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(AppTexts.timelineReasonsTitle, style: BioTypography.title),
          if (subtituloTurno != null) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              subtituloTurno,
              style: BioTypography.caption.copyWith(
                color: context.bio.textMuted,
              ),
            ),
          ],
          BioSpacing.gapH(BioSpacing.md),
          if (hayResumen) ...[
            Text(AppTexts.timelineSummary, style: BioTypography.overline),
            BioSpacing.gapH(BioSpacing.xs),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: _buildResumenConImagenes(resumen, mp.imagenesAdjuntas),
            ),
          ] else if (mp.resumenPendiente)
            Text(
              'Generando resumen… Actualizá en unos segundos.',
              style: BioTypography.bodySm,
            )
          else
            Text(
              'Sin motivos registrados para esta consulta.',
              style: BioTypography.bodySm,
            ),
          if (sugerencias != null && sugerencias.tieneContenido) ...[
            BioSpacing.gapH(BioSpacing.lg),
            Text(
              'Orientación según motivos del paciente',
              style: BioTypography.overline,
            ),
            if (sugerencias.diagnosticos.isNotEmpty) ...[
              BioSpacing.gapH(BioSpacing.sm),
              Text(AppTexts.timelineDiagnoses, style: BioTypography.bodySm),
              BioSpacing.gapH(BioSpacing.xs),
              ...sugerencias.diagnosticos.map(
                (d) => Padding(
                  padding: const EdgeInsets.only(bottom: BioSpacing.xs),
                  child: Text(
                    '• ${d.termino}${d.justificacion != null && d.justificacion!.isNotEmpty ? ' — ${d.justificacion}' : ''}',
                    style: BioTypography.bodySm,
                  ),
                ),
              ),
            ],
            if (sugerencias.practicas.isNotEmpty) ...[
              BioSpacing.gapH(BioSpacing.sm),
              Text(AppTexts.timelinePractices, style: BioTypography.bodySm),
              BioSpacing.gapH(BioSpacing.xs),
              ...sugerencias.practicas.map(
                (p) => Padding(
                  padding: const EdgeInsets.only(bottom: BioSpacing.xs),
                  child: Text(
                    '• ${p.termino}${p.justificacion != null && p.justificacion!.isNotEmpty ? ' — ${p.justificacion}' : ''}',
                    style: BioTypography.bodySm,
                  ),
                ),
              ),
            ],
          ],
        ],
      ),
    );
  }

  List<Widget> _buildResumenConImagenes(String resumen, List<MotivoImagenAdjunta> imagenes) {
    final byRef = {for (final i in imagenes) i.ref: i.url};
    final pattern = RegExp(r'\[(imagen\d+)\]');
    final widgets = <Widget>[];
    var last = 0;

    for (final match in pattern.allMatches(resumen)) {
      if (match.start > last) {
        widgets.add(
          Text(resumen.substring(last, match.start), style: BioTypography.body),
        );
      }
      final ref = match.group(1)!;
      final url = byRef[ref];
      if (url != null && url.isNotEmpty) {
        widgets.add(
          Padding(
            padding: const EdgeInsets.symmetric(vertical: BioSpacing.xs),
            child: ChatMediaImage(
              source: url,
              bearerToken: authToken,
              width: double.infinity,
              height: 160,
              fit: BoxFit.contain,
            ),
          ),
        );
      } else {
        widgets.add(Text('[$ref]', style: BioTypography.body));
      }
      last = match.end;
    }
    if (last < resumen.length) {
      widgets.add(Text(resumen.substring(last), style: BioTypography.body));
    }
    if (widgets.isEmpty) {
      widgets.add(Text(resumen, style: BioTypography.body));
    }

    return widgets;
  }
}
