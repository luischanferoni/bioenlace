// lib/screens/acciones_screen.dart
import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../navigation/native_screen_router.dart';

class AccionesScreen extends StatefulWidget {
  final String userId;
  final String userName;
  final String? authToken;

  const AccionesScreen({
    super.key,
    required this.userId,
    required this.userName,
    this.authToken,
  });

  @override
  State<AccionesScreen> createState() => _AccionesScreenState();
}

class _AccionesScreenState extends State<AccionesScreen> {
  final TextEditingController _queryController = TextEditingController();
  late final AsistenteService _asistente;

  bool _isLoading = false;
  String? _responseText;
  List<Map<String, dynamic>> _interactiveButtons = [];
  List<AtajoCategoria> _atajos = [];
  @override
  void initState() {
    super.initState();
    _asistente = AsistenteService(
      userId: widget.userId,
      authToken: widget.authToken,
      appClient: 'medico-flutter',
    );
    _loadAtajos();
  }

  @override
  void dispose() {
    _queryController.dispose();
    super.dispose();
  }

  Future<void> _loadAtajos() async {
    final cats = await _asistente.cargarAtajos();
    if (!mounted) return;
    setState(() => _atajos = cats);
  }

  Future<void> _sendQuery({String? actionId, String? contentOverride}) async {
    final text = (contentOverride ?? _queryController.text).trim();
    if (text.isEmpty && (actionId == null || actionId.isEmpty)) {
      return;
    }

    setState(() {
      _isLoading = true;
      _responseText = null;
      _interactiveButtons = [];
    });

    final res = await _asistente.procesarInteraccion(
      text,
      actionId: actionId,
    );

    if (!mounted) return;

    if (res['success'] != true) {
      setState(() {
        _isLoading = false;
        _responseText = res['message']?.toString() ?? 'Error en la consulta';
      });
      return;
    }

    final data = res['data'];
    if (data is! Map) {
      setState(() {
        _isLoading = false;
        _responseText = 'Respuesta inválida';
      });
      return;
    }

    final envelope = Map<String, dynamic>.from(data);
    _applyEnvelope(envelope);
    setState(() => _isLoading = false);
  }

