import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import 'condition_detail_screen.dart';

/// Listado de condiciones activas del paciente.
class ConditionsListScreen extends StatelessWidget {
  final List<Map<String, dynamic>> conditions;
  final String? authToken;
  final String userId;
  final String userName;
  final int? subjectPersonaId;
  final void Function(String intentId, {Map<String, String>? draft})? onStartAssistantFlow;
  final VoidCallback? onSolicitudesChanged;

  const ConditionsListScreen({
    Key? key,
    required this.conditions,
    this.authToken,
    this.userId = '',
    this.userName = '',
    this.subjectPersonaId,
    this.onStartAssistantFlow,
    this.onSolicitudesChanged,
  }) : super(key: key);

  void _abrirDetalle(BuildContext context, Map<String, dynamic> condition) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ConditionDetailScreen(
          condition: condition,
          authToken: authToken,
          userId: userId,
          userName: userName,
          subjectPersonaId: subjectPersonaId,
          initialSolicitudesActivas: _asMapList(condition['solicitudes_activas']),
          onStartAssistantFlow: onStartAssistantFlow,
          onSolicitudesChanged: onSolicitudesChanged,
        ),
      ),
    );
  }

  List<Map<String, dynamic>> _asMapList(dynamic raw) {
    if (raw is! List) return [];
    return raw
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;

    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: const BioAppBar(title: 'Tus condiciones'),
      body: ListView.separated(
        padding: const EdgeInsets.symmetric(
          horizontal: BioSpacing.lg,
          vertical: BioSpacing.xl,
        ),
        itemCount: conditions.length,
        separatorBuilder: (_, __) => BioSpacing.gapH(BioSpacing.sm),
        itemBuilder: (context, index) {
          final condition = conditions[index];
          final status = condition['clinical_status']?.toString();
          final intent = ConditionUi.intentForStatus(status);
          final subtitle = ConditionUi.subtitleForList(condition);
          final pendientes = condition['solicitudes_pendientes_count'];
          final pendientesCount = pendientes is int
              ? pendientes
              : int.tryParse(pendientes?.toString() ?? '') ?? 0;

          return BioCard.intent(
            intent: intent,
            onTap: () => _abrirDetalle(context, condition),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        ConditionUi.label(condition),
                        style: BioTypography.title,
                      ),
                      if (subtitle != null) ...[
                        BioSpacing.gapH(BioSpacing.xs),
                        Text(
                          subtitle,
                          style: BioTypography.bodySm.copyWith(
                            color: context.bio.textMuted,
                          ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                      BioSpacing.gapH(BioSpacing.xs),
                      Wrap(
                        spacing: BioSpacing.xs,
                        runSpacing: BioSpacing.xs,
                        children: [
                          BioBadge(
                            label: ConditionUi.statusLabel(condition),
                            intent: intent,
                          ),
                          if (pendientesCount > 0)
                            BioBadge.info(
                              pendientesCount == 1
                                  ? '1 solicitud'
                                  : '$pendientesCount solicitudes',
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
                Icon(Icons.chevron_right, color: context.bio.textMuted),
              ],
            ),
          );
        },
      ),
    );
  }
}
