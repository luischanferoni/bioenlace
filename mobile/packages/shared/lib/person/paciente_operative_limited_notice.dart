import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import 'paciente_context_scope.dart';

/// Aviso breve cuando el paciente aún no puede usar funciones que requieren provincia de contexto.
class PacienteOperativeLimitedNotice extends StatelessWidget {
  const PacienteOperativeLimitedNotice({super.key});

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: PacienteContextScope.instance,
      builder: (context, _) {
        final state = PacienteContextScope.instance.state;
        if (state.puedeOperar) return const SizedBox.shrink();

        final banner = state.banner;
        if (banner?['kind']?.toString() == 'domicilio_pendiente') {
          return const SizedBox.shrink();
        }

        final message = banner?['message']?.toString();
        final text = (message != null && message.isNotEmpty)
            ? message
            : 'Completá tu provincia de contexto para reservar turnos y ver centros de tu zona.';

        return Padding(
          padding: const EdgeInsets.fromLTRB(
            BioSpacing.md,
            BioSpacing.xs,
            BioSpacing.md,
            BioSpacing.sm,
          ),
          child: Text(
            text,
            style: BioTypography.bodySm.copyWith(
              color: context.bio.textMuted,
            ),
          ),
        );
      },
    );
  }
}
