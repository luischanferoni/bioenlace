import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';

/// Burbuja del sistema con la guía declarativa del chat de motivos.
class MotivosConsultaGuideBubble extends StatelessWidget {
  final String message;
  final String? title;

  const MotivosConsultaGuideBubble({
    super.key,
    required this.message,
    this.title,
  });

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final palette = tokens.intentPalette(UiIntent.neutral);

    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.symmetric(
          vertical: BioSpacing.xs,
          horizontal: BioSpacing.sm,
        ),
        padding: const EdgeInsets.all(BioSpacing.md),
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.88,
        ),
        decoration: BoxDecoration(
          color: palette.softBg,
          borderRadius: const BorderRadius.only(
            topLeft: Radius.circular(BioRadius.md),
            topRight: Radius.circular(BioRadius.md),
            bottomRight: Radius.circular(BioRadius.md),
            bottomLeft: Radius.circular(BioRadius.xs),
          ),
          border: Border.all(color: palette.base.withValues(alpha: 0.18)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.support_agent_outlined, size: 18, color: palette.base),
                BioSpacing.gapW(BioSpacing.sm),
                Text(
                  title ?? 'Bioenlace',
                  style: BioTypography.bodySm.copyWith(
                    color: palette.softFg,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
            BioSpacing.gapH(BioSpacing.sm),
            Text(
              message,
              style: BioTypography.body.copyWith(
                color: tokens.textBody,
                height: 1.45,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
