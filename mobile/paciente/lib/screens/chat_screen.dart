import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';
import 'package:url_launcher/url_launcher.dart';

import '../navigation/native_screen_router.dart';
import '../services/chat_service.dart';
import '../services/acciones_service.dart';
import '../utils/turno_resolucion_utils.dart';
import '../components/dynamic_form.dart';
import 'chat_motivos_screen.dart';

class ChatScreen extends StatefulWidget {
  final ChatService chatService;
  final PendingTurnoResolver? pendingResolver;
  final VoidCallback? onPendingResolverHandled;

  const ChatScreen({
    Key? key,
    required this.chatService,
    this.pendingResolver,
    this.onPendingResolverHandled,
  }) : super(key: key);

  @override
  ChatScreenState createState() => ChatScreenState();
}

class ChatScreenState extends State<ChatScreen> {
  final TextEditingController _messageController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  final ScrollController _welcomeShortcutsScrollController = ScrollController();
  late AsistenteService _asistenteService;
  List<Map<String, dynamic>> _chatHistory = [];
  bool _isSending = false;
  Map<String, dynamic> _draft = {};
  Map<String, dynamic> _flowSnapshot = {};
  String? _intentId;
  String? _subintentId;

  /// Incrementa al iniciar cada activación de un flow (reinicio tras otro flow o atajo con reset).
  int _flowActivationSeq = 0;

  /// Atajos para panel inicial (GET acciones/comunes); mismo origen que el bottom sheet Atajos.
  List<AtajoCategoria>? _welcomeAtajos;
  bool _composerHasText = false;

  /// `turnos.crear-como-paciente` → `/api/v1/turnos/crear-como-paciente` (fallback si `client_open` viene null).
  String? _apiRouteFromActionId(String actionId) {
    final aid = actionId.trim().toLowerCase();
    final clinical = RegExp(r'^clinical\.([\w-]+)\.([\w-]+)$');
    final m3 = clinical.firstMatch(aid);
    if (m3 != null) {
      return '/api/v1/clinical/${m3.group(1)}/${m3.group(2)}';
    }
    final dot = aid.indexOf('.');
    if (dot <= 0 || dot >= aid.length - 1) {
      return null;
    }
    final ent = aid.substring(0, dot);
    final act = aid.substring(dot + 1);
    if (ent.isEmpty || act.isEmpty) {
      return null;
    }
    return '/api/v1/$ent/$act';
  }

  /// Pantalla final de alta de turno (descriptor + POST), no listados previos.
  bool _inlineUiIsConfirmacionTurno(Object? inline) {
    if (inline is! Map) {
      return false;
    }
    final route = (inline['route'] ?? '').toString();
    final abs = (inline['api_absolute_url'] ?? '').toString();
    return route.contains('crear-como-paciente') || abs.contains('crear-como-paciente');
  }

  /// Título del flujo (`action_name` en el YAML del subintent).
  String? _flowActionTitleFromMessage(Map<String, dynamic> message) {
    final fm = message['flow_manifest'];
    if (fm is! Map) {
      return null;
    }
    final name = fm['action_name']?.toString().trim();
    if (name == null || name.isEmpty) {
      return null;
    }
    return name;
  }

  bool _messageHasFlowInteractiveUi(Map<String, dynamic> message) {
    return message['inline_ui'] is Map || message['flow_submit'] is Map;
  }

  /// Mensaje del bot cuyo step es **terminal** (último paso del flow): trae `flow_submit`
  /// integrado y el botón "Confirmar y enviar" se renderiza debajo del `UiJsonScreen`.
  /// El tap en items del `kind: list` no debe disparar `procesarInteraccion('')` (Cambio 2).
  bool _messageIsTerminalFlowStep(Map<String, dynamic> message) {
    return message['flow_submit'] is Map;
  }

  /// `flow_submit` visible: sin `inline_ui` (submit-only) o tras `onEmbeddedReady` del paso.
  bool _showFlowSubmitButton(Map<String, dynamic> message) {
    if (message['flow_submit'] is! Map) return false;
    if (message['inline_ui'] is! Map) return true;
    return message['_flow_submit_ready'] == true;
  }

  /// Normaliza el envelope `flow_submit` del motor (`route`/`method`/`action_id`/`body_template`).
  /// Devuelve `null` si el descriptor es inválido (ruta vacía).
  Map<String, dynamic>? _normalizeFlowSubmit(dynamic raw) {
    if (raw is! Map) return null;
    final route = raw['route']?.toString().trim() ?? '';
    if (route.isEmpty) return null;
    final method = (raw['method']?.toString().trim().isNotEmpty ?? false)
        ? raw['method'].toString().trim()
        : 'POST';
    return <String, dynamic>{
      'route': route,
      'method': method,
      'action_id': raw['action_id']?.toString() ?? '',
      'body_template': raw['body_template'] is Map
          ? Map<String, dynamic>.from(raw['body_template'] as Map)
          : <String, dynamic>{},
    };
  }

  void _syncFlowSessionFromView(AssistantFlowView flow) {
    if (flow.intentId.isNotEmpty) {
      _intentId = flow.intentId;
      _asistenteService.currentIntentId = _intentId;
    }
    if (flow.subintentId.isNotEmpty) {
      _subintentId = flow.subintentId;
      _asistenteService.currentSubintentId = _subintentId;
    }
    if (flow.draftDelta.isNotEmpty) {
      _applyDraftDelta(flow.draftDelta);
      _asistenteService.draft = Map<String, dynamic>.from(_draft);
    }
  }

  /// Tras un POST al asistente en modo flow: sincroniza sesión, agrega burbuja y abre mini-UI si aplica.
  Future<void> _handleFlowEnvelopeResponse(Map<String, dynamic> envelope) async {
    final nextFlow = AssistantFlowView.fromEnvelope(envelope);
    if (nextFlow == null) {
      final t = envelope['text']?.toString() ?? 'Ok.';
      setState(() {
        _chatHistory.add({
          'type': 'bot',
          'content': t,
          'actions': null,
          'timestamp': DateTime.now(),
        });
      });
      _scrollToBottom();
      return;
    }

    _syncFlowSessionFromView(nextFlow);
    final msgText = nextFlow.text.trim().isNotEmpty ? nextFlow.text.trim() : 'Ok.';
    final fsPayload = _normalizeFlowSubmit(nextFlow.flowSubmit);
    final providesPayload = nextFlow.provides;
    final openUi = nextFlow.openUi;
    final co = openUi != null ? openUi['client_open'] : null;
    final actionId = openUi != null ? openUi['action_id']?.toString() : null;
    final hasInlineUi =
        co is Map && (actionId != null && actionId.isNotEmpty);
    final isSubmitOnly = fsPayload != null && !hasInlineUi;

    setState(() {
      _chatHistory.add({
        'type': 'bot',
        'content': msgText,
        'actions': null,
        if (nextFlow.manifest != null) 'flow_manifest': nextFlow.manifest,
        if (nextFlow.hints.isNotEmpty) 'hints': nextFlow.hints,
        if (fsPayload != null && isSubmitOnly) 'flow_submit': fsPayload,
        if (providesPayload.isNotEmpty) 'flow_provides': providesPayload,
        'timestamp': DateTime.now(),
      });
      _stampLastBotMessageFlowActivation();
      _applyFlowSupersession(activeIntentId: _intentId);
    });
    _scrollToBottom();

    if (openUi == null || isSubmitOnly) {
      return;
    }
    if (actionId == null || actionId.isEmpty) {
      return;
    }

    Map<String, dynamic>? pseudoAction;
    if (co is Map) {
      pseudoAction = <String, dynamic>{
        'action_id': actionId,
        'display_name': actionId,
        'client_open': Map<String, dynamic>.from(co),
        'parameters': {'provided': _draft},
      };
    } else {
      String? route;
      final fm = nextFlow.manifest;
      if (fm != null) {
        final step = fm['active_step'];
        if (step is Map) {
          final ui = step['ui'];
          if (ui is Map && ui['tabs'] is List) {
            final tabs = ui['tabs'] as List;
            final defId = ui['default_tab']?.toString() ?? '';
            Map? picked;
            for (final tt in tabs) {
              if (tt is Map && defId.isNotEmpty && tt['id']?.toString() == defId) {
                picked = tt;
                break;
              }
            }
            picked ??= tabs.isNotEmpty && tabs.first is Map ? (tabs.first as Map) : null;
            route = picked is Map ? picked['route']?.toString() : null;
          }
        }
      }
      route ??= _apiRouteFromActionId(actionId);
      if (route != null && route.isNotEmpty) {
        pseudoAction = <String, dynamic>{
          'action_id': actionId,
          'display_name': actionId,
          'client_open': {
            'kind': 'ui_json',
            'api': {'route': route, 'method': 'GET|POST'},
          },
          'parameters': {'provided': _draft},
        };
      }
    }

    if (pseudoAction == null) {
      return;
    }

    await _tryOpenClientNative(pseudoAction, messageIndex: _chatHistory.length - 1);
    if (fsPayload != null && mounted && _chatHistory.isNotEmpty) {
      setState(() {
        _chatHistory.last['flow_submit'] = fsPayload;
        _chatHistory.last['_flow_submit_ready'] = false;
      });
    }
  }

  /// Texto del aviso "faltan datos" del botón "Confirmar y enviar". Usa el `flow_snapshot`
  /// vivo para mostrar etiquetas legibles cuando estén disponibles.
  String _missingFieldsMessage(List<String> missing) {
    if (missing.isEmpty) {
      return 'Faltan datos para enviar.';
    }
    final labels = missing.map((k) {
      final entry = _flowSnapshot[k];
      if (entry is Map) {
        final label = entry['label']?.toString().trim() ?? '';
        if (label.isNotEmpty) return label;
      }
      return k.replaceAll('_', ' ');
    }).toList();
    if (labels.length == 1) {
      return 'Falta elegir: ${labels.first}.';
    }
    return 'Faltan datos: ${labels.join(', ')}.';
  }

  /// Resuelve el `body_template` con el `_draft` actual. Devuelve `(body, missingFields)`.
  /// Las claves del template con valor `draft.<campo>` se reemplazan por `_draft[campo]`;
  /// si el campo está vacío, se acumula en `missingFields`. Valores no-`draft.*` se copian
  /// literales (raro, pero soportado).
  ({Map<String, dynamic> body, List<String> missing}) _resolveFlowSubmitBody(
    Map<String, dynamic> bodyTemplate,
  ) {
    final body = <String, dynamic>{};
    final missing = <String>[];
    bodyTemplate.forEach((k, v) {
      final s = v?.toString().trim() ?? '';
      if (s.startsWith('draft.')) {
        final field = s.substring(6);
        final raw = _draft[field];
        final str = raw?.toString().trim() ?? '';
        if (field.isEmpty || str.isEmpty) {
          missing.add(field.isEmpty ? k : field);
        } else {
          body[k] = str;
        }
      } else if (s.isNotEmpty) {
        body[k] = s;
      }
    });
    return (body: body, missing: missing);
  }

  String? _flowIntentIdFromMessage(Map<String, dynamic> message) {
    final fm = message['flow_manifest'];
    if (fm is Map) {
      final id = fm['intent_id']?.toString().trim();
      if (id != null && id.isNotEmpty) {
        return id;
      }
    }
    return null;
  }

