import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../../infra/app_texts.dart';
import '../../../models/encounter_capture_analysis.dart';

/// Revisión de la nota capturada: texto, categorías extraídas, pendientes por resolver y problemas abiertos.
/// No guarda estado: la pantalla provee las selecciones y recibe los cambios por callback.
class CaptureReviewPanel extends StatelessWidget {
  const CaptureReviewPanel({
    super.key,
    required this.review,
    required this.draftText,
    required this.stagedItemIds,
    required this.issueResolutions,
    required this.openProblemResolutions,
    required this.isSaving,
    required this.customController,
    required this.onToggleItem,
    required this.onIssueOption,
    required this.onIssueCustomText,
    required this.onOpenProblemOption,
  });

  final EncounterCaptureAnalysis review;
  final String? draftText;
  final Set<String> stagedItemIds;
  final Map<String, dynamic> issueResolutions;
  final Map<String, dynamic> openProblemResolutions;
  final bool isSaving;
  final TextEditingController Function(String issueId) customController;
  final void Function(String itemId, bool selected) onToggleItem;
  final void Function(EncounterCaptureIssue issue, dynamic value, bool wasSelected) onIssueOption;
  final void Function(EncounterCaptureIssue issue, String text) onIssueCustomText;
  final void Function(String key, dynamic value, bool wasSelected) onOpenProblemOption;

  @override
  Widget build(BuildContext context) {
    final sectionTitleStyle = BioTypography.title;
    final categoryTitleStyle = BioTypography.bodySm.copyWith(
      fontWeight: FontWeight.w600,
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Center(
          child: Text(
            'Nota de esta atención',
            textAlign: TextAlign.center,
            style: BioTypography.title.copyWith(
              decoration: TextDecoration.underline,
            ),
          ),
        ),
        BioSpacing.gapH(BioSpacing.md),
        Text(AppTexts.captureRegisteredText, style: sectionTitleStyle),
        BioSpacing.gapH(BioSpacing.xs),
        Text(
          (draftText ?? review.textoOriginal).trim().isNotEmpty
              ? (draftText ?? review.textoOriginal)
              : review.textoOriginal,
          style: BioTypography.body,
        ),
        if (review.advisories.isNotEmpty) ...[
          BioSpacing.gapH(BioSpacing.md),
          ...review.advisories.map((adv) {
            final intent = adv.severity == 'danger'
                ? UiIntent.danger
                : (adv.severity == 'info' ? UiIntent.info : UiIntent.warning);
            return Padding(
              padding: const EdgeInsets.only(bottom: BioSpacing.sm),
              child: BioAlert(intent: intent, message: adv.message),
            );
          }),
        ],
        if (review.episodeNoteDuplicate && review.advisories.isEmpty) ...[
          BioSpacing.gapH(BioSpacing.sm),
          BioAlert.danger(
            message:
                'Esta nota es casi idéntica a una evolución previa. Editá el texto para documentar los cambios.',
          ),
        ],
        if (review.textoProcesado != null &&
            review.textoProcesado!.trim().isNotEmpty &&
            review.textoProcesado!.trim() != review.textoOriginal.trim()) ...[
          BioSpacing.gapH(BioSpacing.md),
          Text(AppTexts.captureProcessedText, style: sectionTitleStyle),
          BioSpacing.gapH(BioSpacing.xs),
          Text(review.textoProcesado!, style: BioTypography.bodySm),
        ],
        if (review.systemError != null) ...[
          BioSpacing.gapH(BioSpacing.md),
          BioAlert.danger(message: review.systemError!),
        ] else if (!review.hasExtractedContent) ...[
          BioSpacing.gapH(BioSpacing.md),
          BioAlert.info(
            message: 'Sin datos estructurados. Se guardará el texto.',
          ),
        ] else ...[
          BioSpacing.gapH(BioSpacing.lg),
          Text(AppTexts.captureProcessingResult, style: sectionTitleStyle),
          BioSpacing.gapH(BioSpacing.sm),
          ...review.categories.map((cat) {
            final delTexto = cat.items
                .where((i) => i.isFromClinicalText)
                .toList();
            final sugeridos = cat.items
                .where((i) => !i.isFromClinicalText)
                .toList();

            return Padding(
              padding: const EdgeInsets.only(bottom: BioSpacing.md),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(cat.title, style: categoryTitleStyle),
                      ),
                      if (cat.required) BioBadge.danger('Requerido'),
                    ],
                  ),
                  BioSpacing.gapH(BioSpacing.sm),
                  if (cat.items.isEmpty)
                    Text(
                      cat.required
                          ? 'Falta información en esta categoría.'
                          : 'Sin datos en esta categoría.',
                      style: BioTypography.bodySm.copyWith(
                        color: cat.required
                            ? IntentPalette.of(UiIntent.danger).base
                            : context.bio.textMuted,
                      ),
                    ),
                  ...delTexto.map(
                    (item) => _itemBlock(context, item),
                  ),
                  if (sugeridos.isNotEmpty) ...[
                    if (delTexto.isNotEmpty) BioSpacing.gapH(BioSpacing.sm),
                    ...sugeridos.map(
                      (item) => Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _itemBlock(context, item),
                          Text(
                            'Sugerido por IA',
                            style: BioTypography.caption.copyWith(
                              color: IntentPalette.of(UiIntent.secondary).base,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            );
          }),
        ],
        if (review.missingCategories.isNotEmpty) ...[
          BioSpacing.gapH(BioSpacing.sm),
          BioAlert.warning(
            message:
                'Faltan categorías obligatorias: ${review.missingCategories.join(', ')}.',
          ),
        ],
        ...review
            .orphanIssuesForStaged(stagedItemIds)
            .map((i) => _issueBlock(context, i)),
        if (review.hasOpenProblems) ...[
          BioSpacing.gapH(BioSpacing.md),
          Text(AppTexts.captureOpenProblems, style: sectionTitleStyle),
          BioSpacing.gapH(BioSpacing.xs),
          Text(
            'Opcional: indicá el estado al cerrar. Si no elegís, se mantienen.',
            style: BioTypography.caption.copyWith(color: context.bio.textMuted),
          ),
          BioSpacing.gapH(BioSpacing.sm),
          ...review.openProblems!.conditions.map(
            (item) => _openProblemBlock(context, item),
          ),
          ...review.openProblems!.carePlans.map(
            (item) => _openProblemBlock(context, item),
          ),
        ],
      ],
    );
  }

