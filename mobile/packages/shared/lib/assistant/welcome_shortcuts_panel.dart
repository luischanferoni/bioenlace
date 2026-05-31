import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';
import '../ui/bio_card.dart';
import 'asistente_service.dart';

/// Atajos de bienvenida: ocupa el área de mensajes (ancho completo, scroll vertical).
class WelcomeShortcutsPanel extends StatelessWidget {
  const WelcomeShortcutsPanel({
    super.key,
    required this.categorias,
    required this.scrollController,
    required this.isSending,
    required this.onShortcutTap,
  });

  final List<AtajoCategoria> categorias;
  final ScrollController scrollController;
  final bool isSending;
  final void Function(String intentId, String title) onShortcutTap;

  @override
  Widget build(BuildContext context) {
    if (categorias.isEmpty) {
      return const SizedBox.shrink();
    }
    final tokens = context.bio;
    final primary = IntentPalette.of(UiIntent.primary).base;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(
            BioSpacing.lg,
            BioSpacing.md,
            BioSpacing.lg,
            BioSpacing.sm,
          ),
          child: Text(
            'Podés elegir un atajo o escribir tu consulta abajo.',
            style: BioTypography.h3.copyWith(color: tokens.textMuted),
          ),
        ),
        Expanded(
          child: Scrollbar(
            controller: scrollController,
            child: SingleChildScrollView(
              controller: scrollController,
              padding: const EdgeInsets.fromLTRB(
                BioSpacing.lg,
                0,
                BioSpacing.lg,
                BioSpacing.md,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  for (final cat in categorias) ...[
                    Text(
                      cat.titulo,
                      style: BioTypography.title.copyWith(
                        decoration: TextDecoration.underline,
                        color: tokens.textMuted,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    BioSpacing.gapH(BioSpacing.sm),
                    for (final item in cat.items) ...[
                      BioCard(
                        padding: const EdgeInsets.symmetric(
                          horizontal: BioSpacing.md,
                          vertical: BioSpacing.sm + 2,
                        ),
                        onTap: isSending
                            ? null
                            : () => onShortcutTap(item.intentId, item.title),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              item.title,
                              style: BioTypography.title.copyWith(
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                                color: primary,
                              ),
                            ),
                            if (item.description.isNotEmpty) ...[
                              BioSpacing.gapH(BioSpacing.xs),
                              Text(
                                item.description,
                                style: BioTypography.bodySm.copyWith(
                                  color: tokens.textMuted,
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                      BioSpacing.gapH(BioSpacing.sm),
                    ],
                    BioSpacing.gapH(BioSpacing.sm),
                  ],
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}