  /// Flow distinto al activo: marcar UIs interactivas anteriores como descartadas.
  void _supersedeDiscardedFlowUIs(String activeIntentId) {
    for (final m in _chatHistory) {
      if (m['type'] != 'bot' || !_messageHasFlowInteractiveUi(m)) {
        continue;
      }
      final id = _flowIntentIdFromMessage(m);
      if (id != null && id != activeIntentId) {
        m['flow_superseded'] = true;
      }
    }
  }

  void _supersedeAllFlowInteractiveMessages() {
    for (final m in _chatHistory) {
      if (m['type'] == 'bot' && _messageHasFlowInteractiveUi(m)) {
        m['flow_superseded'] = true;
      }
    }
  }

  /// Último mensaje interactivo del flow activo (mini-UI o submit), o `null` si no hay.
  int? _lastFlowInteractiveMessageIndex(String activeIntentId) {
    for (var i = _chatHistory.length - 1; i >= 0; i--) {
      final m = _chatHistory[i];
      if (m['type'] != 'bot' || !_messageHasFlowInteractiveUi(m)) {
        continue;
      }
      if (_flowIntentIdFromMessage(m) == activeIntentId) {
        return i;
      }
    }
    return null;
  }

  /// Cambio 1: al tocar un list de un paso anterior, se eliminan los mensajes
  /// posteriores del mismo flow y se limpian las keys del draft que esos pasos
  /// proveyeron (`flow_provides`). Luego el motor recalcula desde el paso editado.
  ///
  /// Importante: resetea también `_subintentId` (y el del service). Si no, el
  /// próximo `procesarInteraccion('')` mandaría el `subintent_id` del último paso
  /// que el cliente vió (p. ej. `elegir-slot`) y el motor intentaría retomar desde
  /// ahí con un draft incompleto, en vez de recalcular desde el primer paso.
  void _truncateFlowAfter(int messageIndex) {
    if (messageIndex < 0 || messageIndex >= _chatHistory.length - 1) return;
    final activeIntent = _intentId;
    if (activeIntent == null || activeIntent.isEmpty) return;

    // Recolectar keys del draft a borrar y mensajes a eliminar (mismo flow activo).
    final keysToRemove = <String>{};
    final indicesToRemove = <int>[];
    for (var i = messageIndex + 1; i < _chatHistory.length; i++) {
      final m = _chatHistory[i];
      if (m['type'] != 'bot') continue;
      if (_flowIntentIdFromMessage(m) != activeIntent) continue;
      final provides = m['flow_provides'];
      if (provides is List) {
        for (final p in provides) {
          final s = p?.toString().trim() ?? '';
          if (s.isNotEmpty) keysToRemove.add(s);
        }
      }
      indicesToRemove.add(i);
    }
    if (indicesToRemove.isEmpty) return;

    // Borrar de atrás hacia adelante para no invalidar índices.
    for (var i = indicesToRemove.length - 1; i >= 0; i--) {
      _chatHistory.removeAt(indicesToRemove[i]);
    }
    for (final k in keysToRemove) {
      _draft.remove(k);
      _flowSnapshot.remove(k);
    }
    _asistenteService.draft = Map<String, dynamic>.from(_draft);
    // Reset del subintent activo: el motor empezará desde subintents[0] y avanzará
    // según el draft (que ahora refleja sólo lo que el usuario ya eligió).
    _subintentId = null;
    _asistenteService.currentSubintentId = null;
  }

  void _beginNewFlowActivation() {
    _flowActivationSeq++;
  }

  void _stampFlowActivationOnMessage(Map<String, dynamic> message) {
    if (message['flow_manifest'] is Map ||
        _messageHasFlowInteractiveUi(message)) {
      message['flow_activation_seq'] = _flowActivationSeq;
    }
  }

  void _stampLastBotMessageFlowActivation() {
    if (_chatHistory.isEmpty) {
      return;
    }
    final m = _chatHistory.last;
    if (m['type'] == 'bot') {
      _stampFlowActivationOnMessage(m);
    }
  }

  void _applyFlowSupersession({String? previousIntentId, String? activeIntentId}) {
    if (activeIntentId == null || activeIntentId.isEmpty) {
      return;
    }
    if (previousIntentId != null &&
        previousIntentId.isNotEmpty &&
        previousIntentId != activeIntentId) {
      _supersedeDiscardedFlowUIs(activeIntentId);
      _beginNewFlowActivation();
    }
    // Nota (Cambio 1): los pasos previos del MISMO flow activo NO se marcan
    // `flow_superseded`. Quedan clickables para "rebobinar" — el tap dispara
    // `_truncateFlowAfter` y el motor recalcula desde ese paso.
  }

  void _applyDraftDelta(Map<String, dynamic> delta) {
    if (delta.isEmpty) return;
    final merged = applyDraftDelta(
      draft: _draft,
      flowSnapshot: _flowSnapshot,
      delta: delta,
    );
    _draft = merged.draft;
    _flowSnapshot = merged.flowSnapshot;
  }

  void _clearFlowState() {
    _draft = {};
    _flowSnapshot = {};
    _intentId = null;
    _subintentId = null;
    _asistenteService.currentIntentId = null;
    _asistenteService.currentSubintentId = null;
    _asistenteService.draft = {};
  }

  /// Texto legible a partir de `ui_submit_result.data` + contexto del flow.
  String _formatFlowSubmitSummary(
    Map<String, dynamic>? data, {
    String? intentId,
    Map<String, dynamic>? flowSnapshot,
  }) {
    return formatFlowSubmitSummary(
      intentId: intentId ?? _intentId,
      submitData: data,
      flowSnapshot: flowSnapshot ?? _flowSnapshot,
    );
  }

  static const Duration _flowCollapseFadeOut = Duration(milliseconds: 620);
  static const Duration _flowSummaryFadeIn = Duration(milliseconds: 520);

  /// Tras submit exitoso: anima salida de mini-UIs y deja un único mensaje con el resultado.
  Future<void> _collapseCompletedFlowActivation({
    required int activationSeq,
    Map<String, dynamic>? submitData,
    String? flowActionTitle,
    String? intentId,
    Map<String, dynamic>? flowSnapshot,
  }) async {
    final indices = <int>[];
    for (var i = 0; i < _chatHistory.length; i++) {
      final m = _chatHistory[i];
      if (m['type'] == 'bot' && m['flow_activation_seq'] == activationSeq) {
        indices.add(i);
      }
    }
    if (indices.isEmpty) {
      return;
    }

    for (final i in indices) {
      _chatHistory[i]['flow_collapsing'] = true;
    }
    if (mounted) {
      setState(() {});
    }
    await Future<void>.delayed(_flowCollapseFadeOut);
    if (!mounted) {
      return;
    }

    final summary = _formatFlowSubmitSummary(
      submitData,
      intentId: intentId,
      flowSnapshot: flowSnapshot,
    );
    final firstIdx = indices.first;

    for (var i = indices.length - 1; i >= 0; i--) {
      _chatHistory.removeAt(indices[i]);
    }

    _chatHistory.insert(firstIdx, {
      'type': 'bot',
      'content': summary,
      'flow_completed_summary': true,
      if (flowActionTitle != null && flowActionTitle.isNotEmpty)
        'flow_manifest': {'action_name': flowActionTitle},
      'timestamp': DateTime.now(),
    });
    if (mounted) {
      setState(() {});
    }
  }

  Widget _wrapFlowInteractiveCollapse({
    required bool collapsing,
    required Widget child,
  }) {
    return AnimatedOpacity(
      opacity: collapsing ? 0.0 : 1.0,
      duration: _flowCollapseFadeOut,
      curve: Curves.easeInOut,
      child: AnimatedSlide(
        offset: collapsing ? const Offset(0, -0.06) : Offset.zero,
        duration: _flowCollapseFadeOut,
        curve: Curves.easeInOut,
        child: IgnorePointer(
          ignoring: collapsing,
          child: child,
        ),
      ),
    );
  }

  /// Título del flow: una vez por activación (misma `flow_activation_seq`), no en cada paso.
  bool _shouldShowFlowChatHeader(int messageIndex, Map<String, dynamic> message) {
    final fm = message['flow_manifest'];
    if (fm is! Map) {
      return false;
    }
    final intentId = fm['intent_id']?.toString().trim() ?? '';
    final actionName = fm['action_name']?.toString().trim() ?? '';
    final seq = message['flow_activation_seq'];

    for (var i = messageIndex - 1; i >= 0; i--) {
      final prev = _chatHistory[i];
      if (prev['type'] != 'bot') {
        continue;
      }
      final pfm = prev['flow_manifest'];
      if (pfm is! Map) {
        continue;
      }
      if (prev['flow_activation_seq'] != seq) {
        continue;
      }
      if (intentId.isNotEmpty) {
        if ((pfm['intent_id']?.toString().trim() ?? '') == intentId) {
          return false;
        }
        continue;
      }
      if (actionName.isNotEmpty &&
          (pfm['action_name']?.toString().trim() ?? '') == actionName) {
        return false;
      }
    }
    return true;
  }

