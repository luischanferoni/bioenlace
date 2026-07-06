import 'dart:async';

import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../services/chat_service.dart';
import '../services/notificaciones_service.dart';
import '../services/push_notification_service.dart';
import '../utils/turno_resolucion_utils.dart';
import 'alertas_screen.dart';
import 'home_screen.dart';
import 'chat_screen.dart';
import 'care_plan_detail_screen.dart';
import 'configuracion_screen.dart';
import 'encounter_summary_detail_screen.dart';
import 'chat_motivos_screen.dart';
import 'paciente_provincia_context_screen.dart';
import '../config/paciente_intents.dart';

/// Pantalla principal del paciente con bottom nav: Inicio, Asistente, Configuración.
class MainScreen extends StatefulWidget {
  final ChatService chatService;
  final String? authToken;

  const MainScreen({
    Key? key,
    required this.chatService,
    this.authToken,
  }) : super(key: key);

  @override
  State<MainScreen> createState() => _MainScreenState();
}

class _MainScreenState extends State<MainScreen> {
  int _selectedIndex = 0;
  PendingTurnoResolver? _pendingResolver;
  String? _pendingIntentId;
  Map<String, String> _pendingFlowDraft = {};
  int _alertasNoLeidas = 0;
  final GlobalKey<HomeScreenState> _homeKey = GlobalKey<HomeScreenState>();

  @override
  void initState() {
    super.initState();
    PacienteContextScope.instance.bindAuthToken(widget.authToken);
    PacienteContextScope.instance.refresh(authToken: widget.authToken);
    _initPush();
    _initCarePlanReminders();
    _refreshAlertasCount();
  }

