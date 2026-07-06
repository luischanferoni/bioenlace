import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';
import 'person_representation_context.dart';
import 'person_representation_picker.dart';

/// Barra suave que indica modo parental (operando por otro paciente).
class PersonRepresentationActiveBanner extends StatelessWidget {
  const PersonRepresentationActiveBanner({
    super.key,
    this.authToken,
    this.onSubjectChanged,
    this.margin,
  });

  final String? authToken;
  final VoidCallback? onSubjectChanged;
  final EdgeInsetsGeometry? margin;

  static String messageFor(PersonRepresentationContext ctx) {
    final subject = ctx.subjectLabel;
    RepresentationSubjectOption? match;
    for (final o in ctx.options) {
      if (o.personaId == ctx.subjectPersonaId) {
        match = o;
        break;
      }
    }
    if (match?.regime == 'verified_guardianship') {
      return 'Modo parental · Tutela de $subject';
    }
    return 'Modo parental · A cargo de $subject';
  }

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: PersonRepresentationContext.instance,
      builder: (context, _) {
        final ctx = PersonRepresentationContext.instance;
        if (!ctx.actingForOther || ctx.actorPersonaId <= 0) {
          return const SizedBox.shrink();
        }

        final palette = IntentPalette.of(UiIntent.info);
        final message = messageFor(ctx);

        final bar = Material(
          color: palette.softBg.withValues(alpha: 0.88),
          child: InkWell(
            onTap: ctx.loadingOptions
                ? null
                : () => showPersonRepresentationSubjectPicker(
                      context,
                      authToken: authToken,
                      onSubjectChanged: onSubjectChanged,
                    ),
            child: Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: BioSpacing.md,
                vertical: BioSpacing.sm,
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.family_restroom_outlined,
                    color: palette.base,
                    size: 22,
                  ),
                  BioSpacing.gapW(BioSpacing.sm),
                  Expanded(
                    child: Text(
                      message,
                      style: BioTypography.bodySm.copyWith(
                        color: palette.softFg,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  Icon(
                    Icons.unfold_more_rounded,
                    color: palette.base.withValues(alpha: 0.85),
                    size: 20,
                  ),
                ],
              ),
            ),
          ),
        );

        if (margin == null) return bar;
        return Padding(padding: margin!, child: bar);
      },
    );
  }
}
