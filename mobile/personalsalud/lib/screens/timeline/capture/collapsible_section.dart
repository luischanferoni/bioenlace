import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../../infra/app_texts.dart';

/// Sección con cabecera tocable que contrae o expande su contenido.
class CollapsibleSection extends StatelessWidget {
  const CollapsibleSection({
    super.key,
    required this.title,
    required this.expanded,
    required this.onToggle,
    required this.child,
    this.badge,
  });

  final String title;
  final bool expanded;
  final VoidCallback onToggle;
  final Widget child;
  final String? badge;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        InkWell(
          onTap: onToggle,
          borderRadius: BorderRadius.circular(BioRadius.sm),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: BioSpacing.sm),
            child: Row(
              children: [
                Expanded(child: Text(title, style: BioTypography.title)),
                if (badge != null) ...[
                  BioBadge(label: badge!, intent: UiIntent.neutral),
                  BioSpacing.gapW(BioSpacing.sm),
                ],
                Text(
                  expanded ? AppTexts.notesCollapse : AppTexts.notesExpand,
                  style: BioTypography.caption.copyWith(
                    color: context.bio.textMuted,
                  ),
                ),
                Icon(expanded ? Icons.expand_less : Icons.expand_more),
              ],
            ),
          ),
        ),
        if (expanded) child,
      ],
    );
  }
}