  void _applyEnvelope(Map<String, dynamic> envelope) {
    final kind = envelope['kind']?.toString() ?? '';
    _responseText = envelope['text']?.toString().trim();
    if (_responseText == null || _responseText!.isEmpty) {
      _responseText = 'Consulta procesada';
    }

    _interactiveButtons = [];
    if (kind == 'interactive') {
      final buttons = envelope['buttons'];
      if (buttons is List) {
        for (final b in buttons) {
          if (b is! Map) continue;
          final m = Map<String, dynamic>.from(b);
          final iid = m['intent_id']?.toString() ?? '';
          if (iid.isEmpty) continue;
          _interactiveButtons.add({
            'label': m['label']?.toString() ?? iid,
            'intent_id': iid,
          });
        }
      }
      return;
    }

    if (kind == 'flow') {
      final flow = AssistantFlowView.fromEnvelope(envelope);
      if (flow == null) return;

      final manifest = flow.manifest;
      if (manifest != null && manifest['action_name'] != null) {
        final name = manifest['action_name']?.toString().trim() ?? '';
        if (name.isNotEmpty) {
          _responseText = '$_responseText\n\nFlujo: $name';
        }
      }

      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        _openFlowStep(flow, envelope);
      });
    }
  }

  Future<void> _openFlowStep(AssistantFlowView flow, Map<String, dynamic> envelope) async {
    final openUi = flow.openUi;
    if (openUi == null) {
      if (flow.flowSubmit != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Confirmá el envío desde el paso anterior del flujo.')),
        );
      }
      return;
    }

    final actionId = openUi['action_id']?.toString() ?? '';
    final co = openUi['client_open'];
    if (co is! Map || actionId.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('No puedo abrir el paso ($actionId).')),
      );
      return;
    }

    final clientOpen = Map<String, dynamic>.from(co);
    final kindCo = clientOpen['kind']?.toString();

    if (kindCo == 'ui_json') {
      final api = clientOpen['api'];
      final route = api is Map ? api['route']?.toString() : null;
      if (route == null || route.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Pantalla sin ruta API.')),
        );
        return;
      }
      await showModalBottomSheet<void>(
        context: context,
        useSafeArea: true,
        isScrollControlled: true,
        builder: (sheetCtx) => SizedBox(
          height: MediaQuery.of(context).size.height * 0.92,
          child: UiJsonScreen(
            apiAbsoluteUrl: resolveApiAbsoluteUrl(route),
            authToken: widget.authToken,
            appClient: 'bioenlace-medico',
            title: actionId,
            embedded: true,
            enableFlowChainAutoAdvance: true,
            onDraftDelta: (draftDelta) async {
              _asistente.draft = {
                ..._asistente.draft,
                ...Map<String, dynamic>.from(draftDelta),
              };
              final res = await _asistente.procesarInteraccion('');
              if (!mounted || res['success'] != true) return;
              final data = res['data'];
              if (data is Map) {
                if (sheetCtx.mounted) Navigator.of(sheetCtx).pop();
                _applyEnvelope(Map<String, dynamic>.from(data));
                if (mounted) setState(() {});
              }
            },
          ),
        ),
      );
      return;
    }

    if (kindCo == 'native') {
      final mobile = clientOpen['mobile'];
      final screenId = (mobile is Map ? mobile['screen_id'] : null)?.toString() ??
          clientOpen['screen_id']?.toString();
      if (screenId != null && screenId.isNotEmpty) {
        await NativeScreenRouter.open(
          context,
          screenId: screenId,
          title: actionId,
          args: {'draft': _asistente.draft, 'envelope': envelope},
        );
        return;
      }
    }

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Tipo de pantalla no soportado en móvil médico.')),
    );
  }

  Future<void> _onAtajoTap(AtajoItem item) async {
    _asistente.resetFlow();
    _queryController.clear();
    await _sendQuery(actionId: item.intentId, contentOverride: '');
  }

  Future<void> _onInteractiveButton(Map<String, dynamic> btn) async {
    final intentId = btn['intent_id']?.toString() ?? '';
    if (intentId.isEmpty) return;
    _asistente.resetFlow();
    _queryController.clear();
    await _sendQuery(actionId: intentId, contentOverride: '');
  }

  void _clearFlow() {
    _asistente.resetFlow();
    setState(() {
      _interactiveButtons = [];
      _responseText = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final inFlow = _asistente.currentIntentId != null &&
        _asistente.currentIntentId!.isNotEmpty;

    return Scaffold(
      appBar: const BioAppBar(title: 'Acciones'),
      body: Container(
        color: tokens.paperBackground,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(BioSpacing.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Hola, ${widget.userName}',
                style: BioTypography.h3,
                textAlign: TextAlign.center,
              ),
              BioSpacing.gapH(BioSpacing.xs),
              Text(
                '¿En qué puedo ayudarte?',
                style: BioTypography.body.copyWith(color: tokens.textMuted),
                textAlign: TextAlign.center,
              ),
              if (inFlow) ...[
                BioSpacing.gapH(BioSpacing.sm),
                Row(
                  children: [
                    Expanded(
                      child: BioChip(
                        label: 'Flujo: ${_asistente.currentIntentId}',
                        selected: true,
                      ),
                    ),
                    TextButton(onPressed: _clearFlow, child: const Text('Salir')),
                  ],
                ),
              ],
              BioSpacing.gapH(BioSpacing.xl),
              BioInput(
                controller: _queryController,
                maxLines: 4,
                hint:
                    'Ej.: "Quiero ver agenda" o "Buscar paciente"',
                enabled: !_isLoading,
              ),
              BioSpacing.gapH(BioSpacing.md),
              BioButton.primary(
                label: _isLoading ? 'Enviando…' : 'Enviar',
                icon: Icons.send,
                size: BioButtonSize.lg,
                fullWidth: true,
                loading: _isLoading,
                onPressed: _isLoading ? null : () => _sendQuery(),
              ),
              if (_responseText != null) ...[
                BioSpacing.gapH(BioSpacing.lg),
                BioCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(_responseText!, style: BioTypography.bodySm),
                      if (_interactiveButtons.isNotEmpty) ...[
                        BioSpacing.gapH(BioSpacing.md),
                        Wrap(
                          spacing: BioSpacing.sm,
                          runSpacing: BioSpacing.sm,
                          children: _interactiveButtons.map((btn) {
                            return BioButton.outlinePrimary(
                              label: btn['label']?.toString() ?? 'Opción',
                              size: BioButtonSize.sm,
                              onPressed: _isLoading
                                  ? null
                                  : () => _onInteractiveButton(btn),
                            );
                          }).toList(),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
              if (_atajos.isNotEmpty) ...[
                BioSpacing.gapH(BioSpacing.xl),
                Text(
                  'Atajos',
                  style: BioTypography.h3,
                  textAlign: TextAlign.center,
                ),
                BioSpacing.gapH(BioSpacing.md),
                for (final cat in _atajos) ...[
                  if (cat.titulo.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(bottom: BioSpacing.sm),
                      child: Text(
                        cat.titulo,
                        style: BioTypography.title.copyWith(color: tokens.textMuted),
                      ),
                    ),
                  Wrap(
                    spacing: BioSpacing.sm,
                    runSpacing: BioSpacing.sm,
                    children: cat.items.map((item) {
                      return ActionChip(
                        label: Text(item.title),
                        onPressed: _isLoading ? null : () => _onAtajoTap(item),
                      );
                    }).toList(),
                  ),
                  BioSpacing.gapH(BioSpacing.md),
                ],
              ],
            ],
          ),
        ),
      ),
    );
  }
}
