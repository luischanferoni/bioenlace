import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';
import '../ui/ui.dart';
import 'person_representation_context.dart';
import 'person_representation_picker.dart';

/// Chip «A cargo de» para elegir sujeto cuando aún operás por tu cuenta.
class PersonRepresentationSubjectChip extends StatelessWidget {
  final String? authToken;
  final VoidCallback? onSubjectChanged;

  const PersonRepresentationSubjectChip({
    super.key,
    this.authToken,
    this.onSubjectChanged,
  });

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: PersonRepresentationContext.instance,
      builder: (context, _) {
        final ctx = PersonRepresentationContext.instance;
        if (ctx.actorPersonaId <= 0) {
          return const SizedBox.shrink();
        }
        if (ctx.actingForOther || ctx.options.length <= 1) {
          return const SizedBox.shrink();
        }

        return Padding(
          padding: const EdgeInsets.only(top: BioSpacing.sm),
          child: Align(
            alignment: Alignment.centerLeft,
            child: BioChip(
              label: 'A cargo de: Yo',
              icon: Icons.switch_account_outlined,
              onTap: ctx.loadingOptions
                  ? null
                  : () => showPersonRepresentationSubjectPicker(
                        context,
                        authToken: authToken,
                        onSubjectChanged: onSubjectChanged,
                      ),
            ),
          ),
        );
      },
    );
  }
}
