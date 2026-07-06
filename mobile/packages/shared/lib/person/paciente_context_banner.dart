import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

/// Banner de estado de verificación de domicilio / provincia de contexto.
class PacienteContextBanner extends StatelessWidget {
  final VoidCallback? onConfigurarProvincia;

  /// Kinds de `banner.kind` que no se muestran en esta superficie (p. ej. en chat).
  final Set<String> hiddenKinds;

  const PacienteContextBanner({
    super.key,
    this.onConfigurarProvincia,
    this.hiddenKinds = const {},
  });

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: PacienteContextScope.instance,
      builder: (context, _) {
        final state = PacienteContextScope.instance.state;
        final banner = state.banner;
        if (banner == null) return const SizedBox.shrink();

        final kind = banner['kind']?.toString() ?? '';
        if (hiddenKinds.contains(kind)) return const SizedBox.shrink();
        final message = banner['message']?.toString() ?? '';
        if (message.isEmpty) return const SizedBox.shrink();

        final severity = banner['severity']?.toString() ?? 'info';
        final intent = severity == 'warning'
            ? UiIntent.warning
            : UiIntent.primary;
        final palette = IntentPalette.of(intent);

        return Material(
          color: palette.softBg,
          child: Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: BioSpacing.md,
              vertical: BioSpacing.sm,
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  kind == 'domicilio_requiere_provincia'
                      ? Icons.location_off_outlined
                      : Icons.hourglass_top_outlined,
                  color: palette.base,
                  size: 22,
                ),
                BioSpacing.gapW(BioSpacing.sm),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        message,
                        style: BioTypography.bodySm.copyWith(
                          color: palette.softFg,
                        ),
                      ),
                      if (kind == 'domicilio_requiere_provincia' &&
                          onConfigurarProvincia != null) ...[
                        BioSpacing.gapH(BioSpacing.xs),
                        BioButton(
                          label: 'Elegir provincia de contexto',
                          intent: intent,
                          variant: BioButtonVariant.soft,
                          size: BioButtonSize.sm,
                          onPressed: onConfigurarProvincia,
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
