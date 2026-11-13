import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../../infra/app_texts.dart';
import '../../../services/historia_clinica_service.dart';

/// Receta de lentes del paciente: medidas por ojo.
class RecetaLentesCard extends StatelessWidget {
  const RecetaLentesCard({super.key, required this.receta});

  final RecetaLentesClinica receta;

  @override
  Widget build(BuildContext context) {
    final filas = <(String, String, String)>[
      (AppTexts.lensSphere, receta.od.esfera, receta.oi.esfera),
      (AppTexts.lensCylinder, receta.od.cilindro, receta.oi.cilindro),
      (AppTexts.lensAxis, receta.od.eje, receta.oi.eje),
      (AppTexts.lensAddition, receta.od.adicion, receta.oi.adicion),
    ];

    return BioCard.intent(
      intent: UiIntent.info,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(AppTexts.lensTitle, style: BioTypography.title),
          if (receta.registradoEn.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(receta.registradoEn, style: BioTypography.caption),
          ],
          BioSpacing.gapH(BioSpacing.md),
          _FilaMedidas(
            etiqueta: '',
            od: AppTexts.lensEyeOd,
            oi: AppTexts.lensEyeOi,
            encabezado: true,
          ),
          for (final fila in filas)
            _FilaMedidas(
              etiqueta: fila.$1,
              od: fila.$2,
              oi: fila.$3,
            ),
        ],
      ),
    );
  }
}

class _FilaMedidas extends StatelessWidget {
  const _FilaMedidas({
    required this.etiqueta,
    required this.od,
    required this.oi,
    this.encabezado = false,
  });

  final String etiqueta;
  final String od;
  final String oi;
  final bool encabezado;

  @override
  Widget build(BuildContext context) {
    final style = encabezado ? BioTypography.caption : BioTypography.bodySm;

    return Padding(
      padding: const EdgeInsets.only(bottom: BioSpacing.xs),
      child: Row(
        children: [
          Expanded(flex: 3, child: Text(etiqueta, style: style)),
          Expanded(
            flex: 2,
            child: Text(_valor(od), style: style, textAlign: TextAlign.center),
          ),
          Expanded(
            flex: 2,
            child: Text(_valor(oi), style: style, textAlign: TextAlign.center),
          ),
        ],
      ),
    );
  }

  String _valor(String value) =>
      value.isNotEmpty ? value : (encabezado ? value : AppTexts.lensNoValue);
}
