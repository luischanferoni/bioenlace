import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';
import '../ui/bio_card.dart';
import 'home_panel_api.dart';

/// Grupo de KPIs del panel de inicio (`kind: staff_kpi_group`).
class HomePanelKpiGroup {
  final String title;
  final List<HomePanelKpiItem> items;

  const HomePanelKpiGroup({
    required this.title,
    required this.items,
  });

  factory HomePanelKpiGroup.fromSectionData(Map<String, dynamic> data) {
    final rawItems = data['items'] as List<dynamic>? ?? [];
    final items = <HomePanelKpiItem>[];
    for (final row in rawItems) {
      if (row is! Map) continue;
      final map = Map<String, dynamic>.from(row);
      final label = (map['label'] as String?)?.trim() ?? '';
      final value = map['value']?.toString() ?? '—';
      if (label.isEmpty) continue;
      items.add(HomePanelKpiItem(label: label, value: value));
    }
    return HomePanelKpiGroup(
      title: (data['title'] as String?)?.trim() ?? 'Indicadores',
      items: items,
    );
  }
}

class HomePanelKpiItem {
  final String label;
  final String value;

  const HomePanelKpiItem({required this.label, required this.value});
}

/// Parsea secciones KPI del panel (`staff_kpi_group` y opcionalmente indicadores de guardia).
List<HomePanelKpiGroup> homePanelKpiGroupsFromResponse(HomePanelResponse panel) {
  final groups = <HomePanelKpiGroup>[];
  for (final section in panel.sectionsByKind('staff_kpi_group')) {
    final group = HomePanelKpiGroup.fromSectionData(section.data);
    if (group.items.isNotEmpty) {
      groups.add(group);
    }
  }

  final guardia = panel.sectionByKind('emergency_indicators');
  if (guardia != null) {
    final group = _guardiaIndicatorsKpiGroup(guardia.data);
    if (group != null) {
      groups.add(group);
    }
  }

  return groups;
}

HomePanelKpiGroup? _guardiaIndicatorsKpiGroup(Map<String, dynamic> d) {
  final tiempos = d['tiempos_hoy'] is Map
      ? Map<String, dynamic>.from(d['tiempos_hoy'] as Map)
      : <String, dynamic>{};
  final minMedico = tiempos['minutos_a_medico'];

  final items = <HomePanelKpiItem>[
    HomePanelKpiItem(
      label: 'Activos',
      value: '${d['activos'] ?? 0}',
    ),
    HomePanelKpiItem(
      label: 'Sin triage',
      value: '${d['sin_triage'] ?? 0}',
    ),
    HomePanelKpiItem(
      label: 'Ingresos hoy',
      value: '${d['ingresos_hoy'] ?? 0}',
    ),
    HomePanelKpiItem(
      label: 'SLA incumplidos',
      value: '${d['sla_incumplidos_tablero'] ?? 0}',
    ),
    if (minMedico != null)
      HomePanelKpiItem(
        label: 'Mediana a médico',
        value: '$minMedico min',
      ),
  ];

  if (items.isEmpty) return null;
  return HomePanelKpiGroup(title: 'Guardia', items: items);
}

/// Banner de contexto operativo (`kind: staff_session_context`).
class HomePanelStaffContextBanner extends StatelessWidget {
  final Map<String, dynamic> data;

  const HomePanelStaffContextBanner({super.key, required this.data});

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final efector = (data['nombre_efector'] as String?)?.trim();
    final servicio = (data['nombre_servicio'] as String?)?.trim();
    final encounter = (data['encounter_class'] as String?)?.trim();
    final hint = (data['hint'] as String?)?.trim();

    return BioCard(
      margin: const EdgeInsets.fromLTRB(
        BioSpacing.lg,
        BioSpacing.md,
        BioSpacing.lg,
        BioSpacing.sm,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (efector != null && efector.isNotEmpty)
            Text(efector, style: BioTypography.title),
          if (servicio != null && servicio.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              'Servicio: $servicio',
              style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
            ),
          ],
          if (encounter != null && encounter.isNotEmpty)
            Text(
              'Contexto: $encounter',
              style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
            ),
          if (hint != null && hint.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Text(
              hint,
              style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
            ),
          ],
        ],
      ),
    );
  }
}

/// Una o más tarjetas de KPI apiladas (reutilizable web staff / móvil médico).
class HomePanelKpiGroupsList extends StatelessWidget {
  final List<HomePanelKpiGroup> groups;

  const HomePanelKpiGroupsList({super.key, required this.groups});

  @override
  Widget build(BuildContext context) {
    if (groups.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (var i = 0; i < groups.length; i++) ...[
          _KpiGroupCard(group: groups[i]),
          if (i < groups.length - 1) BioSpacing.gapH(BioSpacing.sm),
        ],
      ],
    );
  }
}

class _KpiGroupCard extends StatelessWidget {
  final HomePanelKpiGroup group;

  const _KpiGroupCard({required this.group});

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;

    return BioCard(
      margin: const EdgeInsets.symmetric(horizontal: BioSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(group.title, style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.md),
          Wrap(
            spacing: BioSpacing.sm,
            runSpacing: BioSpacing.sm,
            children: group.items
                .map(
                  (item) => _KpiTile(
                    label: item.label,
                    value: item.value,
                    tokens: tokens,
                  ),
                )
                .toList(),
          ),
        ],
      ),
    );
  }
}

class _KpiTile extends StatelessWidget {
  final String label;
  final String value;
  final BioTokens tokens;

  const _KpiTile({
    required this.label,
    required this.value,
    required this.tokens,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 148,
      padding: const EdgeInsets.symmetric(
        horizontal: BioSpacing.md,
        vertical: BioSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: tokens.paperBackground,
        borderRadius: BioRadius.all(BioRadius.sm),
        border: BioBorder.all(BorderWidth.thin, tokens.paperBorderDefault),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: BioTypography.caption.copyWith(color: tokens.textMuted),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          BioSpacing.gapH(BioSpacing.xs),
          Text(value, style: BioTypography.h3),
        ],
      ),
    );
  }
}
