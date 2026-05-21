import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import 'care_plan_detail_screen.dart';

/// Listado de planes de tratamiento activos del paciente.
class CarePlansListScreen extends StatelessWidget {
  final List<Map<String, dynamic>> plans;
  final String? authToken;

  const CarePlansListScreen({
    Key? key,
    required this.plans,
    this.authToken,
  }) : super(key: key);

  void _abrirDetalle(BuildContext context, Map<String, dynamic> plan) {
    final id = CarePlanUi.idFromMap(plan);
    if (id == null) return;
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => CarePlanDetailScreen(
          planId: id,
          authToken: authToken,
          initialSummary: plan,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;

    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: const BioAppBar(title: 'Tus tratamientos'),
      body: ListView.separated(
        padding: const EdgeInsets.symmetric(
          horizontal: BioSpacing.lg,
          vertical: BioSpacing.xl,
        ),
        itemCount: plans.length,
        separatorBuilder: (_, __) => BioSpacing.gapH(BioSpacing.sm),
        itemBuilder: (context, index) {
          final plan = plans[index];
          final status = plan['status']?.toString();
          final intent = CarePlanUi.intentForStatus(status);
          final subtitle = CarePlanUi.subtitleForList(plan);

          return BioCard.intent(
            intent: intent,
            onTap: () => _abrirDetalle(context, plan),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        CarePlanUi.categoryLabel(plan),
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
                      BioBadge(
                        label: CarePlanUi.statusLabel(plan),
                        intent: intent,
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
