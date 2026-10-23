import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../../services/historia_clinica_service.dart';

/// Cabecera del paciente; con un resumen de atención cargado no muestra edad ni género.
class PacienteHeaderCard extends StatelessWidget {
  const PacienteHeaderCard({super.key, required this.persona, this.showDemographics = true});

  final PersonaData persona;
  final bool showDemographics;

  @override
  Widget build(BuildContext context) {
    final meta = <String>[];
    if (showDemographics && persona.edad != null) {
      meta.add('${persona.edad} años');
    }
    final genero = (persona.generoTexto ?? '').trim();
    if (showDemographics && genero.isNotEmpty) {
      meta.add(genero);
    }

    return BioCard.intent(
      intent: UiIntent.primary,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Text(persona.nombreCompleto, style: BioTypography.h3),
          ),
          if (meta.isNotEmpty) ...[
            BioSpacing.gapW(BioSpacing.sm),
            Text(
              meta.join(' · '),
              style: BioTypography.title.copyWith(color: context.bio.textMuted),
              textAlign: TextAlign.right,
            ),
          ],
        ],
      ),
    );
  }
}
