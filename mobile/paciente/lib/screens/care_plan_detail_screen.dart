import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../config/paciente_intents.dart';
import '../services/consulta_async_api.dart';
import '../widgets/consulta_async_solicitud_card.dart';
import 'chat_medico_screen.dart';

/// Detalle de un plan de tratamiento activo del paciente.
class CarePlanDetailScreen extends StatefulWidget {
  final int planId;
  final String? authToken;
  final String userId;
  final String userName;
  final Map<String, dynamic>? initialSummary;
  final List<Map<String, dynamic>>? initialSolicitudesActivas;
  final List<Map<String, dynamic>>? initialSolicitudesHistorial;
  final void Function(String intentId, {Map<String, String>? draft})? onStartAssistantFlow;
  final VoidCallback? onSolicitudesChanged;

  const CarePlanDetailScreen({
    Key? key,
    required this.planId,
    this.authToken,
    this.userId = '',
    this.userName = '',
    this.initialSummary,
    this.initialSolicitudesActivas,
    this.initialSolicitudesHistorial,
    this.onStartAssistantFlow,
    this.onSolicitudesChanged,
  }) : super(key: key);

  @override
  State<CarePlanDetailScreen> createState() => _CarePlanDetailScreenState();
}

class _CarePlanDetailScreenState extends State<CarePlanDetailScreen> {
  late CarePlanService _service;
  late final HomePanelApi _homePanelApi = HomePanelApi(
    authToken: widget.authToken,
    appClient: 'paciente-flutter',
  );
  Map<String, dynamic>? _plan;
  List<Map<String, dynamic>> _solicitudesActivas = [];
  List<Map<String, dynamic>> _solicitudesHistorial = [];
  bool _loading = true;
  bool _loadingSolicitudes = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _service = CarePlanService(authToken: widget.authToken);
    _plan = widget.initialSummary;
    _solicitudesActivas = List<Map<String, dynamic>>.from(
      widget.initialSolicitudesActivas ?? const [],
    );
    _solicitudesHistorial = List<Map<String, dynamic>>.from(
      widget.initialSolicitudesHistorial ?? const [],
    );
    _cargar();
  }

  Future<void> _cargar() async {
    setState(() {
      _loading = _plan == null;
      _error = null;
    });
    await Future.wait([_cargarPlan(), _cargarSolicitudes()]);
    if (!mounted) return;
    setState(() => _loading = false);
  }

  Future<void> _cargarPlan() async {
    final r = await _service.fetchById(widget.planId);
    if (!mounted) return;
    if (r['success'] == true && r['data'] is Map) {
      setState(() {
        _plan = Map<String, dynamic>.from(r['data'] as Map);
      });
    } else if (_plan == null) {
      setState(() {
        _error = r['message'] as String? ?? 'No se pudo cargar el tratamiento';
      });
    }
  }

  Future<void> _cargarSolicitudes() async {
    setState(() => _loadingSolicitudes = true);
    try {
      final panel = await _homePanelApi.getPanel(
        sections: 'patient_async_consultations',
      );
      final asyncSec = panel.sectionByKind('patient_async_consultations');
      if (!mounted) return;
      if (asyncSec == null) {
        setState(() => _loadingSolicitudes = false);
        return;
      }
      final activas = _filterForPlan(_asMapList(asyncSec.data['items']));
      final history = asyncSec.data['history'];
      final histItems = history is Map
          ? _filterForPlan(_asMapList(history['items']))
          : <Map<String, dynamic>>[];
      setState(() {
        _solicitudesActivas = activas;
        _solicitudesHistorial = histItems;
        _loadingSolicitudes = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _loadingSolicitudes = false);
    }
  }

  List<Map<String, dynamic>> _asMapList(dynamic raw) {
    if (raw is! List) return [];
    return raw
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }

  List<Map<String, dynamic>> _filterForPlan(List<Map<String, dynamic>> items) {
    return items.where((item) {
      if (!ConsultaAsyncSolicitudCard.perteneceATratamiento(item)) return false;
      final id = ConsultaAsyncSolicitudCard.carePlanIdOf(item);
      return id == null || id == widget.planId;
    }).toList();
  }

  void _abrirChat(Map<String, dynamic> item) {
    final raw = item['encounter_id'];
    final id = raw is int ? raw : int.tryParse(raw?.toString() ?? '');
    if (id == null || id <= 0) return;
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ChatMedicoScreen(
          consultaId: id,
          authToken: widget.authToken,
          userId: widget.userId,
          userName: widget.userName,
          titulo: item['servicio']?.toString() ?? 'Consulta clínica por mensaje',
        ),
      ),
    ).then((_) {
      _cargarSolicitudes();
      widget.onSolicitudesChanged?.call();
    });
  }

  Future<void> _cancelar(Map<String, dynamic> item) async {
    final raw = item['encounter_id'];
    final id = raw is int ? raw : int.tryParse(raw?.toString() ?? '');
    if (id == null || id <= 0) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Retirar solicitud'),
        content: const Text(
          '¿Querés retirar esta solicitud? Solo podés hacerlo mientras el equipo aún no la atiende.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('No')),
          TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Retirar')),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    final res = await ConsultaAsyncApi(authToken: widget.authToken).cancelarComoPaciente(id);
    if (!mounted) return;
    if (res['success'] == true) {
      await _cargarSolicitudes();
      widget.onSolicitudesChanged?.call();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res['message']?.toString() ?? 'No se pudo retirar la solicitud'),
          backgroundColor: IntentPalette.of(UiIntent.danger).base,
        ),
      );
    }
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
                      _buildSolicitudesSection(context),
                      BioSpacing.gapH(BioSpacing.lg),
                      _buildConsultasSeguimiento(context, _plan!),
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

  Widget _buildSolicitudesSection(BuildContext context) {
    if (_loadingSolicitudes &&
        _solicitudesActivas.isEmpty &&
        _solicitudesHistorial.isEmpty) {
      return const BioCard(
        child: Padding(
          padding: EdgeInsets.all(BioSpacing.md),
          child: Center(
            child: SizedBox(
              width: 22,
              height: 22,
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
          ),
        ),
      );
    }
    if (_solicitudesActivas.isEmpty && _solicitudesHistorial.isEmpty) {
      return const SizedBox.shrink();
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (_solicitudesActivas.isNotEmpty) ...[
          Text('Solicitudes del tratamiento', style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.sm),
          ..._solicitudesActivas.map(
            (item) => Padding(
              padding: const EdgeInsets.only(bottom: BioSpacing.sm),
              child: ConsultaAsyncSolicitudCard(
                item: item,
                onAbrirChat: () => _abrirChat(item),
                onCancelar: () => _cancelar(item),
              ),
            ),
          ),
        ],
        if (_solicitudesHistorial.isNotEmpty) ...[
          if (_solicitudesActivas.isNotEmpty) BioSpacing.gapH(BioSpacing.sm),
          Text('Solicitudes anteriores', style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.sm),
          ..._solicitudesHistorial.map(
            (item) => Padding(
              padding: const EdgeInsets.only(bottom: BioSpacing.sm),
              child: ConsultaAsyncSolicitudCard(
                item: item,
                esHistorial: true,
                onAbrirChat: () => _abrirChat(item),
              ),
            ),
          ),
        ],
      ],
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

  Widget _buildConsultasSeguimiento(BuildContext context, Map<String, dynamic> plan) {
    final onStart = widget.onStartAssistantFlow;
    if (onStart == null) {
      return const SizedBox.shrink();
    }
    final raw = plan['seguimientoAcciones'];
    final actions = raw is List
        ? raw.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
        : <Map<String, dynamic>>[];
    if (actions.isEmpty) {
      return const SizedBox.shrink();
    }
    final planId = widget.planId.toString();

    return BioCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Control y seguimiento', style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.sm),
          Text(
            'Elegí qué necesitás sobre este tratamiento.',
            style: BioTypography.bodySm.copyWith(color: context.bio.textMuted),
          ),
          BioSpacing.gapH(BioSpacing.md),
          ...actions.map((action) {
            final code = action['seguimiento_necesidad']?.toString() ?? '';
            final label = action['label']?.toString() ?? code;
            return Padding(
              padding: const EdgeInsets.only(bottom: BioSpacing.sm),
              child: BioButton.outlinePrimary(
                label: label,
                fullWidth: true,
                onPressed: code.isEmpty
                    ? null
                    : () {
                        onStart(
                          PacienteIntents.solicitarAtencion,
                          draft: {
                            'triage_raiz': 'seguimiento_cronico',
                            'intake_tipo': 'seguimiento',
                            'care_plan_id': planId,
                            'seguimiento_necesidad': code,
                          },
                        );
                        Navigator.of(context).pop();
                      },
              ),
            );
          }),
        ],
      ),
    );
  }
}
