import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

/// Card de solicitud/consulta async (bandeja paciente).
class ConsultaAsyncSolicitudCard extends StatelessWidget {
  const ConsultaAsyncSolicitudCard({
    super.key,
    required this.item,
    this.esHistorial = false,
    this.onAbrirChat,
    this.onCancelar,
  });

  final Map<String, dynamic> item;
  final bool esHistorial;
  final VoidCallback? onAbrirChat;
  final VoidCallback? onCancelar;

  static bool perteneceATratamiento(Map<String, dynamic> item) {
    final group = item['ui_group']?.toString().trim();
    if (group == 'tratamiento') return true;
    if (group == 'consultas') return false;
    final raw = item['care_plan_id'];
    final id = raw is int ? raw : int.tryParse(raw?.toString() ?? '') ?? 0;
    return id > 0;
  }

  static int? carePlanIdOf(Map<String, dynamic> item) {
    final raw = item['care_plan_id'];
    if (raw is int) return raw > 0 ? raw : null;
    final id = int.tryParse(raw?.toString() ?? '') ?? 0;
    return id > 0 ? id : null;
  }

  static Widget previewText(String preview) {
    const prefixes = [
      'Solicitud de renovación',
      'Solicitud de ajuste',
    ];
    for (final prefix in prefixes) {
      if (preview.startsWith(prefix)) {
        return Text.rich(
          TextSpan(
            style: BioTypography.bodySm,
            children: [
              TextSpan(
                text: prefix,
                style: const TextStyle(decoration: TextDecoration.underline),
              ),
              TextSpan(text: preview.substring(prefix.length)),
            ],
          ),
          maxLines: 3,
          overflow: TextOverflow.ellipsis,
        );
      }
    }
    return Text(
      preview,
      style: BioTypography.bodySm,
      maxLines: 3,
      overflow: TextOverflow.ellipsis,
    );
  }

  @override
  Widget build(BuildContext context) {
    final preview = item['reason_preview']?.toString().trim() ?? '';
    final servicio = item['servicio']?.toString().trim() ?? '';
    final resolucion = item['resolution_label']?.toString().trim() ?? '';
    final estado = item['status_label']?.toString().trim() ??
        item['status']?.toString().trim() ??
        '';
    final createdAt = item['created_at']?.toString().trim() ?? '';
    final accionesRaw = item['acciones'];
    final acciones = accionesRaw is Map
        ? Map<String, dynamic>.from(accionesRaw)
        : <String, dynamic>{};
    final abrirChat = acciones['abrir_chat'] == true && onAbrirChat != null;
    final cancelar = acciones['cancelar'] == true && onCancelar != null;

    return BioCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  servicio.isNotEmpty ? servicio : 'Consulta clínica',
                  style: BioTypography.title,
                ),
              ),
              if (estado.isNotEmpty) BioBadge.neutral(estado),
            ],
          ),
          if (createdAt.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(createdAt, style: BioTypography.caption),
          ],
          if (preview.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            previewText(preview),
          ],
          if (esHistorial && resolucion.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text('Resolución: $resolucion', style: BioTypography.bodySm),
          ],
          if (abrirChat || cancelar) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Wrap(
              spacing: BioSpacing.sm,
              runSpacing: BioSpacing.xs,
              children: [
                if (abrirChat)
                  BioButton.primary(
                    label: esHistorial ? 'Ver conversación' : 'Ver mensajes',
                    size: BioButtonSize.sm,
                    icon: Icons.chat_bubble_outline,
                    onPressed: onAbrirChat,
                  ),
                if (cancelar)
                  BioButton.outlineDanger(
                    label: 'Retirar solicitud',
                    size: BioButtonSize.sm,
                    icon: Icons.delete_outline,
                    onPressed: onCancelar,
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
