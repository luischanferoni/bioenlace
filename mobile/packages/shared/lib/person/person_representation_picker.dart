import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';
import 'person_representation_context.dart';

/// Abre el selector «¿Por quién operás?» y persiste la elección.
Future<void> showPersonRepresentationSubjectPicker(
  BuildContext context, {
  required String? authToken,
  VoidCallback? onSubjectChanged,
}) async {
  final ctx = PersonRepresentationContext.instance;
  if (ctx.options.isEmpty) {
    await ctx.refreshOptions(authToken: authToken);
  }
  if (!context.mounted) return;

  final options = PersonRepresentationContext.instance.options;
  if (options.length <= 1) return;

  final picked = await showModalBottomSheet<RepresentationSubjectOption>(
    context: context,
    showDragHandle: true,
    builder: (sheetCtx) {
      final current = PersonRepresentationContext.instance;
      return SafeArea(
        child: ListView(
          shrinkWrap: true,
          children: [
            Padding(
              padding: BioSpacing.pageHorizontal.copyWith(top: BioSpacing.sm),
              child: Text(
                '¿Por quién operás?',
                style: BioTypography.title.copyWith(fontWeight: FontWeight.w700),
              ),
            ),
            BioSpacing.gapH(BioSpacing.sm),
            ...options.map((o) {
              final selected = current.subjectPersonaId == o.personaId ||
                  (!current.actingForOther &&
                      o.personaId == current.actorPersonaId);
              return ListTile(
                leading: Icon(
                  o.isSelf ? Icons.person_outline : Icons.family_restroom_outlined,
                ),
                title: Text(o.label),
                subtitle: o.isSelf
                    ? const Text('Tu cuenta')
                    : Text(
                        o.regime == 'verified_guardianship'
                            ? 'Tutela'
                            : 'Representante',
                      ),
                trailing: selected ? const Icon(Icons.check) : null,
                onTap: () => Navigator.pop(sheetCtx, o),
              );
            }),
          ],
        ),
      );
    },
  );

  if (picked == null) return;
  await ctx.selectSubject(picked, authToken: authToken);
  onSubjectChanged?.call();
}
