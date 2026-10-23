import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../../services/historia_clinica_service.dart';

/// Contexto del episodio (tipo, estado, motivo, triage) y sus acciones.
class EpisodioBanner extends StatelessWidget {
  const EpisodioBanner({super.key, required this.ctx, required this.onAction});

  final ContextoEpisodioHistoria? ctx;
  final ValueChanged<EpisodioAccionHistoria> onAction;

  @override
  Widget build(BuildContext context) {
    final ctx = this.ctx;
    final tituloTipo = ctx?.tipoLabel ?? 'Episodio';
    final tituloParts = <String>[tituloTipo];
    if (ctx?.ingresoAt != null && ctx!.ingresoAt!.isNotEmpty) {
      tituloParts.add('Ingreso ${formatEpisodioFecha(ctx.ingresoAt)}');
    }

    final estado = ctx?.estadoLabel ?? ctx?.estado ?? 'En curso';
    final motivo = (ctx?.motivo != null && ctx!.motivo!.isNotEmpty)
        ? ctx.motivo!
        : 'Sin motivo registrado';
    final triageScale =
        (ctx?.triageScale != null && ctx!.triageScale!.isNotEmpty)
        ? ctx.triageScale!
        : null;
    final triageLevel = ctx?.triageLevel;
    final triageLabel =
        ctx?.triageLevelLabel ??
        (triageLevel != null ? 'Nivel $triageLevel' : null);

    Widget metaCell(String label, String value) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label.toUpperCase(),
            style: BioTypography.caption.copyWith(
              color: context.bio.textMuted,
              fontWeight: FontWeight.w600,
            ),
          ),
          Text(value, style: BioTypography.body),
        ],
      );
    }

    Widget triageBadge() {
      final rawColor = ctx?.triageLevelColor ?? '';
      final bg = RegExp(r'^#[0-9a-fA-F]{6}$').hasMatch(rawColor)
          ? Color(int.parse('FF${rawColor.substring(1)}', radix: 16))
          : Colors.grey.shade300;
      final fg = bg.computeLuminance() < 0.5 ? Colors.white : Colors.black87;
      return Container(
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(BioRadius.xs),
        ),
        padding: const EdgeInsets.symmetric(
          horizontal: BioSpacing.sm,
          vertical: 2,
        ),
        child: Text(
          triageLabel ?? 'Triage',
          style: BioTypography.caption.copyWith(
            color: fg,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
    }

    return BioCard.intent(
      intent: ctx?.intent ?? UiIntent.neutral,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (triageLevel != null) ...[
            Wrap(
              spacing: BioSpacing.xs,
              runSpacing: BioSpacing.xs,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                triageBadge(),
                if (triageScale != null)
                  Text(
                    triageScale,
                    style: BioTypography.caption.copyWith(
                      color: context.bio.textMuted,
                    ),
                  ),
              ],
            ),
            BioSpacing.gapH(BioSpacing.sm),
          ],
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: metaCell('Episodio', tituloParts.join(' · '))),
              BioSpacing.gapW(BioSpacing.md),
              Expanded(child: metaCell('Estado', estado)),
            ],
          ),
          BioSpacing.gapH(BioSpacing.sm),
          metaCell('Motivo / ingreso', motivo),
          if (ctx != null && ctx.acciones.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Wrap(
              spacing: BioSpacing.xs,
              runSpacing: BioSpacing.xs,
              children: [
                for (final a in ctx.acciones)
                  BioButton(
                    label: a.label,
                    intent: a.intent,
                    variant: a.variant,
                    size: BioButtonSize.sm,
                    onPressed: () => onAction(a),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
