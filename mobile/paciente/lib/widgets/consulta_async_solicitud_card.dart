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
    if (group == 'consultas' || group == 'condicion') return false;
    final raw = item['care_plan_id'];
    final id = raw is int ? raw : int.tryParse(raw?.toString() ?? '') ?? 0;
    return id > 0;
  }

  static bool perteneceACondicion(Map<String, dynamic> item) {
    final group = item['ui_group']?.toString().trim();
    if (group == 'condicion') return true;
    if (group == 'consultas' || group == 'tratamiento') return false;
    final codigo = item['condition_codigo']?.toString().trim() ?? '';
    final ref = item['condition_ref']?.toString().trim() ?? '';
    return codigo.isNotEmpty || ref.isNotEmpty;
  }

  static int? carePlanIdOf(Map<String, dynamic> item) {
    final raw = item['care_plan_id'];
    if (raw is int) return raw > 0 ? raw : null;
    final id = int.tryParse(raw?.toString() ?? '') ?? 0;
    return id > 0 ? id : null;
  }

  static String? conditionCodigoOf(Map<String, dynamic> item) {
    final c = item['condition_codigo']?.toString().trim();
    return (c != null && c.isNotEmpty) ? c : null;
  }

  static Widget previewText(String preview) {
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
    final solicitudTipo = item['solicitud_tipo']?.toString().trim() ?? '';
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
          if (solicitudTipo.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            BioBadge.info(solicitudTipo),
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
