import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

/// Detalle de un plan de tratamiento activo del paciente.
class CarePlanDetailScreen extends StatefulWidget {
  final int planId;
  final String? authToken;
  final Map<String, dynamic>? initialSummary;

  const CarePlanDetailScreen({
    Key? key,
    required this.planId,
    this.authToken,
    this.initialSummary,
  }) : super(key: key);

  @override
  State<CarePlanDetailScreen> createState() => _CarePlanDetailScreenState();
}

class _CarePlanDetailScreenState extends State<CarePlanDetailScreen> {
  late CarePlanService _service;
  Map<String, dynamic>? _plan;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _service = CarePlanService(authToken: widget.authToken);
    _plan = widget.initialSummary;
    _cargar();
  }

  Future<void> _cargar() async {
    setState(() {
      _loading = _plan == null;
      _error = null;
    });
    final r = await _service.fetchById(widget.planId);
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (r['success'] == true && r['data'] is Map) {
        _plan = Map<String, dynamic>.from(r['data'] as Map);
      } else if (_plan == null) {
        _error = r['message'] as String? ?? 'No se pudo cargar el tratamiento';
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;

    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: const BioAppBar(title: 'Tu tratamiento'),
      body: _loading && _plan == null
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _plan == null
              ? Padding(
                  padding: BioSpacing.pageAll,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      BioAlert.danger(message: _error!),
                      BioSpacing.gapH(BioSpacing.lg),
                      BioButton.primary(
                        label: 'Reintentar',
                        onPressed: _cargar,
                        icon: Icons.refresh,
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _cargar,
                  child: ListView(
                    padding: const EdgeInsets.symmetric(
                      horizontal: BioSpacing.lg,
                      vertical: BioSpacing.xl,
                    ),
                    children: [
                      if (_error != null) ...[
                        BioAlert.danger(message: _error!),
                        BioSpacing.gapH(BioSpacing.md),
                      ],
                      _buildResumen(context, _plan!),
                      BioSpacing.gapH(BioSpacing.lg),
                      CarePlanReminderPlanPanel(
                        carePlanId: widget.planId,
                        authToken: widget.authToken,
                      ),
                    ],
                  ),
                ),
    );
  }

  Widget _buildResumen(BuildContext context, Map<String, dynamic> plan) {
    final status = plan['status']?.toString();
    final intent = CarePlanUi.intentForStatus(status);
    final lines = CarePlanUi.activitySummaries(plan);
    final title = plan['title']?.toString().trim();
    final description = plan['description']?.toString().trim();

    return BioCard.intent(
      intent: intent,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.medical_services_outlined, color: context.bio.textMuted, size: 24),
              BioSpacing.gapW(BioSpacing.sm),
              Expanded(
                child: Text(
                  CarePlanUi.categoryLabel(plan),
                  style: BioTypography.h3,
                ),
              ),
              BioBadge(
                label: CarePlanUi.statusLabel(plan),
                intent: intent,
              ),
            ],
          ),
          if (title != null && title.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Text(title, style: BioTypography.title),
          ],
          if (description != null && description.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Text(description, style: BioTypography.body),
          ],
          if (lines.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.lg),
            Text('Indicaciones', style: BioTypography.title),
            BioSpacing.gapH(BioSpacing.sm),
            ...lines.map(
              (line) => Padding(
                padding: const EdgeInsets.only(bottom: BioSpacing.xs),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('• ', style: BioTypography.body),
                    Expanded(child: Text(line, style: BioTypography.body)),
                  ],
                ),
              ),
            ),
          ] else ...[
            BioSpacing.gapH(BioSpacing.md),
            Text(
              'No hay indicaciones cargadas en este plan.',
              style: BioTypography.bodySm.copyWith(color: context.bio.textMuted),
            ),
          ],
        ],
      ),
    );
  }
}