  void _initCarePlanReminders() {
    CarePlanLocalReminderService.onNotificationTap = ({
      required int carePlanId,
      required int activityId,
    }) {
      if (!mounted) return;
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => CarePlanDetailScreen(
            planId: carePlanId,
            authToken: widget.authToken,
          ),
        ),
      );
    };
    unawaited(
      CarePlanLocalReminderService.instance.syncFromApi(
        authToken: widget.authToken,
      ),
    );
  }

  Future<void> _initPush() async {
    await PushNotificationService.instance.init(
      onOpen: (data) {
        final journeyPush = journeyPushDesdeData(data);
        if (journeyPush != null) {
          _abrirJourneyDesdePush(journeyPush);
          return;
        }
        final touchpointId =
            PushNotificationService.followupTouchpointIdDesdePush(data);
        if (touchpointId != null) {
          _abrirFollowupTouchpoint(touchpointId);
          return;
        }
        final encounterId = PushNotificationService.encounterIdDesdePush(data);
        if (encounterId != null) {
          _abrirResumenAtencion(encounterId);
          return;
        }
        final stub = PushNotificationService.turnoStubDesdePush(data);
        if (stub != null) {
          _abrirResolverTurno(stub);
        } else {
          _openAlertas();
        }
      },
    );
    await PushNotificationService.instance.registerTokenIfLoggedIn();
  }

  Future<void> _refreshAlertasCount() async {
    final svc = NotificacionesService(authToken: widget.authToken);
    final r = await svc.listar(soloNoLeidas: true, limit: 1);
    if (!mounted) return;
    if (r['success'] == true) {
      setState(() => _alertasNoLeidas = r['no_leidas'] as int? ?? 0);
    }
  }

  void _abrirResolverTurno(Map<String, dynamic> turno) {
    setState(() {
      _selectedIndex = 1;
      _pendingResolver = PendingTurnoResolver(turno);
    });
  }

  void _abrirResumenAtencion(int encounterId) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => EncounterSummaryDetailScreen(
          encounterId: encounterId,
          authToken: widget.authToken,
        ),
      ),
    );
  }

  void _abrirFollowupTouchpoint(int touchpointId) {
    final path =
        AppConfig.normalizeApiV1Path('/api/v1/care-packs/followup?touchpoint_id=$touchpointId');
    final uri = path.startsWith('http')
        ? Uri.parse(path)
        : Uri.parse('${AppConfig.apiUrl}$path');
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => UiJsonScreen(
          apiAbsoluteUrl: uri.toString(),
          authToken: widget.authToken,
          appClient: 'paciente-flutter',
        ),
      ),
    );
  }

  Future<void> _abrirJourneyDesdePush(Map<String, String> push) async {
    final turnoId = int.tryParse(push['id_turno'] ?? '') ?? 0;
    if (turnoId <= 0) return;

    final api = EncounterJourneyApi(authToken: widget.authToken);
    final estado = await api.fetchEstado(turnoId: turnoId);
    if (!mounted) return;
    if (estado == null) {
      _openAlertas();
      return;
    }

    final touchpointRaw = push['touchpoint_id'];
    if (touchpointRaw != null && touchpointRaw.isNotEmpty) {
      final touchpointId = int.tryParse(touchpointRaw) ?? 0;
      if (touchpointId > 0) {
        _abrirFollowupTouchpoint(touchpointId);
        return;
      }
    }

    var turno = turnoConJourneyDesdeEstado({'id': turnoId}, estado);
    final encounterRaw = push['encounter_id'];
    if (encounterRaw != null && encounterRaw.isNotEmpty) {
      final eid = int.tryParse(encounterRaw);
      if (eid != null && eid > 0) {
        turno['encounter_id'] = eid;
        turno['id_consulta'] = eid;
      }
    }

    final phase = push['phase']?.trim() ?? '';
    if (phase.isNotEmpty) {
      final phaseMap = journeyPhase(turno, phase);
      if (phaseMap != null &&
          (phaseMap['enabled'] == true ||
              phase == kEncounterJourneyPhasePostConsulta)) {
        abrirFaseEncounterJourney(
          context: context,
          turno: turno,
          phaseId: phase,
          authToken: widget.authToken,
          onOpenMotivos: _abrirMotivosDesdeJourney,
        );
        return;
      }
    }

    if (seguimientoPostConsultaTienePendientes(turno)) {
      abrirSeguimientoPostConsultaHub(
        context: context,
        turno: turno,
        authToken: widget.authToken,
        onOpenMotivos: _abrirMotivosDesdeJourney,
      );
      return;
    }

    abrirPrepararConsultaHub(
      context: context,
      turno: turno,
      authToken: widget.authToken,
      onOpenMotivos: _abrirMotivosDesdeJourney,
    );
  }

  void _abrirMotivosDesdeJourney(
    BuildContext context, {
    required int consultaId,
    required String titulo,
  }) {
    final chat = widget.chatService;
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ChatMotivosScreen(
          consultaId: consultaId,
          authToken: widget.authToken,
          userId: chat.currentUserId,
          userName: chat.currentUserName,
          titulo: titulo,
        ),
      ),
    );
  }

  void _onPendingResolverHandled() {
    setState(() => _pendingResolver = null);
    _refreshAlertasCount();
    _homeKey.currentState?.refrescarProximos();
  }

  void _onPendingIntentHandled() {
    setState(() {
      _pendingIntentId = null;
      _pendingFlowDraft = {};
    });
  }

  void _abrirFlowAsistente(String intentId, {Map<String, String>? draft}) {
    setState(() {
      _selectedIndex = 1;
      _pendingIntentId = intentId;
      _pendingFlowDraft = draft ?? {};
    });
  }

  void _abrirIntentQueja() {
    setState(() {
      _selectedIndex = 1;
      _pendingIntentId = PacienteIntents.enviarQueja;
    });
  }

  void _openAlertas() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => AlertasScreen(
          authToken: widget.authToken,
          onAbrirResolver: _abrirResolverTurno,
        ),
      ),
    ).then((_) => _refreshAlertasCount());
  }

  void _abrirProvinciaContexto() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => PacienteProvinciaContextScreen(
          authToken: widget.authToken,
        ),
      ),
    ).then((changed) {
      if (changed == true) {
        PacienteContextScope.instance.refresh(authToken: widget.authToken);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(
        index: _selectedIndex,
        children: [
          HomeScreen(
            key: _homeKey,
            userId: widget.chatService.currentUserId,
            userName: widget.chatService.currentUserName,
            authToken: widget.authToken,
            alertasNoLeidas: _alertasNoLeidas,
            onOpenAlertas: _openAlertas,
            onResolverTurno: _abrirResolverTurno,
            onStartAssistantFlow: _abrirFlowAsistente,
          ),
          ChatScreen(
            chatService: widget.chatService,
            pendingResolver: _pendingResolver,
            onPendingResolverHandled: _onPendingResolverHandled,
            pendingIntentId: _pendingIntentId,
            pendingFlowDraft: _pendingFlowDraft,
            onPendingIntentHandled: _onPendingIntentHandled,
            onConfigurarProvincia: _abrirProvinciaContexto,
          ),
          ConfiguracionScreen(
            userId: widget.chatService.currentUserId,
            userName: widget.chatService.currentUserName,
            authToken: widget.authToken,
            onEnviarQueja: _abrirIntentQueja,
          ),
        ],
      ),
      bottomNavigationBar: BioBottomNav(
        currentIndex: _selectedIndex,
        onTap: (index) {
          setState(() => _selectedIndex = index);
          if (index == 0) {
            _refreshAlertasCount();
          }
        },
        items: const [
          BioBottomNavItem(icon: Icons.home_outlined, label: 'Inicio'),
          BioBottomNavItem(icon: Icons.chat_bubble_outline, label: 'Asistente'),
          BioBottomNavItem(icon: Icons.settings_outlined, label: 'Configuración'),
        ],
      ),
    );
  }
}
