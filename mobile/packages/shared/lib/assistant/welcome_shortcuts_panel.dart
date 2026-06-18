import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';
import 'asistente_service.dart';
import 'atajo_shortcut_cards.dart';

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
            'Elegí un atajo para comenzar o escribí tu consulta abajo.',
            style: BioTypography.h3.copyWith(
              color: tokens.textMuted,
              fontSize: 16,
              fontWeight: FontWeight.w500,
              height: 1.35,
            ),
          ),
        ),
        Expanded(
          child: Scrollbar(
            controller: scrollController,
            child: SingleChildScrollView(
              controller: scrollController,
              padding: const EdgeInsets.only(bottom: BioSpacing.md),
              child: AtajoShortcutCards(
                categorias: categorias,
                enabled: !isSending,
                onTap: onShortcutTap,
              ),
            ),
          ),
        ),
      ],
    );
  }
}
