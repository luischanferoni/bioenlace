import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../config/paciente_intents.dart';
import '../services/consulta_async_api.dart';
import '../widgets/consulta_async_solicitud_card.dart';
import 'chat_medico_screen.dart';

/// Detalle liviano de una condición activa (acciones + async anidadas).
class ConditionDetailScreen extends StatefulWidget {
  final Map<String, dynamic> condition;
  final String? authToken;
  final String userId;
  final String userName;
  final int? subjectPersonaId;
  final List<Map<String, dynamic>>? initialSolicitudesActivas;
  final void Function(String intentId, {Map<String, String>? draft})? onStartAssistantFlow;
  final VoidCallback? onSolicitudesChanged;

  const ConditionDetailScreen({
    Key? key,
    required this.condition,
    this.authToken,
    this.userId = '',
    this.userName = '',
    this.subjectPersonaId,
    this.initialSolicitudesActivas,
    this.onStartAssistantFlow,
    this.onSolicitudesChanged,
  }) : super(key: key);

  @override
  State<ConditionDetailScreen> createState() => _ConditionDetailScreenState();
}

class _ConditionDetailScreenState extends State<ConditionDetailScreen> {
  late final HomePanelApi _homePanelApi = HomePanelApi(
    authToken: widget.authToken,
    appClient: 'paciente-flutter',
  );
  late Map<String, dynamic> _condition;
  List<Map<String, dynamic>> _solicitudesActivas = [];
  bool _loadingSolicitudes = false;

  @override
  void initState() {
    super.initState();
    _condition = Map<String, dynamic>.from(widget.condition);
    _solicitudesActivas = List<Map<String, dynamic>>.from(
      widget.initialSolicitudesActivas ??
          _asMapList(widget.condition['solicitudes_activas']),
    );
    _cargarSolicitudes();
  }

  Future<void> _cargarSolicitudes() async {
    setState(() => _loadingSolicitudes = true);
    try {
      final panel = await _homePanelApi.getPanel(
        sections: 'conditions_active',
        subjectPersonaId: widget.subjectPersonaId,
      );
      final sec = panel.sectionByKind('patient_conditions_active');
      if (!mounted) return;
      if (sec == null) {
        setState(() => _loadingSolicitudes = false);
        return;
      }
      final items = _asMapList(sec.data['items']);
      final mine = _findMine(items);
      if (mine != null) {
        setState(() {
          _condition = mine;
          _solicitudesActivas = _asMapList(mine['solicitudes_activas']);
          _loadingSolicitudes = false;
        });
        return;
      }
      setState(() => _loadingSolicitudes = false);
    } catch (_) {
      if (!mounted) return;
      setState(() => _loadingSolicitudes = false);
    }
  }

  Map<String, dynamic>? _findMine(List<Map<String, dynamic>> items) {
    final myId = ConditionUi.idFromMap(_condition);
    final myCodigo = ConditionUi.codigo(_condition)?.toUpperCase();
    for (final item in items) {
      final id = ConditionUi.idFromMap(item);
      if (myId != null && id == myId) return item;
      final codigo = ConditionUi.codigo(item)?.toUpperCase();
      if (myCodigo != null && codigo == myCodigo) return item;
    }
    return null;
  }

  List<Map<String, dynamic>> _asMapList(dynamic raw) {
    if (raw is! List) return [];
    return raw
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
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
    final title = ConditionUi.label(_condition);

    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: BioAppBar(title: title),
      body: RefreshIndicator(
        onRefresh: _cargarSolicitudes,
        child: ListView(
          padding: const EdgeInsets.symmetric(
            horizontal: BioSpacing.lg,
            vertical: BioSpacing.xl,
          ),
          children: [
            _buildResumen(context),
            BioSpacing.gapH(BioSpacing.lg),
            _buildSolicitudesSection(context),
            BioSpacing.gapH(BioSpacing.lg),
            _buildAcciones(context),
          ],
        ),
      ),
    );
  }

  Widget _buildResumen(BuildContext context) {
    final status = _condition['clinical_status']?.toString();
    final intent = ConditionUi.intentForStatus(status);
    final codigo = ConditionUi.codigo(_condition);
    final protocol = _condition['protocol_title']?.toString().trim();

    return BioCard.intent(
      intent: intent,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.healing_outlined, color: context.bio.textMuted, size: 24),
              BioSpacing.gapW(BioSpacing.sm),
              Expanded(
                child: Text(ConditionUi.label(_condition), style: BioTypography.h3),
              ),
              BioBadge(
                label: ConditionUi.statusLabel(_condition),
                intent: intent,
              ),
            ],
          ),
          if (codigo != null) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Text(codigo, style: BioTypography.bodySm.copyWith(color: context.bio.textMuted)),
          ],
          if (protocol != null && protocol.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Text('Protocolo: $protocol', style: BioTypography.body),
          ],
        ],
      ),
    );
  }

  Widget _buildSolicitudesSection(BuildContext context) {
    if (_loadingSolicitudes && _solicitudesActivas.isEmpty) {
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
    if (_solicitudesActivas.isEmpty) {
      return const SizedBox.shrink();
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Consultas por mensaje', style: BioTypography.title),
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
    );
  }

  Widget _buildAcciones(BuildContext context) {
    final onStart = widget.onStartAssistantFlow;
    if (onStart == null) {
      return const SizedBox.shrink();
    }
    final raw = _condition['seguimientoAcciones'];
    final actions = raw is List
        ? raw.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
        : <Map<String, dynamic>>[];
    if (actions.isEmpty) {
      return const SizedBox.shrink();
    }
    final anchor = ConditionUi.controlHubAnchor(_condition);
    final codigo = ConditionUi.codigo(_condition) ?? '';
    final conditionId = ConditionUi.idFromMap(_condition)?.toString() ?? '';
    final conditionLabel = ConditionUi.label(_condition);

    return BioCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Control y seguimiento', style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.sm),
          Text(
            'Elegí qué necesitás sobre esta condición.',
            style: BioTypography.bodySm.copyWith(color: context.bio.textMuted),
          ),
          BioSpacing.gapH(BioSpacing.md),
          ...actions.map((action) {
            final code = action['code']?.toString() ?? '';
            final label = action['label']?.toString() ?? code;
            final draftExtra = action['draft'] is Map
                ? Map<String, dynamic>.from(action['draft'] as Map)
                : <String, dynamic>{};
            return Padding(
              padding: const EdgeInsets.only(bottom: BioSpacing.sm),
              child: BioButton.outlinePrimary(
                label: label,
                fullWidth: true,
                onPressed: code.isEmpty && draftExtra.isEmpty
                    ? null
                    : () {
                        final draft = <String, String>{
                          'triage_raiz': 'seguimiento_cronico',
                          'control_hub_anchor': anchor,
                          'control_hub_kind': 'condition',
                          if (codigo.isNotEmpty) 'condition_codigo': codigo,
                          if (conditionId.isNotEmpty) 'condition_ref': conditionId,
                          'condition_accion': code,
                          '_label_condition': 'Condición: $conditionLabel',
                          '_label_condition_accion': label,
                          '_prefill_walk': 'condition_seguimiento',
                        };
                        draftExtra.forEach((k, v) {
                          final s = v?.toString().trim() ?? '';
                          if (s.isNotEmpty) draft[k] = s;
                        });
                        onStart(PacienteIntents.solicitarAtencion, draft: draft);
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
