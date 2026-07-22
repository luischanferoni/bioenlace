import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';
import '../ui/ui.dart';

/// Panel de contexto de solicitud async (lines + detalle encounter + CTAs por kind).
///
/// El cliente pasa [onReference] y navega según `kind` (`clinical_history`,
/// `reference_encounter`, …) sin hardcodear pantallas de dominio en el widget.
class AsyncIntakeContextPanel extends StatelessWidget {
  const AsyncIntakeContextPanel({
    super.key,
    required this.intakeContext,
    this.onReference,
    this.compact = false,
  });

  final Map<String, dynamic> intakeContext;
  final void Function(Map<String, dynamic> reference)? onReference;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final sectionLabel =
        intakeContext['section_label']?.toString().trim().isNotEmpty == true
            ? intakeContext['section_label'].toString().trim()
            : 'Contexto de la solicitud';
    final tipoLabel = intakeContext['tipo_label']?.toString().trim() ?? '';
    final lines = intakeContext['lines'];
    final refEnc = intakeContext['reference_encounter'];
    final detail = refEnc is Map ? refEnc['detail'] : null;
    final references = intakeContext['references'];

    final lineWidgets = <Widget>[];
    if (lines is List) {
      for (final raw in lines) {
        if (raw is! Map) continue;
        final code = raw['code']?.toString() ?? '';
        if (code == 'reference_encounter') continue;
        final label = raw['label']?.toString().trim() ?? '';
        final value = raw['value']?.toString().trim() ?? '';
        if (label.isEmpty || value.isEmpty) continue;
        lineWidgets.add(
          Padding(
            padding: const EdgeInsets.only(bottom: BioSpacing.xs),
            child: Text.rich(
              TextSpan(
                children: [
                  TextSpan(
                    text: '$label: ',
                    style: BioTypography.bodySm.copyWith(fontWeight: FontWeight.w600),
                  ),
                  TextSpan(text: value, style: BioTypography.bodySm),
                ],
              ),
            ),
          ),
        );
      }
    }

    final actionButtons = <Widget>[];
    if (onReference != null && references is List) {
      for (final raw in references) {
        if (raw is! Map) continue;
        final kind = raw['kind']?.toString().trim() ?? '';
        if (kind.isEmpty) continue;
        final label = raw['label']?.toString().trim();
        if (label == null || label.isEmpty) continue;
        final map = Map<String, dynamic>.from(raw);
        final isPrimary = kind == 'clinical_history';
        actionButtons.add(
          isPrimary
              ? BioButton.primary(
                  label: label,
                  size: BioButtonSize.sm,
                  onPressed: () => onReference!(map),
                )
              : BioButton.outlinePrimary(
                  label: label,
                  size: BioButtonSize.sm,
                  onPressed: () => onReference!(map),
                ),
        );
      }
    }

    return BioCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            sectionLabel,
            style: BioTypography.bodySm.copyWith(fontWeight: FontWeight.w600),
          ),
          if (tipoLabel.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              tipoLabel,
              style: BioTypography.caption.copyWith(color: context.bio.textMuted),
            ),
          ],
          if (lineWidgets.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.sm),
            ...lineWidgets,
          ],
          if (detail is Map) ...[
            BioSpacing.gapH(BioSpacing.sm),
            _EncounterDetailBlock(
              detail: Map<String, dynamic>.from(detail),
              compact: compact,
            ),
          ],
          if (actionButtons.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Wrap(
              spacing: BioSpacing.sm,
              runSpacing: BioSpacing.xs,
              children: actionButtons,
            ),
          ],
        ],
      ),
    );
  }
}

class _EncounterDetailBlock extends StatelessWidget {
  const _EncounterDetailBlock({
    required this.detail,
    required this.compact,
  });

  final Map<String, dynamic> detail;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final title = detail['title']?.toString().trim().isNotEmpty == true
        ? detail['title'].toString().trim()
        : 'Atención de referencia';
    final headline = detail['headline']?.toString().trim() ?? '';
    final narrative = detail['narrativeText']?.toString().trim() ?? '';
    final efector = detail['efector'] is Map
        ? (detail['efector'] as Map)['nombre']?.toString().trim()
        : null;
    final profesional = detail['profesional'] is Map
        ? (detail['profesional'] as Map)['display']?.toString().trim()
        : null;
    final fecha = detail['periodEnd']?.toString().trim().isNotEmpty == true
        ? detail['periodEnd'].toString().trim()
        : detail['publishedAt']?.toString().trim();

    final maxChars = compact ? 280 : 600;
    final shownNarrative = narrative.length > maxChars
        ? '${narrative.substring(0, maxChars)}…'
        : narrative;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(BioSpacing.sm),
      decoration: BoxDecoration(
        color: context.bio.paperSurfaceSunken,
        borderRadius: BorderRadius.circular(BioRadius.sm),
        border: Border.all(color: context.bio.paperBorderDefault),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: BioTypography.bodySm.copyWith(fontWeight: FontWeight.w600),
          ),
          if (headline.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              headline,
              style: BioTypography.caption.copyWith(color: context.bio.textMuted),
            ),
          ],
          if (efector != null && efector.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(efector, style: BioTypography.bodySm),
          ],
          if (profesional != null && profesional.isNotEmpty)
            Text('Profesional: $profesional', style: BioTypography.bodySm),
          if (fecha != null && fecha.isNotEmpty)
            Text(
              fecha,
              style: BioTypography.caption.copyWith(color: context.bio.textMuted),
            ),
          if (shownNarrative.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Text(shownNarrative, style: BioTypography.bodySm),
          ],
        ],
      ),
    );
  }
}

/// Pantalla de solo lectura con el detalle lean del encounter de referencia.
class AsyncReferenceEncounterDetailScreen extends StatelessWidget {
  const AsyncReferenceEncounterDetailScreen({
    super.key,
    required this.detail,
  });

  final Map<String, dynamic> detail;

  @override
  Widget build(BuildContext context) {
    final title = detail['title']?.toString().trim().isNotEmpty == true
        ? detail['title'].toString().trim()
        : 'Atención de referencia';
    final narrative = detail['narrativeText']?.toString().trim() ?? '';
    final efector = detail['efector'] is Map
        ? (detail['efector'] as Map)['nombre']?.toString().trim()
        : null;
    final profesional = detail['profesional'] is Map
        ? (detail['profesional'] as Map)['display']?.toString().trim()
        : null;
    final fecha = detail['periodEnd']?.toString().trim().isNotEmpty == true
        ? detail['periodEnd'].toString().trim()
        : detail['publishedAt']?.toString().trim();

    return Scaffold(
      backgroundColor: context.bio.paperBackground,
      appBar: BioAppBar(title: title),
      body: ListView(
        padding: BioSpacing.pageAll,
        children: [
          if (efector != null && efector.isNotEmpty)
            Text(
              efector,
              style: BioTypography.title.copyWith(fontWeight: FontWeight.w700),
            ),
          if (profesional != null && profesional.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text('Profesional: $profesional', style: BioTypography.body),
          ],
          if (fecha != null && fecha.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              fecha,
              style: BioTypography.bodySm.copyWith(color: context.bio.textMuted),
            ),
          ],
          BioSpacing.gapH(BioSpacing.lg),
          BioCard(
            child: Text(
              narrative.isNotEmpty
                  ? narrative
                  : 'Sin resumen narrativo disponible para esta atención.',
              style: BioTypography.body,
            ),
          ),
        ],
      ),
    );
  }
}