  /// Encabezado del flujo: `action_name` pequeño centrado + línea a ancho completo.
  Widget _buildFlowChatHeader(BuildContext context, {required String title}) {
    final primary = IntentPalette.of(UiIntent.primary).base;
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        BioSpacing.lg,
        BioSpacing.sm,
        BioSpacing.lg,
        0,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            title,
            textAlign: TextAlign.center,
            style: BioTypography.title.copyWith(
              fontWeight: FontWeight.w600,
              color: primary,
            ),
          ),
          BioSpacing.gapH(BioSpacing.xs + 2),
          Divider(
            color: primary.withValues(alpha: 0.85),
            thickness: 2,
            height: 2,
          ),
        ],
      ),
    );
  }

  /// Texto del paso (`assistant_text` / `content`), alineado a la izquierda sin línea inferior.
  Widget _buildFlowStepTitle(
    BuildContext context,
    String stepText, {
    bool muted = false,
  }) {
    final tokens = context.bio;
    final stepColor = muted ? tokens.textMuted : tokens.textTitle;
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        BioSpacing.lg,
        BioSpacing.xs,
        BioSpacing.lg,
        0,
      ),
      child: Align(
        alignment: Alignment.centerLeft,
        child: Text(
          stepText,
          textAlign: TextAlign.left,
          style: BioTypography.title.copyWith(
            fontSize: 16,
            fontWeight: FontWeight.w600,
            color: stepColor,
          ),
        ),
      ),
    );
  }

  /// Cierra el flujo del asistente sin persistir; vuelve al mensaje inicial (sin llamar a la API).
  void _resetAssistantToWelcome() {
    setState(() {
      _chatHistory = [];
      _clearFlowState();
      _isSending = false;
    });
    _scrollToBottom();
  }

  bool get _showWelcomeShortcutGrid =>
      !_composerHasText &&
      _chatHistory.isEmpty &&
      _welcomeAtajos != null &&
      _welcomeAtajos!.isNotEmpty;

  void _onComposerTextChanged() {
    final next = _messageController.text.trim().isNotEmpty;
    if (next == _composerHasText) return;
    setState(() => _composerHasText = next);
  }

  @override
  void initState() {
    super.initState();
    _messageController.addListener(_onComposerTextChanged);
    _initializeService();
  }

  @override
  void didUpdateWidget(ChatScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.pendingResolver != null &&
        widget.pendingResolver != oldWidget.pendingResolver) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && widget.pendingResolver != null) {
          _runPendingResolver(widget.pendingResolver!);
        }
      });
    }
  }

  Future<void> _initializeService() async {
    // Cargar token desde SharedPreferences
    final prefs = await SharedPreferences.getInstance();
    final authToken = prefs.getString('auth_token');
    
    // Inicializar servicio con el userId y token
    _asistenteService = AsistenteService(
      userId: widget.chatService.currentUserId,
      authToken: authToken,
      appClient: 'paciente-flutter',
    );
    try {
      final atajos = await _asistenteService.cargarAtajos();
      if (!mounted) return;
      setState(() => _welcomeAtajos = atajos);
    } catch (_) {
      if (mounted) setState(() => _welcomeAtajos = []);
    }
    if (widget.pendingResolver != null && mounted) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && widget.pendingResolver != null) {
          _runPendingResolver(widget.pendingResolver!);
        }
      });
    }
  }

  /// Desde Inicio (Resolver) o push: inicia el flow y salta la elección del turno.
  Future<void> _runPendingResolver(PendingTurnoResolver pending) async {
    widget.onPendingResolverHandled?.call();
    final turno = pending.turno;
    final intentId = TurnoResolucionUtils.intentResolver(turno);
    final id = turno['id']?.toString() ?? '';
    if (id.isEmpty) {
      return;
    }

    await _onRemediationChoice({
      'intent_id': intentId,
      'reset_flow': true,
    });
    if (!mounted) {
      return;
    }

    final label = TurnoResolucionUtils.etiquetaLista(turno);
    _applyDraftDelta({
      'id': id,
      '_flow_item_id': {'id': id, 'name': label},
    });
    _asistenteService.draft = Map<String, dynamic>.from(_draft);

    final res = await _asistenteService.procesarInteraccion('');
    if (!mounted) {
      return;
    }
    if (res['success'] == true && res['data'] is Map) {
      final consumed = await _consumeAsistenteSuccessData(
        Map<String, dynamic>.from(res['data'] as Map),
      );
      if (!consumed) {
        _appendGenericAsistenteBotMessage(
          Map<String, dynamic>.from(res['data'] as Map),
        );
      }
      _scrollToBottom();
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  /// Mensaje genérico cuando el sobre no fue consumido por flow/interactive/message.
  void _appendGenericAsistenteBotMessage(Map<String, dynamic> data) {
    final actions = data['actions'] ?? (data['action'] != null ? [data['action']] : null);
    final textField = data['text']?.toString();
    final explanation = (textField != null && textField.trim().isNotEmpty)
        ? textField.trim()
        : 'Consulta procesada';
    final suggestedQuery = data['interaccion_sugerida']?['texto'];
    final queryType = data['query_type'];
    final matchedBy = data['matched_by'];
    final actionAnalysisRaw = data['action_analysis'];

    setState(() {
      _isSending = false;
      _chatHistory.add({
        'type': 'bot',
        'content': explanation,
        'actions': actions != null && actions.isNotEmpty ? List<Map<String, dynamic>>.from(actions) : null,
        'suggested_query': suggestedQuery,
        'query_type': queryType,
        'matched_by': matchedBy,
        'needs_user_input': data['needs_user_input'] ?? false,
        'action_analysis': (actionAnalysisRaw is Map)
            ? Map<String, dynamic>.from(actionAnalysisRaw)
            : null,
        'parameters': data['parameters'],
        'timestamp': DateTime.now(),
      });
    });
  }

  void _maybeAutoExecuteSingleAction(Map<String, dynamic> data) {
    final actions = data['actions'] ?? (data['action'] != null ? [data['action']] : null);
    final matchedBy = data['matched_by'];
    final queryType = data['query_type'];
    final needsUserInput = data['needs_user_input'] ?? false;
    if ((matchedBy == 'action_id' || matchedBy == 'semantic') &&
        actions != null &&
        actions.length == 1 &&
        queryType == 'direct_action' &&
        !needsUserInput) {
      Future.delayed(const Duration(milliseconds: 500), () {
        _executeAction(actions[0], messageIndex: _chatHistory.length - 1);
      });
    }
  }

  /// `true` si ya se actualizó la UI y no debe ejecutarse el branch genérico de acciones.
  Future<bool> _consumeAsistenteSuccessData(Map<String, dynamic> data) async {
    final kind = data['kind']?.toString();
    final textField = data['text']?.toString();
    final explanation = (textField != null && textField.trim().isNotEmpty)
        ? textField.trim()
        : 'Consulta procesada';

    if (kind == 'message') {
      setState(() {
        _isSending = false;
        _chatHistory.add({
          'type': 'bot',
          'content': explanation,
          'timestamp': DateTime.now(),
        });
      });
      _scrollToBottom();
      return true;
    }

    if (kind == 'interactive') {
      setState(() {
        _isSending = false;
        _supersedeAllFlowInteractiveMessages();
        _clearFlowState();
        final buttons = data['buttons'];
        List<Map<String, dynamic>>? buttonList;
        if (buttons is List) {
          buttonList = buttons
              .whereType<Map>()
              .map((e) => Map<String, dynamic>.from(e))
              .toList();
        }
        _chatHistory.add({
          'type': 'bot',
          'content': explanation,
          'buttons': buttonList,
          'timestamp': DateTime.now(),
        });
      });
      _scrollToBottom();
      return true;
    }

    final flow = AssistantFlowView.fromEnvelope(data);
    if (flow == null) {
      return false;
    }

    final previousIntentId = _intentId;
    _syncFlowSessionFromView(flow);

    final flowSubmit = _normalizeFlowSubmit(flow.flowSubmit);
    final providesList = flow.provides;
    final openUi = flow.openUi;
    if (openUi != null || flowSubmit != null) {
      final co = openUi != null ? openUi['client_open'] : null;
      final actionId = openUi != null ? openUi['action_id']?.toString() : null;
      // Caso "submit-only": el motor cerró el flow sin UI previa (ver `buildTerminalSubmitOnlyResponse`).
      // Mensaje sólo con `flow_submit` → render del botón "Confirmar y enviar" sin `inline_ui`.
      if (flowSubmit != null && !(co is Map && actionId != null && actionId.isNotEmpty)) {
        setState(() {
          _isSending = false;
          _chatHistory.add({
            'type': 'bot',
            'content': explanation,
            'actions': null,
            if (flow.manifest != null) 'flow_manifest': flow.manifest,
            if (flow.hints.isNotEmpty) 'hints': flow.hints,
            'flow_submit': flowSubmit,
            if (providesList.isNotEmpty) 'flow_provides': providesList,
            'timestamp': DateTime.now(),
          });
          _stampLastBotMessageFlowActivation();
          _applyFlowSupersession(
            previousIntentId: previousIntentId,
            activeIntentId: _intentId,
          );
        });
        _scrollToBottom();
        return true;
      }
      if (co is Map && actionId != null && actionId.isNotEmpty) {
        final kindCo = co['kind']?.toString();
        final api = co['api'];
        final mobile = co['mobile'];
        final hasUiJsonRoute = (kindCo == 'ui_json') && (api is Map) && (api['route']?.toString().isNotEmpty ?? false);
        final hasNativeScreen = (kindCo == 'native') && (mobile is Map) && (mobile['screen_id']?.toString().isNotEmpty ?? false);
        if (!(hasUiJsonRoute || hasNativeScreen)) {
          setState(() {
            _isSending = false;
            _chatHistory.add({
              'type': 'bot',
              'content': '$explanation\n\nNo puedo abrir la mini-UI requerida ($actionId): client_open inválido o incompleto.',
              'actions': null,
              'timestamp': DateTime.now(),
            });
          });
          _scrollToBottom();
          return true;
        }

        final pseudoAction = <String, dynamic>{
          'action_id': actionId,
          'display_name': actionId,
          'client_open': Map<String, dynamic>.from(co),
          'parameters': {'provided': _draft},
        };
        setState(() {
          _isSending = false;
          _chatHistory.add({
            'type': 'bot',
            'content': explanation,
            'actions': null,
            if (flow.manifest != null) 'flow_manifest': flow.manifest,
            if (flow.hints.isNotEmpty) 'hints': flow.hints,
            if (flowSubmit != null) 'flow_submit': flowSubmit,
            if (flowSubmit != null && hasUiJsonRoute) '_flow_submit_ready': false,
            if (providesList.isNotEmpty) 'flow_provides': providesList,
            'timestamp': DateTime.now(),
          });
          _stampLastBotMessageFlowActivation();
          _applyFlowSupersession(
            previousIntentId: previousIntentId,
            activeIntentId: _intentId,
          );
        });
        _scrollToBottom();
        await _tryOpenClientNative(pseudoAction, messageIndex: _chatHistory.length - 1);
        return true;
      }

      if (actionId != null && actionId.isNotEmpty && co == null) {
        final fm = flow.manifest;
        String? route;
        if (fm != null) {
          final step = fm['active_step'];
          if (step is Map) {
            final ui = step['ui'];
            if (ui is Map && ui['tabs'] is List) {
              final tabs = ui['tabs'] as List;
              final defId = ui['default_tab']?.toString() ?? '';
              Map? picked;
              for (final t in tabs) {
                if (t is Map && defId.isNotEmpty && t['id']?.toString() == defId) {
                  picked = t;
                  break;
                }
              }
              picked ??= tabs.isNotEmpty && tabs.first is Map ? (tabs.first as Map) : null;
              route = picked is Map ? picked['route']?.toString() : null;
            }
          }
        }
        route ??= _apiRouteFromActionId(actionId);

        if (route != null && route.isNotEmpty) {
          final pseudoAction = <String, dynamic>{
            'action_id': actionId,
            'display_name': actionId,
            'client_open': {
              'kind': 'ui_json',
              'api': {'route': route, 'method': 'GET|POST'},
            },
            'parameters': {'provided': _draft},
          };
          setState(() {
            _isSending = false;
            _chatHistory.add({
              'type': 'bot',
              'content': explanation,
              'actions': null,
              if (flow.manifest != null) 'flow_manifest': flow.manifest,
              if (flowSubmit != null) 'flow_submit': flowSubmit,
              if (flowSubmit != null) '_flow_submit_ready': false,
              if (providesList.isNotEmpty) 'flow_provides': providesList,
              'timestamp': DateTime.now(),
            });
            _stampLastBotMessageFlowActivation();
            _applyFlowSupersession(
              previousIntentId: previousIntentId,
              activeIntentId: _intentId,
            );
          });
          _scrollToBottom();
          await _tryOpenClientNative(pseudoAction, messageIndex: _chatHistory.length - 1);
          return true;
        }

        setState(() {
          _isSending = false;
          _chatHistory.add({
            'type': 'bot',
            'content': '$explanation\n\nNo puedo abrir la mini-UI requerida ($actionId). No vino client_open ni pude derivar route desde flow_manifest.',
            'actions': null,
            'timestamp': DateTime.now(),
          });
        });
        _scrollToBottom();
        return true;
      }
    }

    if (openUi == null && flowSubmit == null) {
      setState(() {
        _isSending = false;
        _chatHistory.add({
          'type': 'bot',
          'content': explanation,
          'actions': null,
          if (flow.manifest != null) 'flow_manifest': flow.manifest,
          'timestamp': DateTime.now(),
        });
        _stampLastBotMessageFlowActivation();
        _applyFlowSupersession(
          previousIntentId: previousIntentId,
          activeIntentId: _intentId,
        );
      });
      _scrollToBottom();
      return true;
    }

    return false;
  }

  List<Map<String, dynamic>> _messageInteractiveButtons(Map<String, dynamic> message) {
    final buttons = message['buttons'];
    if (buttons is List && buttons.isNotEmpty) {
      return buttons.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
    }
    final rem = message['remediation'];
    if (rem is List && rem.isNotEmpty) {
      return rem.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
    }
    return [];
  }

  Future<void> _onRemediationChoice(Map<String, dynamic> opt) async {
    final intentId = opt['intent_id']?.toString() ?? '';
    if (intentId.isEmpty) return;
    final resetFlow = opt['reset_flow'] == true;
    final prevIntent = _intentId;
    setState(() {
      _isSending = true;
      if (resetFlow || (prevIntent != null && prevIntent != intentId)) {
        _beginNewFlowActivation();
      }
      if (resetFlow) {
        _supersedeAllFlowInteractiveMessages();
        _subintentId = null;
        _draft = {};
        _asistenteService.currentSubintentId = null;
        _asistenteService.draft = {};
      }
    });
    _scrollToBottom();
    try {
      _intentId = intentId;
      _asistenteService.currentIntentId = intentId;

      final result = await _asistenteService.procesarInteraccion('');
      if (!mounted) return;

      if (result['success'] != true) {
        setState(() {
          _isSending = false;
          _chatHistory.add({
            'type': 'bot',
            'content': result['message']?.toString() ?? 'No se pudo iniciar el flujo.',
            'timestamp': DateTime.now(),
          });
        });
        _scrollToBottom();
        return;
      }

      final raw = result['data'];
      if (raw is! Map) {
        setState(() => _isSending = false);
        return;
      }
      final data = Map<String, dynamic>.from(raw);
      final consumed = await _consumeAsistenteSuccessData(data);
      if (!consumed) {
        _appendGenericAsistenteBotMessage(data);
        _maybeAutoExecuteSingleAction(data);
      }
      _scrollToBottom();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isSending = false;
        _chatHistory.add({
          'type': 'bot',
          'content': 'Error al iniciar el flujo. Intentá de nuevo.',
          'timestamp': DateTime.now(),
        });
      });
      _scrollToBottom();
    }
  }

  /// Inicia un flow por `intent_id` como la SPA (Atajos → `startFlowFromShortcut`).
  Future<void> _startFlowFromShortcut(String intentId, String displayTitle) async {
    await _onRemediationChoice({
      'intent_id': intentId,
      'reset_flow': true,
    });
  }

  Future<void> _showAtajosSheet() async {
    final future = _asistenteService.cargarAtajos();
    if (!mounted) return;
    final screenH = MediaQuery.of(context).size.height;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) {
        return FutureBuilder<List<AtajoCategoria>>(
          future: future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return SizedBox(
                height: screenH * 0.35,
                child: const Center(child: CircularProgressIndicator()),
              );
            }
            final cats = snapshot.data ?? [];
            if (cats.isEmpty) {
              return Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'No hay atajos disponibles.',
                      style: BioTypography.bodySm.copyWith(
                        color: context.bio.textMuted,
                      ),
                    ),
                    BioSpacing.gapH(BioSpacing.lg),
                    BioButton(
                      label: 'Cerrar',
                      intent: UiIntent.neutral,
                      variant: BioButtonVariant.soft,
                      size: BioButtonSize.sm,
                      onPressed: () => Navigator.pop(sheetContext),
                    ),
                  ],
                ),
              );
            }
            final maxH = screenH * 0.65;
            return SizedBox(
              height: maxH,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
                    child: Text(
                      'Atajos',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.bold,
                          ),
                    ),
                  ),
                  Expanded(
                    child: ListView(
                      children: [
                        for (final cat in cats) ...[
                          Padding(
                            padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
                            child: Text(
                              cat.titulo,
                              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                    fontWeight: FontWeight.w600,
                                    decoration: TextDecoration.underline,
                                  ),
                            ),
                          ),
                          ...cat.items.map(
                            (item) => ListTile(
                              title: Text(item.title),
                              subtitle: item.description.isNotEmpty
                                  ? Text(
                                      item.description,
                                      style: Theme.of(context).textTheme.bodySmall,
                                    )
                                  : null,
                              onTap: () {
                                Navigator.pop(sheetContext);
                                _startFlowFromShortcut(item.intentId, item.title);
                              },
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  /// Panel central con mismos atajos que el ícono «Atajos»; se oculta al escribir o al tener mensajes en el chat.
  Widget _buildWelcomeShortcutsPanel() {
    final cats = _welcomeAtajos;
    if (cats == null || cats.isEmpty) {
      return const SizedBox.shrink();
    }
    final tokens = context.bio;
    final primary = IntentPalette.of(UiIntent.primary).base;
    return Padding(
      padding: const EdgeInsets.fromLTRB(0, BioSpacing.md, 0, BioSpacing.md),
      child: Align(
        alignment: Alignment.topCenter,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: BioSpacing.lg),
              child: Text(
                'Podés elegir un atajo o escribir tu consulta abajo.',
                textAlign: TextAlign.start,
                style: BioTypography.h3.copyWith(color: tokens.textMuted),
              ),
            ),
            BioSpacing.gapH(BioSpacing.md),
            ConstrainedBox(
              constraints: const BoxConstraints(maxHeight: 360),
              child: Scrollbar(
                controller: _welcomeShortcutsScrollController,
                child: SingleChildScrollView(
                  controller: _welcomeShortcutsScrollController,
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: BioSpacing.lg),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        ...cats.map(
                          (cat) => Padding(
                            padding: const EdgeInsets.only(bottom: BioSpacing.md),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  cat.titulo,
                                  style: BioTypography.title.copyWith(
                                    decoration: TextDecoration.underline,
                                    color: tokens.textMuted,
                                    fontWeight: FontWeight.w600,
                                  ),
                                  textAlign: TextAlign.start,
                                ),
                                BioSpacing.gapH(BioSpacing.sm),
                                Wrap(
                                  alignment: WrapAlignment.start,
                                  spacing: BioSpacing.sm,
                                  runSpacing: BioSpacing.sm,
                                  children: cat.items
                                      .map(
                                        (item) => ConstrainedBox(
                                          constraints: const BoxConstraints(
                                            maxWidth: 280,
                                          ),
                                          child: BioCard(
                                            padding: const EdgeInsets.symmetric(
                                              horizontal: BioSpacing.md,
                                              vertical: BioSpacing.sm + 2,
                                            ),
                                            onTap: _isSending
                                                ? null
                                                : () => _startFlowFromShortcut(
                                                      item.intentId,
                                                      item.title,
                                                    ),
                                            child: Column(
                                              crossAxisAlignment:
                                                  CrossAxisAlignment.start,
                                              mainAxisSize: MainAxisSize.min,
                                              children: [
                                                Text(
                                                  item.title,
                                                  style: BioTypography.title.copyWith(
                                                    fontSize: 14,
                                                    fontWeight: FontWeight.w600,
                                                    color: primary,
                                                  ),
                                                ),
                                                if (item.description.isNotEmpty) ...[
                                                  BioSpacing.gapH(BioSpacing.xs),
                                                  Text(
                                                    item.description,
                                                    style: BioTypography.bodySm.copyWith(
                                                      color: tokens.textMuted,
                                                    ),
                                                  ),
                                                ],
                                              ],
                                            ),
                                          ),
                                        ),
                                      )
                                      .toList(),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _sendMessage() async {
    final text = _messageController.text.trim();
    if (text.isEmpty) return;

    setState(() {
      _isSending = true;

      // Agregar mensaje del usuario al historial
      _chatHistory.add({
        'type': 'user',
        'content': text,
        'timestamp': DateTime.now(),
      });
      _messageController.clear();
    });

    _scrollToBottom();

    try {
      // Sin esto, cualquier mensaje seguía yendo a SubIntentEngine con el mismo intent y volvía a abrir
      // el selector de efector (faltan draft.*). El texto libre debe re-enrutar al IntentEngine raíz.
      if (!asistenteUserSaysNearbyForEfectorChooser(text)) {
        _clearFlowState();
      }

      // Procesar interacción del usuario con el servicio de acciones
      final result = await _asistenteService.procesarInteraccion(text);

      if (result['success'] == true) {
        final raw = result['data'];
        if (raw is Map) {
          final data = Map<String, dynamic>.from(raw);
          final consumed = await _consumeAsistenteSuccessData(data);
          if (!consumed) {
            _appendGenericAsistenteBotMessage(data);
            _maybeAutoExecuteSingleAction(data);
          }
        } else {
          setState(() {
            _isSending = false;
            _chatHistory.add({
              'type': 'bot',
              'content': 'Consulta procesada',
              'timestamp': DateTime.now(),
            });
          });
        }
      } else {
        final data = result['data'];
        final textFromEnvelope = data is Map ? data['text']?.toString() : null;

        setState(() {
          _isSending = false;
          _chatHistory.add({
            'type': 'bot',
            'content': (textFromEnvelope != null && textFromEnvelope.trim().isNotEmpty)
                ? textFromEnvelope.trim()
                : (result['message']?.toString() ?? 'Lo siento, no pude procesar tu consulta. Intenta nuevamente.'),
            'suggested_query': data?['interaccion_sugerida']?['texto'],
            'timestamp': DateTime.now(),
          });
        });
      }
      _scrollToBottom();
    } catch (e) {
      setState(() {
        _isSending = false;
        _chatHistory.add({
          'type': 'bot',
          'content': 'Error al procesar tu consulta. Por favor, intenta nuevamente.',
          'timestamp': DateTime.now(),
        });
      });
      _showErrorSnackbar('Error: ${e.toString()}');
      _scrollToBottom();
    }
  }

  /// Origen del sitio web (sin `/api/v1`) a partir de {@link AppConfig.apiUrl}.
  static Uri _webBaseUriFromApiUrl(String apiUrl) {
    var s = apiUrl.trim();
    s = s.replaceAll(RegExp(r'/api/v\d+/?$', caseSensitive: false), '');
    while (s.endsWith('/')) {
      s = s.substring(0, s.length - 1);
    }
    return Uri.parse(s);
  }

  /// Resuelve `client_open.api.route` + `query` del backend a URL absoluta de API.
  String _clientOpenUiJsonAbsoluteUrl(Map co) {
    final api = co['api'];
    if (api is! Map) {
      return '';
    }
    final route = api['route']?.toString() ?? '';
    if (route.isEmpty) {
      return '';
    }
    var base = resolveApiAbsoluteUrl(route);
    final q = api['query'];
    if (q is Map && q.isNotEmpty) {
      final u = Uri.parse(base);
      final qp = Map<String, String>.from(u.queryParameters);
      q.forEach((k, v) {
        if (v != null && v.toString().isNotEmpty) {
          qp[k.toString()] = v.toString();
        }
      });
      base = u.replace(queryParameters: qp).toString();
    }
    return base;
  }

  /// Combina `inline_ui.provided` del mensaje (snapshot al abrir el paso) con `_draft` vivo.
  /// Así las pestañas siguen teniendo `id_servicio*` aunque `_draft` global se haya limpiado al escribir otra consulta.
  Map<String, dynamic> _draftSnapshotForMessage(int messageIndex) {
    final out = <String, dynamic>{};
    if (messageIndex >= 0 && messageIndex < _chatHistory.length) {
      final inline = _chatHistory[messageIndex]['inline_ui'];
      if (inline is Map) {
        if (inline['provided'] is Map) {
          out.addAll(Map<String, dynamic>.from(inline['provided'] as Map));
        }
        final abs = inline['api_absolute_url']?.toString();
        if (abs != null && abs.trim().isNotEmpty) {
          final u = Uri.tryParse(abs);
          final idA = u?.queryParameters['id_servicio_asignado'];
          final idS = u?.queryParameters['id_servicio'];
          if (idA != null && idA.isNotEmpty) {
            out.putIfAbsent('id_servicio_asignado', () => idA);
          }
          if (idS != null && idS.isNotEmpty) {
            out.putIfAbsent('id_servicio', () => idS);
          }
        }
      }
    }
    _draft.forEach((k, v) {
      if (v != null && v.toString().trim().isNotEmpty) {
        out[k] = v;
      }
    });
    return out;
  }

  Future<String?> _absoluteUrlForFlowManifestTab(
    Map<String, dynamic> tab, {
    required Map<String, dynamic> draftForQuery,
  }) async {
    final route = tab['route']?.toString() ?? '';
    if (route.isEmpty) {
      return null;
    }
    final params = tab['params'];
    final qp = <String, String>{};
    if (params is Map) {
      for (final e in params.entries) {
        final spec = e.value?.toString() ?? '';
        if (spec.startsWith('draft.')) {
          final f = spec.substring(6);
          final v = draftForQuery[f];
          if (v != null && v.toString().isNotEmpty) {
            qp[e.key.toString()] = v.toString();
          }
        }
      }
    }
    final needsGeo = tab['requires_client'] is List &&
        (tab['requires_client'] as List).map((e) => e.toString()).contains('geolocation');
    if (needsGeo) {
      var perm = await Geolocator.checkPermission();
      if (perm == LocationPermission.denied) {
        perm = await Geolocator.requestPermission();
      }
      if (perm == LocationPermission.denied || perm == LocationPermission.deniedForever) {
        throw Exception('Se necesita permiso de ubicación para listar centros de salud cercanos.');
      }
      final pos = await Geolocator.getCurrentPosition();
      qp['latitud'] = '${pos.latitude}';
      qp['longitud'] = '${pos.longitude}';
    }
    var u = Uri.parse(resolveApiAbsoluteUrl(route));
    final merged = <String, String>{...u.queryParameters, ...qp};
    _ensureServicioQueryForEfectoresListado(route, merged, draftForQuery);
    u = u.replace(queryParameters: merged);
    return u.toString();
  }

  /// El backend exige `id_servicio` o `id_servicio_asignado` en listar-por-servicio*;
  /// la pestaña "cercano" puede traer solo lat/lng si el manifest no repite draft.*.
  void _ensureServicioQueryForEfectoresListado(
    String route,
    Map<String, String> qp,
    Map<String, dynamic> draft,
  ) {
    if (!route.contains('listar-por-servicio')) {
      return;
    }
    bool has(String k) => (qp[k]?.trim().isNotEmpty ?? false);
    if (has('id_servicio') || has('id_servicio_asignado')) {
      return;
    }
    final a = draft['id_servicio_asignado'];
    final s = draft['id_servicio'];
    if (a != null && a.toString().trim().isNotEmpty) {
      qp['id_servicio_asignado'] = a.toString().trim();
    } else if (s != null && s.toString().trim().isNotEmpty) {
      qp['id_servicio'] = s.toString().trim();
    }
  }

  /// Query de UI JSON: el servidor a veces no envía `parameters.provided`; el draft local trae id_*,
  /// `slot_id` (composite del paso horario) y `tipo_atencion` para la pantalla de alta.
  Map<String, dynamic> _mergeDraftIdsWithProvided(Object? providedRaw) {
    final out = <String, dynamic>{};
    for (final e in _draft.entries) {
      final k = e.key.toString();
      if (!k.startsWith('id_')) {
        continue;
      }
      final v = e.value;
      if (v != null && v.toString().trim().isNotEmpty) {
        out[k] = v;
      }
    }
    final slot = _draft['slot_id'];
    if (slot != null && slot.toString().trim().isNotEmpty) {
      out['slot_id'] = slot;
    }
    final tipo = _draft['tipo_atencion'];
    if (tipo != null && tipo.toString().trim().isNotEmpty) {
      out['tipo_atencion'] = tipo;
    }
    if (providedRaw is Map) {
      out.addAll(Map<String, dynamic>.from(providedRaw));
    }
    return out;
  }

  Future<void> _onFlowManifestTabSelected(int messageIndex, Map<String, dynamic> tab) async {
    try {
      final draftSnap = _draftSnapshotForMessage(messageIndex);
      final url = await _absoluteUrlForFlowManifestTab(tab, draftForQuery: draftSnap);
      if (url == null || url.isEmpty) {
        _showErrorSnackbar('No se pudo armar la URL del listado.');
        return;
      }
      final route = tab['route']?.toString() ?? '';
      if (route.contains('listar-por-servicio')) {
        final u = Uri.tryParse(url);
        final q = u?.queryParameters ?? {};
        final hasServ = (q['id_servicio']?.trim().isNotEmpty ?? false) ||
            (q['id_servicio_asignado']?.trim().isNotEmpty ?? false);
        if (!hasServ) {
          _showErrorSnackbar(
            'Falta el servicio elegido para listar centros de salud. Volvé a elegir el servicio en el paso anterior o iniciá de nuevo el trámite de turno.',
          );
          return;
        }
      }
      setState(() {
        final m = _chatHistory[messageIndex];
        m['flow_selected_tab_id'] = tab['id']?.toString();
        final inline = m['inline_ui'];
        if (inline is Map) {
          inline['api_absolute_url'] = url;
          inline['provided'] = draftSnap;
          final r = tab['route']?.toString();
          if (r != null && r.isNotEmpty) {
            inline['route'] = r;
          }
        }
      });
    } catch (e) {
      _showErrorSnackbar(e.toString());
    }
  }

  /// Si la acción trae `client_open` (pantalla Yii / web nativa), abre el navegador y no pasa por CRUD/wizard.
  Future<bool> _tryOpenClientNative(Map<String, dynamic> action, {int? messageIndex}) async {
    final co = action['client_open'];
    if (co is! Map) {
      return false;
    }
    final kind = co['kind']?.toString();
    final mobile = co['mobile'];
    final web = co['web'];

    // Intent conversacional (YAML): POST /asistente/enviar con action_id → kind flow.
    if (kind == 'intent') {
      final intentId = co['intent_id']?.toString() ?? action['action_id']?.toString() ?? '';
      if (intentId.isEmpty) {
        _showErrorSnackbar('Intent sin intent_id.');
        return true;
      }
      // Ya estamos en este flow (p. ej. cierre `flow_submit` mal etiquetado): no reiniciar draft.
      if (_intentId != null &&
          _intentId!.isNotEmpty &&
          intentId == _intentId &&
          _draft.isNotEmpty) {
        return true;
      }
      setState(() {
        _isSending = true;
        _beginNewFlowActivation();
        _intentId = intentId;
        _subintentId = null;
        _draft = {};
        _asistenteService.currentIntentId = intentId;
        _asistenteService.currentSubintentId = null;
        _asistenteService.draft = {};
      });
      _scrollToBottom();
      try {
        final result = await _asistenteService.procesarInteraccion('', actionId: intentId);
        if (!mounted) return true;
        if (result['success'] != true) {
          setState(() {
            _isSending = false;
            _chatHistory.add({
              'type': 'bot',
              'content': result['message']?.toString() ?? 'No se pudo iniciar el flujo.',
              'timestamp': DateTime.now(),
            });
          });
          _scrollToBottom();
          return true;
        }
        final raw = result['data'];
        if (raw is! Map) {
          setState(() => _isSending = false);
          return true;
        }
        final data = Map<String, dynamic>.from(raw);
        final consumed = await _consumeAsistenteSuccessData(data);
        if (!consumed) {
          _appendGenericAsistenteBotMessage(data);
          _maybeAutoExecuteSingleAction(data);
        }
      } catch (e) {
        if (mounted) {
          setState(() {
            _isSending = false;
            _chatHistory.add({
              'type': 'bot',
              'content': e.toString(),
              'timestamp': DateTime.now(),
            });
          });
        }
      }
      _scrollToBottom();
      return true;
    }

    // UI JSON (descriptor + submit): abrir con el motor UI JSON compartido.
    if (kind == 'ui_json') {
      final api = co['api'];
      final route = api is Map ? api['route']?.toString() : null;
      if (route == null || route.isEmpty) {
        _showErrorSnackbar('Acción UI JSON sin api.route.');
        return true;
      }
      final providedRaw = (action['parameters'] is Map) ? (action['parameters'] as Map)['provided'] : null;
      final effectiveProvided = _mergeDraftIdsWithProvided(providedRaw);
      // Igual que la SPA: `api.query` del descriptor + query desde draft/provided (p. ej. id_servicio_asignado).
      var apiAbs = _clientOpenUiJsonAbsoluteUrl(co);
      if (effectiveProvided.isNotEmpty) {
        final base = apiAbs.trim().isNotEmpty ? apiAbs : resolveApiAbsoluteUrl(route);
        apiAbs = applyProvidedParamsToRoute(base, effectiveProvided);
      }
      // Contrato nuevo: el motor abre SIEMPRE inline automáticamente.
      final title = action['display_name']?.toString() ?? action['action_id']?.toString() ?? 'Formulario';
      setState(() {
        if (messageIndex != null && messageIndex >= 0 && messageIndex < _chatHistory.length) {
          final m = _chatHistory[messageIndex];
          m['inline_ui'] = {
            'title': title,
            'route': route,
            'provided': effectiveProvided.isNotEmpty ? effectiveProvided : providedRaw,
            if (apiAbs.isNotEmpty) 'api_absolute_url': apiAbs,
          };
          final fm = m['flow_manifest'];
          if (fm is Map) {
            final step = fm['active_step'];
            if (step is Map) {
              final ui = step['ui'];
              if (ui is Map && ui['tabs'] is List && (ui['tabs'] as List).length >= 2) {
                m['flow_tabs'] = ui['tabs'];
                m['flow_default_tab'] = ui['default_tab']?.toString() ?? '';
                m['flow_selected_tab_id'] = m['flow_default_tab'];
              }
            }
          }
          if ((m['content']?.toString() ?? '').trim().isEmpty) {
            m['content'] = title;
          }
          _stampFlowActivationOnMessage(m);
        } else {
          _chatHistory.add({
            'type': 'bot',
            'content': title,
            'inline_ui': {
              'title': title,
              'route': route,
              'provided': effectiveProvided.isNotEmpty ? effectiveProvided : providedRaw,
              if (apiAbs.isNotEmpty) 'api_absolute_url': apiAbs,
            },
            'timestamp': DateTime.now(),
          });
          _stampLastBotMessageFlowActivation();
        }
        _applyFlowSupersession(activeIntentId: _intentId);
      });
      _scrollToBottom();
      return true;
    }

    // UIs nativas (web+flutter) por screen_id: el móvil construye su propia pantalla.
    if (kind == 'native') {
      final screenId = (mobile is Map ? mobile['screen_id'] : null) ??
          co['screen_id'];
      if (screenId is! String || screenId.isEmpty) {
        _showErrorSnackbar('Acción nativa sin screen_id para móvil.');
        return true;
      }
      await NativeScreenRouter.open(
        context,
        screenId: screenId,
        userId: widget.chatService.currentUserId,
        userName: widget.chatService.currentUserName,
        authToken: _asistenteService.authToken,
      );
      return true;
    }

    final pathRaw = (web is Map ? web['path'] : null);
    if (pathRaw is! String || pathRaw.isEmpty) {
      return false;
    }
    final base = _webBaseUriFromApiUrl(AppConfig.apiUrl);
    var target = base.replace(path: pathRaw);
    final queryMap = (web is Map ? web['query'] : null);
    if (queryMap is Map && queryMap.isNotEmpty) {
      final q = <String, String>{...target.queryParameters};
      queryMap.forEach((k, v) {
        if (v != null && v.toString().isNotEmpty) {
          q[k.toString()] = v.toString();
        }
      });
      target = target.replace(queryParameters: q);
    }
    try {
      // Fallback legacy: abrir en navegador externo.
      if (await canLaunchUrl(target)) {
        await launchUrl(target, mode: LaunchMode.externalApplication);
        return true;
      }
      _showErrorSnackbar('No se pudo abrir la pantalla.');
      return true;
    } catch (e) {
      _showErrorSnackbar('No se pudo abrir: ${e.toString()}');
      return true;
    }
  }

  Future<void> _executeAction(Map<String, dynamic> action, {int? messageIndex}) async {
    if (await _tryOpenClientNative(action, messageIndex: messageIndex)) {
      return;
    }

    // Desde la migración a UI JSON, el backend debe enviar `client_open` para abrir la pantalla.
    // Si no vino, evitamos llamar endpoints legacy (/crud/ejecutar-accion) que ya no existen.
    _showErrorSnackbar('Acción sin client_open: no se puede abrir en esta versión.');
  }

  Future<void> _executeActionWithParams(String actionId, Map<String, dynamic> params) async {
    // Legacy: antes se posteaba a /crud/ejecutar-accion. Ese endpoint ya no existe.
    // La ejecución/submit ahora ocurre dentro del cliente UI JSON (UiJsonScreen).
    _showErrorSnackbar('Submit legacy no soportado. Abrí la pantalla desde client_open.');
  }

  /// Despliega el formulario dinámico en un modal (bottom sheet) para móvil.
  void _showFormModal({
    required BuildContext context,
    required Map<String, dynamic> formConfig,
    List<Map<String, dynamic>>? wizardSteps,
    int initialStep = 0,
    required String title,
    String? authToken,
    required Future<void> Function(Map<String, dynamic>) onSubmit,
    required VoidCallback onCancel,
  }) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (modalContext) => Container(
        height: MediaQuery.of(modalContext).size.height,
        decoration: BoxDecoration(
          color: Theme.of(context).scaffoldBackgroundColor,
        ),
        child: Column(
          children: [
            // Título y cerrar
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      title,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close),
                    onPressed: () {
                      Navigator.of(modalContext).pop();
                      onCancel();
                    },
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            // Formulario con scroll
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: DynamicForm(
                  formConfig: formConfig,
                  wizardSteps: wizardSteps,
                  initialStep: initialStep,
                  authToken: authToken,
                  onSubmit: (formValues) async {
                    Navigator.of(modalContext).pop();
                    await onSubmit(formValues);
                  },
                  onCancel: () {
                    Navigator.of(modalContext).pop();
                    onCancel();
                  },
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _messageFromHttpResponse(http.Response res) {
    try {
      final decoded = json.decode(utf8.decode(res.bodyBytes));
      if (decoded is Map && decoded['message'] != null) {
        final m = decoded['message'].toString().trim();
        if (m.isNotEmpty) return m;
      }
    } catch (_) {
      // ignore
    }
    return 'HTTP ${res.statusCode}';
  }

  Future<void> _postFlowSubmitFromMessage(int index) async {
    if (index < 0 || index >= _chatHistory.length) return;
    final msg = _chatHistory[index];
    final fs = msg['flow_submit'];
    if (fs is! Map) return;
    final route = fs['route']?.toString().trim() ?? '';
    if (route.isEmpty) return;

    // Resolver body_template con el draft actual. Si faltan campos, mostrar error inline
    // (Cambio 2: botón siempre habilitado; descubrible).
    final tpl = fs['body_template'] is Map
        ? Map<String, dynamic>.from(fs['body_template'] as Map)
        : <String, dynamic>{};
    final resolved = _resolveFlowSubmitBody(tpl);
    if (resolved.missing.isNotEmpty) {
      setState(() {
        msg['_flow_submit_missing'] = resolved.missing;
      });
      return;
    }
    setState(() {
      msg.remove('_flow_submit_missing');
      msg['_flow_submit_busy'] = true;
    });
    try {
      final uri = Uri.parse(resolveApiAbsoluteUrl(route));
      final bodyMap = resolved.body;
      final headers = AppConfig.jsonHeaders(
        bearerToken: _asistenteService.authToken,
        appClient: 'bioenlace-paciente',
      );
      final res = await http
          .post(uri, headers: headers, body: json.encode(bodyMap))
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      if (!mounted) return;
      if (res.statusCode < 200 || res.statusCode >= 300) {
        _showErrorSnackbar(_messageFromHttpResponse(res));
        return;
      }
      final decoded = json.decode(utf8.decode(res.bodyBytes));
      if (decoded is! Map) {
        _showErrorSnackbar('Respuesta inválida');
        return;
      }
      final m = Map<String, dynamic>.from(decoded);
      if (m['kind'] == 'ui_submit_result' && m['success'] != false) {
        final data = m['data'];
        var successText = 'Listo.';
        if (data is Map) {
          final s = data['mensaje']?.toString() ?? data['message']?.toString() ?? '';
          if (s.trim().isNotEmpty) {
            successText = s.trim();
          }
        }
        if (mounted) {
          final palette = IntentPalette.of(UiIntent.primary);
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                successText,
                style: TextStyle(color: palette.onBase),
              ),
              backgroundColor: palette.base,
              duration: const Duration(seconds: 4),
            ),
          );
        }
        final activationSeq = msg['flow_activation_seq'] is int
            ? msg['flow_activation_seq'] as int
            : _flowActivationSeq;
        final flowTitle = _flowActionTitleFromMessage(msg);
        final resultData = data is Map ? Map<String, dynamic>.from(data) : null;
        final intentForSummary = _intentId;
        final snapForSummary = Map<String, dynamic>.from(_flowSnapshot);
        await _collapseCompletedFlowActivation(
          activationSeq: activationSeq,
          submitData: resultData,
          flowActionTitle: flowTitle,
          intentId: intentForSummary,
          flowSnapshot: snapForSummary,
        );
        if (!mounted) return;
        setState(() {
          _clearFlowState();
        });
        _scrollToBottom();
        return;
      }
      if (m['kind'] == 'ui_definition' && m['errors'] is Map) {
        final err = m['errors'] as Map;
        final first = err.values.isNotEmpty ? err.values.first.toString() : 'No se pudo validar.';
        _showErrorSnackbar(first);
        return;
      }
      _showErrorSnackbar(m['message']?.toString() ?? 'Error al confirmar');
    } catch (e) {
      if (mounted) _showErrorSnackbar(e.toString());
    } finally {
      if (mounted) {
        setState(() => msg['_flow_submit_busy'] = false);
      }
    }
  }

  void _showErrorSnackbar(String message) {
    final palette = IntentPalette.of(UiIntent.danger);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message, style: TextStyle(color: palette.onBase)),
        backgroundColor: palette.base,
        duration: const Duration(seconds: 3),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    // `cs` y `tt` se mantienen como atajos sobre el theme global "papel",
    // que ya resuelve primary/surface/onSurface/etc. desde los tokens.
    // Permite que el resto de los subwidgets de esta pantalla siga usándolos.
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;
    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: BioAppBar(
        title: 'BioEnlace',
        actions: [
          IconButton(
            icon: const Icon(Icons.bookmarks_outlined),
            tooltip: 'Atajos',
            onPressed: _isSending ? null : _showAtajosSheet,
          ),
        ],
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (_showWelcomeShortcutGrid) _buildWelcomeShortcutsPanel(),
          Expanded(
            child: ListView.builder(
              controller: _scrollController,
              padding: const EdgeInsets.symmetric(vertical: 8.0),
              itemCount: _chatHistory.length,
              itemBuilder: (context, index) {
                final message = _chatHistory[index];
                final isUser = message['type'] == 'user';
                final isFlowCompletedSummary =
                    !isUser && message['flow_completed_summary'] == true;
                final content = message['content'] as String;
                final actions = message['actions'] as List<Map<String, dynamic>>?;
                final suggestedQuery = message['suggested_query'] as String?;
                final inlineUi = message['inline_ui'];
                final hasEmbeddedUi = !isUser && inlineUi is Map;
                final flowActionTitle = !isUser ? _flowActionTitleFromMessage(message) : null;
                final hasFlowSubmit = !isUser && message['flow_submit'] is Map;
                final hasFlowContext =
                    flowActionTitle != null && (hasEmbeddedUi || hasFlowSubmit);
                final showFlowHeader = hasFlowContext &&
                    _shouldShowFlowChatHeader(index, message);
                final showFlowStepText = hasFlowContext && content.isNotEmpty;
                final flowUiDisabled = message['flow_superseded'] == true;
                final flowCollapsing = message['flow_collapsing'] == true;

                // Verificar si hay form_config para ocultar el mensaje de chat
                final hasFormConfig = message['form_config'] != null;
                final hasActionsRow =
                    !isUser && actions != null && actions.isNotEmpty;
                final hasRemediationRow =
                    !isUser && _messageInteractiveButtons(message).isNotEmpty;
                /// Separación antes de tabs/UI inline (ver también padding del [UiJsonScreen] abajo).
                double inlineUiLeadGapHeight = 4.0;
                if (!hasFormConfig && !isUser && inlineUi is Map && hasEmbeddedUi) {
                  if (hasFlowContext) {
                    inlineUiLeadGapHeight = showFlowStepText ? 0.0 : 2.0;
                  } else if (content.isNotEmpty && !hasActionsRow && !hasRemediationRow) {
                    inlineUiLeadGapHeight = 2.0;
                  }
                }

                if (isFlowCompletedSummary) {
                  final summaryTitle = _flowActionTitleFromMessage(message);
                  return _AnimatedFlowCompletedSummary(
                    fadeInDuration: _flowSummaryFadeIn,
                    title: summaryTitle,
                    content: content,
                    headerBuilder: summaryTitle != null && summaryTitle.isNotEmpty
                        ? (ctx) => _buildFlowChatHeader(ctx, title: summaryTitle)
                        : null,
                    surfaceColor: cs.primary.withValues(alpha: 0.08),
                    borderColor: cs.primary.withValues(alpha: 0.35),
                    textStyle: tt.bodyMedium?.copyWith(color: cs.onSurface),
                  );
                }

                return Column(
                  children: [
                    if (!hasFormConfig && hasFlowContext) ...[
                      if (showFlowHeader)
                        _buildFlowChatHeader(context, title: flowActionTitle),
                      if (showFlowStepText)
                        _buildFlowStepTitle(
                          context,
                          content,
                          muted: flowUiDisabled,
                        ),
                    ],
                    // Mensaje (solo mostrar si no hay form_config)
                    if (!hasFormConfig) ...[
                      if (isUser)
                        Align(
                          alignment: Alignment.centerRight,
                          child: Container(
                            margin: const EdgeInsets.symmetric(
                              vertical: 4.0,
                              horizontal: 16.0,
                            ),
                            padding: const EdgeInsets.all(16.0),
                            decoration: BoxDecoration(
                              color: cs.primary,
                              borderRadius: BorderRadius.circular(16.0),
                              boxShadow: [
                                BoxShadow(
                                  color: cs.shadow.withValues(alpha: 0.12),
                                  blurRadius: 4,
                                  offset: const Offset(0, 2),
                                ),
                              ],
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                if (content.isNotEmpty)
                                  Text(
                                    content,
                                    style: tt.titleMedium?.copyWith(
                                      color: cs.onPrimary,
                                    ),
                                  ),
                              ],
                            ),
                          ),
                        )
                      else if (hasEmbeddedUi) ...[
                        if (!hasFlowContext && content.isNotEmpty)
                          Align(
                            alignment: Alignment.centerLeft,
                            child: Padding(
                              padding: const EdgeInsets.only(
                                left: 16.0,
                                right: 16.0,
                                top: 0,
                                bottom: 0,
                              ),
                              child: Text(
                                content,
                                style: tt.titleMedium?.copyWith(color: cs.onSurface),
                              ),
                            ),
                          ),
                      ] else if (!hasFlowContext)
                        Align(
                          alignment: Alignment.centerLeft,
                          child: Container(
                            margin: const EdgeInsets.fromLTRB(
                              16.0,
                              4.0,
                              0,
                              0.0,
                            ),
                            padding: const EdgeInsets.all(16.0),
                            decoration: BoxDecoration(
                              color: cs.surfaceContainerHighest,
                              borderRadius: BorderRadius.circular(16.0),
                              boxShadow: [
                                BoxShadow(
                                  color: cs.shadow.withValues(alpha: 0.12),
                                  blurRadius: 4,
                                  offset: const Offset(0, 2),
                                ),
                              ],
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                if (content.isNotEmpty)
                                  Text(
                                    content,
                                    style: tt.titleMedium?.copyWith(
                                      color: cs.onSurface,
                                    ),
                                  ),
                              ],
                            ),
                          ),
                        ),
                    ],
                    // Acciones si existen
                    if (!isUser && actions != null && actions.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Padding(
                              padding: const EdgeInsets.only(
                                left: 16.0,
                                right: 16.0,
                                top: 0,
                                bottom: 0,
                              ),
                        child: Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: actions.map((action) {
                            return ActionChip(
                              label: Text(
                                action['display_name'] ?? action['title'] ?? action['label'] ?? 'Acción',
                                style: tt.labelLarge?.copyWith(fontSize: 12),
                              ),
                              avatar: Icon(
                                Icons.touch_app,
                                size: 16,
                              ),
                              onPressed: () => _executeAction(action, messageIndex: index),
                              backgroundColor: cs.primary.withValues(alpha: 0.1),
                              labelStyle: tt.labelLarge?.copyWith(
                                fontSize: 12,
                                color: cs.primary,
                              ),
                            );
                          }).toList(),
                        ),
                      ),
                    ],
                    if (!isUser &&
                        _messageInteractiveButtons(message).isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16.0),
                        child: Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: _messageInteractiveButtons(message).map((raw) {
                            final opt = Map<String, dynamic>.from(raw);
                            final label = opt['label']?.toString() ??
                                opt['intent_id']?.toString() ??
                                'Opción';
                            return ActionChip(
                              label: Text(
                                label,
                                style: tt.labelLarge?.copyWith(fontSize: 12),
                              ),
                              avatar: const Icon(Icons.alt_route, size: 16),
                              onPressed: _isSending
                                  ? null
                                  : () => _onRemediationChoice(opt),
                              backgroundColor: cs.primary.withValues(alpha: 0.12),
                              labelStyle: tt.labelLarge?.copyWith(
                                fontSize: 12,
                                color: cs.primary,
                              ),
                            );
                          }).toList(),
                        ),
                      ),
                    ],
                    // Inline UI JSON embebida en chat (antes del "Confirmar y enviar" del flow)
                    if (!isUser && inlineUi is Map)
                      _wrapFlowInteractiveCollapse(
                        collapsing: flowCollapsing,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                      SizedBox(height: inlineUiLeadGapHeight),
                      if (message['flow_tabs'] is List && (message['flow_tabs'] as List).length >= 2) ...[
                        Padding(
                          padding: const EdgeInsets.only(left: 16.0, right: 16.0, top: 0, bottom: 0),
                          child: Align(
                            alignment: Alignment.centerLeft,
                            child: Wrap(
                              spacing: 6,
                              runSpacing: 6,
                              children: (message['flow_tabs'] as List).map((tab) {
                                if (tab is! Map) {
                                  return const SizedBox.shrink();
                                }
                                final tm = Map<String, dynamic>.from(tab);
                                final id = tm['id']?.toString() ?? '';
                                final label = tm['label']?.toString() ?? id;
                                final def = message['flow_default_tab']?.toString() ?? '';
                                final sel = message['flow_selected_tab_id']?.toString() ?? def;
                                final selected = id.isNotEmpty && sel == id;
                                return ChoiceChip(
                                  label: Text(
                                    label,
                                    style: tt.labelLarge?.copyWith(fontSize: 12),
                                  ),
                                  selected: selected,
                                  onSelected: flowUiDisabled
                                      ? null
                                      : (_) => _onFlowManifestTabSelected(index, tm),
                                );
                              }).toList(),
                            ),
                          ),
                        ),
                        const SizedBox(height: 4),
                      ],
                      Padding(
                        padding: const EdgeInsets.only(left: 8.0, right: 8.0, top: 0.0, bottom: 0),
                        child: Align(
                          alignment: Alignment.topLeft,
                          child: ConstrainedBox(
                            constraints: BoxConstraints(
                              maxWidth: MediaQuery.of(context).size.width - 32,
                            ),
                            child: AnimatedSize(
                              duration: const Duration(milliseconds: 180),
                              curve: Curves.easeOut,
                              alignment: Alignment.center,
                              child: AbsorbPointer(
                                absorbing: flowUiDisabled,
                                child: Opacity(
                                  opacity: flowUiDisabled ? 0.55 : 1,
                                  child: _KeepAliveChatChild(
                                keepAlive: hasEmbeddedUi,
                                child: UiJsonScreen(
                              key: ObjectKey('inline-ui-$index'),
                              initialDefinition: inlineUi['ui_definition'] is Map
                                  ? Map<String, dynamic>.from(inlineUi['ui_definition'] as Map)
                                  : null,
                              onDefinitionLoaded: flowUiDisabled
                                  ? null
                                  : (def) {
                                      inlineUi['ui_definition'] = def;
                                    },
                              onEmbeddedReady: flowUiDisabled
                                  ? null
                                  : () {
                                      if (!mounted ||
                                          index < 0 ||
                                          index >= _chatHistory.length) {
                                        return;
                                      }
                                      setState(() {
                                        _chatHistory[index]['_flow_submit_ready'] = true;
                                      });
                                    },
                              enableFlowChainAutoAdvance: !flowUiDisabled &&
                                  inlineUi['ui_definition'] == null,
                              apiAbsoluteUrl: (inlineUi['api_absolute_url']?.toString() ?? '').trim().isNotEmpty
                                  ? inlineUi['api_absolute_url']!.toString()
                                  : applyProvidedParamsToRoute(
                                      inlineUi['route']?.toString() ?? '',
                                      inlineUi['provided'] is Map ? Map<String, dynamic>.from(inlineUi['provided'] as Map) : null,
                                    ),
                              authToken: _asistenteService.authToken,
                              appClient: 'bioenlace-paciente',
                              title: inlineUi['title']?.toString(),
                              embedded: true,
                              onCancel: flowUiDisabled
                                  ? null
                                  : (_inlineUiIsConfirmacionTurno(inlineUi) ? _resetAssistantToWelcome : null),
                              onDraftDelta: flowUiDisabled ? null : (dd) async {
                                // Cambio 1: si este mensaje NO es el último interactivo del flow
                                // activo, "rebobinar" el flow: eliminar mensajes posteriores del
                                // mismo flow y limpiar las keys del draft que esos pasos proveyeron.
                                // Luego se vuelve a pedir el siguiente paso al motor con el draft
                                // truncado + la nueva elección.
                                final activeIid = _intentId;
                                if (activeIid != null && activeIid.isNotEmpty) {
                                  final lastIdx = _lastFlowInteractiveMessageIndex(activeIid);
                                  if (lastIdx != null && index < lastIdx) {
                                    setState(() {
                                      _truncateFlowAfter(index);
                                    });
                                  }
                                }
                                _applyDraftDelta(Map<String, dynamic>.from(dd));
                                // El descriptor GET puede llevar filtros en query (`id_servicio`, …) vía `provided`;
                                // si el draft local no tiene aún `id_servicio_asignado`, lo tomamos de la URL resuelta
                                // para que SubIntentEngine no vuelva a abrir la misma UI por draft incompleto.
                                final absRaw = inlineUi['api_absolute_url']?.toString() ?? '';
                                final routeRaw = inlineUi['route']?.toString() ?? '';
                                final providedMap = inlineUi['provided'] is Map
                                    ? Map<String, dynamic>.from(inlineUi['provided'] as Map)
                                    : null;
                                final resolved = absRaw.trim().isNotEmpty
                                    ? absRaw
                                    : (routeRaw.isNotEmpty ? applyProvidedParamsToRoute(routeRaw, providedMap) : '');
                                if (resolved.isNotEmpty) {
                                  final u = Uri.tryParse(resolved);
                                  if (u != null) {
                                    final idServicio = u.queryParameters['id_servicio_asignado'] ??
                                        u.queryParameters['id_servicio'];
                                    if (idServicio != null && idServicio.isNotEmpty) {
                                      _draft.putIfAbsent('id_servicio_asignado', () => idServicio);
                                    }
                                  }
                                }
                                _asistenteService.draft = Map<String, dynamic>.from(_draft);

                                // Step terminal (último del flow): el tap mergeó local; NO postear al motor.
                                // El submit lo dispara el botón "Confirmar y enviar" del mismo mensaje.
                                if (_messageIsTerminalFlowStep(message)) {
                                  // Si había un aviso previo de "falta X" y ahora X está en el draft, limpiarlo.
                                  if (message['_flow_submit_missing'] is List) {
                                    setState(() {
                                      message.remove('_flow_submit_missing');
                                    });
                                  }
                                  return;
                                }

                                // Avanzar el flow automáticamente (snapshot) sin texto.
                                final res = await _asistenteService.procesarInteraccion('');
                                if (!mounted) return;
                                if (res['success'] == true) {
                                  final data = res['data'];
                                  if (data is Map) {
                                    await _handleFlowEnvelopeResponse(
                                      Map<String, dynamic>.from(data),
                                    );
                                  }
                                }
                              },
                              onSubmitSuccess: flowUiDisabled ? null : (data) async {
                                final rc = data['razon_cancelacion']?.toString().trim();
                                if (rc != null && rc.isNotEmpty) {
                                  setState(() {
                                    _applyDraftDelta({
                                      'razon_cancelacion': rc,
                                      '_flow_item_razon_cancelacion': {
                                        'code': rc,
                                        'label': etiquetaRazonCancelacionPaciente(rc),
                                      },
                                    });
                                  });
                                }
                                // Para submits (fields/custom_widget) no hay draft_delta local; igualmente avanzamos el flow.
                                _asistenteService.draft = Map<String, dynamic>.from(_draft);
                                final res = await _asistenteService.procesarInteraccion('');
                                if (!mounted) return;
                                if (res['success'] == true) {
                                  final data = res['data'];
                                  if (data is Map) {
                                    await _handleFlowEnvelopeResponse(
                                      Map<String, dynamic>.from(data),
                                    );
                                  }
                                }
                              },
                            ),
                          ),
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                          ],
                        ),
                      ),
                    if (!isUser && _showFlowSubmitButton(message))
                      _wrapFlowInteractiveCollapse(
                        collapsing: flowCollapsing,
                        child: Padding(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              // Aviso inline si faltan campos del draft al apretar (Cambio 2).
                              if (message['_flow_submit_missing'] is List &&
                                  (message['_flow_submit_missing'] as List).isNotEmpty) ...[
                                BioAlert.danger(
                                  message: _missingFieldsMessage(
                                    List<String>.from(
                                      (message['_flow_submit_missing'] as List)
                                          .map((e) => e.toString()),
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 8),
                              ],
                              BioButton.primary(
                                label: 'Confirmar y enviar',
                                icon: Icons.check_circle_outline,
                                size: BioButtonSize.lg,
                                fullWidth: true,
                                loading: message['_flow_submit_busy'] == true,
                                // Siempre habilitado salvo: flow superseded / colapsando / submit en curso.
                                // Si faltan campos del draft, `_postFlowSubmitFromMessage` muestra el alert
                                // inline y no envía (descubrible para el usuario).
                                onPressed: (flowUiDisabled ||
                                        flowCollapsing ||
                                        message['_flow_submit_busy'] == true ||
                                        _isSending)
                                    ? null
                                    : () => _postFlowSubmitFromMessage(index),
                              ),
                            ],
                          ),
                        ),
                      ),
                    // Formulario dinámico si hay form_config (viene de getActionFormConfig)
                    if (!isUser) ...[
                      Builder(
                        builder: (context) {
                          final formConfig = message['form_config'];
                          final wizardSteps = message['wizard_steps'];
                          final actionIdFromMessage = message['action_id'];
                          final actionName = message['action_name'];
      // final parametersFromMessage = message['parameters'];
                          
                          if (formConfig != null && formConfig is Map) {
                            // Card tappable: un solo tap abre directamente el formulario en modal
                            return Column(
                              children: [
                                const SizedBox(height: 16),
                                Padding(
                                  padding: const EdgeInsets.symmetric(horizontal: 16.0),
                                  child: Material(
                                    color: cs.primary.withValues(alpha: 0.06),
                                    borderRadius: BorderRadius.circular(12),
                                    child: InkWell(
                                      onTap: () {
                                        _showFormModal(
                                          context: context,
                                          formConfig: Map<String, dynamic>.from(formConfig),
                                          wizardSteps: wizardSteps != null ? List<Map<String, dynamic>>.from(wizardSteps.map((step) => Map<String, dynamic>.from(step))) : null,
                                          initialStep: message['initial_step'] as int? ?? 0,
                                          title: actionName ?? 'Completa la información',
                                          authToken: _asistenteService.authToken,
                                          onSubmit: (formValues) async {
                                            if (actionIdFromMessage != null) {
                                              await _executeActionWithParams(actionIdFromMessage, formValues);
                                            }
                                          },
                                          onCancel: () {
                                            setState(() {
                                              _chatHistory.removeAt(index);
                                            });
                                          },
                                        );
                                      },
                                      borderRadius: BorderRadius.circular(12),
                                      child: Container(
                                        padding: const EdgeInsets.all(16.0),
                                        decoration: BoxDecoration(
                                          borderRadius: BorderRadius.circular(12),
                                          border: Border.all(
                                            color: cs.primary.withValues(alpha: 0.3),
                                          ),
                                        ),
                                        child: Row(
                                          children: [
                                            Icon(
                                              Icons.input,
                                              size: 20,
                                              color: cs.primary,
                                            ),
                                            const SizedBox(width: 8),
                                            Expanded(
                                              child: Text(
                                                actionName ?? 'Completa la información',
                                                style: tt.titleSmall?.copyWith(
                                                  fontWeight: FontWeight.bold,
                                                  color: cs.primary,
                                                ),
                                              ),
                                            ),
                                            Icon(
                                              Icons.arrow_forward_ios,
                                              size: 14,
                                              color: cs.primary,
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                              ],
                            );
                          }
                          
                          // Si no hay form_config, verificar si hay actionAnalysis (comportamiento anterior)
                          final actionAnalysis = message['action_analysis'];
                          final needsUserInput = message['needs_user_input'] ?? false;
                          
                          if (needsUserInput && actionAnalysis != null && actionAnalysis is Map) {
                            final actionId = actionAnalysis['action_id'] as String?;
                            return Column(
                              children: [
                                const SizedBox(height: 16),
                                Padding(
                                  padding: const EdgeInsets.symmetric(horizontal: 16.0),
                                  child: Material(
                                    color: cs.primary.withValues(alpha: 0.06),
                                    borderRadius: BorderRadius.circular(12),
                                    child: InkWell(
                                      onTap: () {
                                        _showFormModal(
                                          context: context,
                                          formConfig: Map<String, dynamic>.from(actionAnalysis['form_config'] ?? {}),
                                          title: 'Completa la información',
                                          authToken: _asistenteService.authToken,
                                          onSubmit: (formValues) async {
                                            if (actionId != null) {
                                              await _executeActionWithParams(actionId, formValues);
                                            }
                                          },
                                          onCancel: () {
                                            setState(() {
                                              _chatHistory.removeAt(index);
                                            });
                                          },
                                        );
                                      },
                                      borderRadius: BorderRadius.circular(12),
                                      child: Container(
                                        padding: const EdgeInsets.all(16.0),
                                        decoration: BoxDecoration(
                                          borderRadius: BorderRadius.circular(12),
                                          border: Border.all(
                                            color: cs.primary.withValues(alpha: 0.3),
                                          ),
                                        ),
                                        child: Row(
                                          children: [
                                            Icon(
                                              Icons.input,
                                              size: 20,
                                              color: cs.primary,
                                            ),
                                            const SizedBox(width: 8),
                                            Expanded(
                                              child: Text(
                                                'Completa la información',
                                                style: tt.titleSmall?.copyWith(
                                                  fontWeight: FontWeight.bold,
                                                  color: cs.primary,
                                                ),
                                              ),
                                            ),
                                            Icon(
                                              Icons.arrow_forward_ios,
                                              size: 14,
                                              color: cs.primary,
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                              ],
                            );
                          }
                          
                          return const SizedBox.shrink();
                        },
                      ),
                    ],
                    // Consulta sugerida si existe
                    if (!isUser && suggestedQuery != null && suggestedQuery.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Padding(
                        padding: const EdgeInsets.only(
                          left: 16.0,
                          right: 16.0,
                          top: 0,
                          bottom: 0,
                        ),
                        child: BioButton.outlinePrimary(
                          label: suggestedQuery,
                          icon: Icons.lightbulb_outline,
                          size: BioButtonSize.sm,
                          onPressed: () {
                            _messageController.text = suggestedQuery;
                            _sendMessage();
                          },
                        ),
                      ),
                    ],
                    // CTA Cargar motivos cuando la respuesta incluye id_consulta (ej. tras crear turno)
                    if (!isUser) ...[
                      Builder(
                        builder: (context) {
                          final data = message['data'];
                          final rawId = data is Map
                              ? (data['encounter_id'] ?? data['id_consulta'])
                              : null;
                          final idConsultaInt = rawId is int
                              ? rawId
                              : (rawId is num
                                  ? rawId.toInt()
                                  : int.tryParse('$rawId'));
                          if (idConsultaInt == null || idConsultaInt <= 0) {
                            return const SizedBox.shrink();
                          }
                          return Padding(
                            padding: const EdgeInsets.only(left: 16.0, right: 16.0, top: 0.0),
                            child: ActionChip(
                              avatar: Icon(Icons.edit_note, size: 18, color: cs.primary),
                              label: const Text('Cargar motivos de la consulta'),
                              onPressed: () {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => ChatMotivosScreen(
                                      consultaId: idConsultaInt,
                                      authToken: _asistenteService.authToken,
                                      userId: widget.chatService.currentUserId,
                                      userName: widget.chatService.currentUserName,
                                      titulo: 'Motivos de la consulta',
                                    ),
                                  ),
                                );
                              },
                              backgroundColor: cs.primary.withValues(alpha: 0.1),
                              labelStyle: tt.labelLarge?.copyWith(color: cs.primary, fontSize: 13),
                            ),
                          );
                        },
                      ),
                    ],
                  ],
                );
              },
            ),
          ),
          AssistantChatComposerBar(
            controller: _messageController,
            onSend: _sendMessage,
            isSending: _isSending,
          ),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _messageController.removeListener(_onComposerTextChanged);
    _messageController.dispose();
    _scrollController.dispose();
    _welcomeShortcutsScrollController.dispose();
    super.dispose();
  }
}

/// Resumen final del flow con entrada suave tras el colapso de las mini-UIs.
class _AnimatedFlowCompletedSummary extends StatefulWidget {
  final Duration fadeInDuration;
  final String? title;
  final String content;
  final Widget Function(BuildContext context)? headerBuilder;
  final Color surfaceColor;
  final Color borderColor;
  final TextStyle? textStyle;

  const _AnimatedFlowCompletedSummary({
    required this.fadeInDuration,
    required this.content,
    required this.surfaceColor,
    required this.borderColor,
    this.title,
    this.headerBuilder,
    this.textStyle,
  });

  @override
  State<_AnimatedFlowCompletedSummary> createState() =>
      _AnimatedFlowCompletedSummaryState();
}

class _AnimatedFlowCompletedSummaryState
    extends State<_AnimatedFlowCompletedSummary> {
  double _opacity = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        setState(() => _opacity = 1);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedOpacity(
      opacity: _opacity,
      duration: widget.fadeInDuration,
      curve: Curves.easeOut,
      child: AnimatedSlide(
        offset: _opacity < 1 ? const Offset(0, 0.08) : Offset.zero,
        duration: widget.fadeInDuration,
        curve: Curves.easeOut,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (widget.headerBuilder != null) widget.headerBuilder!(context),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: widget.surfaceColor,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: widget.borderColor),
                ),
                child: Text(
                  widget.content,
                  style: widget.textStyle,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Evita que el scroll del chat desmonte mini-UIs y vuelva a pedir el descriptor GET.
class _KeepAliveChatChild extends StatefulWidget {
  final Widget child;
  final bool keepAlive;

  const _KeepAliveChatChild({
    required this.child,
    this.keepAlive = true,
  });

  @override
  State<_KeepAliveChatChild> createState() => _KeepAliveChatChildState();
}

class _KeepAliveChatChildState extends State<_KeepAliveChatChild>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => widget.keepAlive;

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return widget.child;
  }
}