  Widget _openProblemBlock(BuildContext context, EncounterOpenProblemItem item) {
    final key = '${item.kind}:${item.id}';
    final selected = openProblemResolutions[key];
    return Padding(
      padding: const EdgeInsets.only(bottom: BioSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            item.label,
            style: BioTypography.bodySm.copyWith(fontWeight: FontWeight.w600),
          ),
          if (item.detail != null && item.detail!.isNotEmpty)
            Text(
              item.detail!,
              style: BioTypography.caption.copyWith(
                color: context.bio.textMuted,
              ),
            ),
          if (item.statusLabel != null && item.statusLabel!.isNotEmpty)
            Text(
              item.statusLabel!,
              style: BioTypography.caption.copyWith(
                color: context.bio.textMuted,
              ),
            ),
          BioSpacing.gapH(BioSpacing.sm),
          Wrap(
            spacing: BioSpacing.sm,
            runSpacing: BioSpacing.sm,
            children: item.options.map((opt) {
              final isSelected =
                  selected != null &&
                  selected.toString() == opt.value.toString();
              return BioChip(
                label: opt.label,
                selected: isSelected,
                icon: isSelected ? Icons.check : null,
                intent: UiIntent.neutral,
                onTap: isSaving
                    ? null
                    : () => onOpenProblemOption(key, opt.value, isSelected),
              );
            }).toList(),
          ),
        ],
      ),
    );
  }

  Widget _issueBlock(BuildContext context, EncounterCaptureIssue issue) {
    final selected = issueResolutions[issue.id];
    return Padding(
      padding: const EdgeInsets.only(bottom: BioSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (issue.field.isNotEmpty)
            Text(
              issue.field,
              style: BioTypography.caption.copyWith(
                color: context.bio.textMuted,
              ),
            ),
          BioSpacing.gapH(BioSpacing.sm),
          if (issue.options.isNotEmpty)
            Wrap(
              spacing: BioSpacing.sm,
              runSpacing: BioSpacing.sm,
              children: issue.options.map((opt) {
                final isSelected =
                    selected != null &&
                    selected.toString() == opt.value.toString();
                return BioChip(
                  label: opt.label,
                  selected: isSelected,
                  icon: isSelected ? Icons.check : null,
                  intent: UiIntent.neutral,
                  onTap: isSaving
                      ? null
                      : () => onIssueOption(issue, opt.value, isSelected),
                );
              }).toList(),
            ),
          if (issue.allowCustom) ...[
            BioSpacing.gapH(BioSpacing.sm),
            TextField(
              controller: customController(issue.id),
              enabled: !isSaving,
              decoration: InputDecoration(
                hintText: issue.options.isEmpty
                    ? 'Completá el valor'
                    : 'Otra opción…',
                isDense: true,
                border: const OutlineInputBorder(),
              ),
              onChanged: (value) => onIssueCustomText(issue, value.trim()),
            ),
          ],
        ],
      ),
    );
  }

  Widget _itemBlock(BuildContext context, EncounterCaptureItem item) {
    final incomplete = review.incompleteForItem(item.id);
    final itemIssues = review.issuesForItem(item.id);
    final selected = stagedItemIds.contains(item.id);
    return Padding(
      padding: const EdgeInsets.only(bottom: BioSpacing.sm),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _itemChip(item, incomplete: incomplete != null),
          if (selected && incomplete != null && itemIssues.isEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              incomplete.message,
              style: BioTypography.bodySm.copyWith(
                color: IntentPalette.of(UiIntent.danger).base,
              ),
            ),
          ],
          if (selected && itemIssues.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Text(
              'Completar datos',
              style: BioTypography.bodySm.copyWith(
                fontWeight: FontWeight.w600,
                color: IntentPalette.of(UiIntent.danger).base,
              ),
            ),
            BioSpacing.gapH(BioSpacing.xs),
            ...itemIssues.map((i) => _issueBlock(context, i)),
          ],
        ],
      ),
    );
  }

  Widget _itemChip(
    EncounterCaptureItem item, {
    bool incomplete = false,
  }) {
    final selected = stagedItemIds.contains(item.id);
    final label = item.subtitle != null && item.subtitle!.isNotEmpty
        ? '${item.label} (${item.subtitle})'
        : item.label;

    return BioChip(
      label: label,
      selected: selected,
      filled: incomplete && selected,
      icon: selected
          ? Icons.check_circle
          : (incomplete ? Icons.add_circle_outline : Icons.add_circle_outline),
      intent: incomplete
          ? UiIntent.danger
          : (item.alreadyActive && !selected
                ? UiIntent.neutral
                : (item.isFromClinicalText
                      ? UiIntent.neutral
                      : UiIntent.secondary)),
      onTap: isSaving ? null : () => onToggleItem(item.id, !selected),
    );
  }
}
