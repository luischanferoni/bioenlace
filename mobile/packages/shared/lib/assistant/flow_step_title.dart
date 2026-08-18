import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';

/// Título de paso de flow: primera línea en énfasis; el resto (si hay) en body.
class FlowStepTitle extends StatelessWidget {
  const FlowStepTitle({
    super.key,
    required this.text,
    this.muted = false,
  });

  final String text;
  final bool muted;

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final stepColor = muted ? tokens.textMuted : tokens.textTitle;
    final parts = text.split('\n');
    final first = parts.isEmpty ? '' : parts.first.trim();
    final rest = parts.length > 1 ? parts.skip(1).join('\n').trim() : '';

    return Padding(
      padding: const EdgeInsets.fromLTRB(
        BioSpacing.lg,
        BioSpacing.xs,
        BioSpacing.lg,
        0,
      ),
      child: Align(
        alignment: Alignment.centerLeft,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (first.isNotEmpty)
              Text(
                first,
                textAlign: TextAlign.left,
                style: BioTypography.title.copyWith(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                  color: stepColor,
                ),
              ),
            if (rest.isNotEmpty) ...[
              const SizedBox(height: BioSpacing.xs),
              Text(
                rest,
                textAlign: TextAlign.left,
                style: BioTypography.body.copyWith(
                  fontWeight: FontWeight.w400,
                  color: muted ? tokens.textMuted : tokens.textBody,
                  height: 1.35,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
