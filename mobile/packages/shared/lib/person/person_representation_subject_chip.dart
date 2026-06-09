import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';
import '../ui/ui.dart';
import 'person_representation_context.dart';

/// Chip «A cargo de» para header de inicio paciente.
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
        if (!ctx.actingForOther && ctx.options.length <= 1) {
          return const SizedBox.shrink();
        }

        final label = ctx.actingForOther ? 'A cargo de: ${ctx.subjectLabel}' : 'A cargo de: Yo';
        return Padding(
          padding: const EdgeInsets.only(top: BioSpacing.sm),
          child: Align(
            alignment: Alignment.centerLeft,
            child: BioChip(
              label: label,
              icon: Icons.switch_account_outlined,
              selected: ctx.actingForOther,
              onTap: ctx.loadingOptions ? null : () => _openPicker(context, ctx),
            ),
          ),
        );
      },
    );
  }

  Future<void> _openPicker(BuildContext context, PersonRepresentationContext ctx) async {
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
                final selected = ctx.subjectPersonaId == o.personaId ||
                    (!ctx.actingForOther && o.personaId == ctx.actorPersonaId);
                return ListTile(
                  leading: Icon(
                    o.isSelf ? Icons.person_outline : Icons.family_restroom_outlined,
                  ),
                  title: Text(o.label),
                  subtitle: o.isSelf
                      ? const Text('Tu cuenta')
                      : Text(o.regime == 'verified_guardianship' ? 'Tutela' : 'Representante'),
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
}
