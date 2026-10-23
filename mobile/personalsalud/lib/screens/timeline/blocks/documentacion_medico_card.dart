import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../../infra/app_texts.dart';
import '../../../services/historia_clinica_service.dart';

/// Documentación cargada en la consulta; las secciones las define el backend.
class DocumentacionMedicoCard extends StatelessWidget {
  const DocumentacionMedicoCard({super.key, required this.doc});

  final DocumentacionMedico doc;

  @override
  Widget build(BuildContext context) {
    return BioCard.intent(
      intent: UiIntent.success,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(AppTexts.timelineLoadedData, style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.md),
          if (!doc.tieneDatos || doc.secciones.isEmpty)
            Text(
              AppTexts.documentationEmpty,
              style: BioTypography.bodySm,
            )
          else
            ClinicalSectionList(sections: doc.secciones),
        ],
      ),
    );
  }
}
