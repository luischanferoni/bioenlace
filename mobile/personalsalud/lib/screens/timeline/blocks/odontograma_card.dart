import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../../infra/app_texts.dart';
import '../../../services/historia_clinica_service.dart';

/// Odontograma del paciente: piezas con registro, agrupadas por dentición.
class OdontogramaCard extends StatelessWidget {
  const OdontogramaCard({super.key, required this.odontograma});

  final OdontogramaClinica odontograma;

  static const _dentitionPermanent = 'permanent';
  static const _dentitionTemporary = 'temporary';

  @override
  Widget build(BuildContext context) {
    final secciones = _secciones();

    return BioCard.intent(
      intent: UiIntent.info,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(AppTexts.odontogramTitle, style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.xs),
          Text(AppTexts.odontogramHint, style: BioTypography.bodySm),
          if (secciones.isEmpty) ...[
            BioSpacing.gapH(BioSpacing.md),
            Text(AppTexts.noData, style: BioTypography.bodySm),
          ],
          for (final seccion in secciones) ...[
            BioSpacing.gapH(BioSpacing.md),
            Text(seccion.titulo, style: BioTypography.bodySm),
            for (final pieza in seccion.piezas) _PiezaTile(pieza: pieza),
          ],
        ],
      ),
    );
  }

  List<_Seccion> _secciones() {
    final permanentes = <OdontogramaPieza>[];
    final temporarias = <OdontogramaPieza>[];
    final sinClasificar = <OdontogramaPieza>[];

    for (final pieza in odontograma.piezas) {
      switch (pieza.denticion) {
        case _dentitionPermanent:
          permanentes.add(pieza);
        case _dentitionTemporary:
          temporarias.add(pieza);
        default:
          sinClasificar.add(pieza);
      }
    }

    return [
      if (permanentes.isNotEmpty)
        _Seccion(titulo: AppTexts.odontogramPermanent, piezas: permanentes),
      if (temporarias.isNotEmpty)
        _Seccion(titulo: AppTexts.odontogramTemporary, piezas: temporarias),
      if (sinClasificar.isNotEmpty)
        _Seccion(titulo: AppTexts.odontogramUnclassified, piezas: sinClasificar),
    ];
  }
}

class _Seccion {
  const _Seccion({required this.titulo, required this.piezas});

  final String titulo;
  final List<OdontogramaPieza> piezas;
}

class _PiezaTile extends StatelessWidget {
  const _PiezaTile({required this.pieza});

  final OdontogramaPieza pieza;

  @override
  Widget build(BuildContext context) {
    final actual = pieza.actual;
    final etiqueta = actual?.etiqueta ?? '';

    return ExpansionTile(
      tilePadding: EdgeInsets.zero,
      title: Row(
        children: [
          Expanded(
            child: Text(
              AppTexts.odontogramTooth(pieza.numero),
              style: BioTypography.body,
            ),
          ),
          if (actual != null) _MomentoBadge(tiempo: actual.tiempo),
        ],
      ),
      subtitle: Text(
        etiqueta.isEmpty ? AppTexts.odontogramNoDetail : etiqueta,
        style: BioTypography.bodySm,
      ),
      children: [
        for (final estado in pieza.historia) _EstadoRow(estado: estado),
      ],
    );
  }
}

class _EstadoRow extends StatelessWidget {
  const _EstadoRow({required this.estado});

  final OdontogramaEstado estado;

  @override
  Widget build(BuildContext context) {
    final etiqueta = estado.etiqueta;
    final caras = estado.caras.isEmpty ? null : AppTexts.odontogramSurfaces(estado.caras);

    return Padding(
      padding: const EdgeInsets.only(bottom: BioSpacing.sm),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  etiqueta.isEmpty ? AppTexts.odontogramNoDetail : etiqueta,
                  style: BioTypography.bodySm,
                ),
                if (caras != null)
                  Text(caras, style: BioTypography.caption),
                if (estado.registradoEn.isNotEmpty)
                  Text(estado.registradoEn, style: BioTypography.caption),
              ],
            ),
          ),
          _MomentoBadge(tiempo: estado.tiempo),
        ],
      ),
    );
  }
}

class _MomentoBadge extends StatelessWidget {
  const _MomentoBadge({required this.tiempo});

  final String tiempo;

  @override
  Widget build(BuildContext context) {
    final (label, intent) = switch (tiempo) {
      'PASADA' => (AppTexts.odontogramPast, UiIntent.neutral),
      'PRESENTE' => (AppTexts.odontogramPresent, UiIntent.info),
      'FUTURA' => (AppTexts.odontogramFuture, UiIntent.warning),
      _ => (AppTexts.odontogramNoMoment, UiIntent.neutral),
    };

    return BioBadge(label: label, intent: intent);
  }
}
