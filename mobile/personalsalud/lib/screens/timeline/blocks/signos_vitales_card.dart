import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../../infra/app_texts.dart';
import '../../../services/historia_clinica_service.dart';

String? _nodeValue(dynamic node, String key) {
  if (node is! Map) return null;
  final v = node[key];
  if (v == null) return null;
  final s = v.toString().trim();
  return s.isEmpty ? null : s;
}

String _historyRowText(Map<String, dynamic> row) {
  final fechaRaw = row['fecha_atencion'] ?? row['fecha'];
  final partes = <String>[];
  if (fechaRaw != null && '$fechaRaw'.trim().isNotEmpty) {
    partes.add('$fechaRaw');
  }
  final ta = row['ta1_sistolica'] != null && row['ta1_diastolica'] != null
      ? '${row['ta1_sistolica']}/${row['ta1_diastolica']}'
      : row['ta']?.toString();
  if (ta != null && ta.trim().isNotEmpty) partes.add('PA $ta');
  if (row['peso'] != null) partes.add('Peso ${row['peso']}');
  if (row['talla'] != null) partes.add('Talla ${row['talla']}');
  return partes.isEmpty ? '—' : partes.join(' · ');
}

/// Signos vitales actuales del paciente con su historial.
class SignosVitalesCard extends StatelessWidget {
  const SignosVitalesCard({super.key, required this.sv});

  final SignosVitalesClinica sv;

  @override
  Widget build(BuildContext context) {
    final u = sv.ultimosSv;
    final peso = _nodeValue(u?['peso'], 'value');
    final talla = _nodeValue(u?['talla'], 'value');
    final imc = _nodeValue(u?['imc'], 'value');
    String? tension;
    final ta = u?['ta'];
    if (ta is Map) {
      final sys = ta['sistolica']?.toString().trim() ?? '';
      final dia = ta['diastolica']?.toString().trim() ?? '';
      if (sys.isNotEmpty && dia.isNotEmpty) {
        tension = '$sys/$dia mmHg';
      }
    }
    final hayResumen = peso != null || talla != null || imc != null || tension != null;

    final titulo = sv.fechaTitulo.isNotEmpty
        ? 'Signos vitales actuales (${sv.fechaTitulo})'
        : 'Signos vitales actuales';

    return BioCard.intent(
      intent: UiIntent.info,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(titulo, style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.md),
          if (!hayResumen)
            Text(AppTexts.noData, style: BioTypography.bodySm)
          else
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (peso != null)
                  Text(AppTexts.timelineWeightKg(peso), style: BioTypography.body),
                if (talla != null)
                  Text(AppTexts.timelineHeightCm(talla), style: BioTypography.body),
                if (imc != null) Text(AppTexts.timelineBmiValue(imc), style: BioTypography.body),
                if (tension != null)
                  Text(AppTexts.timelineBloodPressureValue(tension), style: BioTypography.body),
              ],
            ),
          if (sv.datosSv.isNotEmpty && (sv.tieneMasSv || sv.datosSv.length > 1)) ...[
            BioSpacing.gapH(BioSpacing.md),
            ExpansionTile(
              tilePadding: EdgeInsets.zero,
              title: Text(
                'Historial (${sv.totalSv} registros)',
                style: BioTypography.title,
              ),
              children: [
                for (final row in sv.datosSv.take(12))
                  Padding(
                    padding: const EdgeInsets.only(bottom: BioSpacing.sm),
                    child: Text(_historyRowText(row), style: BioTypography.caption),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
