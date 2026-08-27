// lib/screens/home_screen.dart
import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import '../models/turno.dart';
import '../models/cirugia_agenda_item.dart';
import '../auth/personalsalud_post_login.dart';
import '../services/internados_service.dart';
import '../services/emergency_guardia_api.dart';
import '../services/consulta_async_api.dart';
import 'emergency/emergency_guardia_actions.dart';
import 'patient_timeline_screen.dart';
import 'chat_consulta_screen.dart';

/// Pantalla principal del médico. Contenido según encounter class:
/// AMB = turnos del día; VR = consultas por mensaje; IMP = internados/cirugías; EMER = tablero de guardia.
class HomeScreen extends StatefulWidget {
  final String userId;
  final String userName;
  final String? authToken;
  final String? idProfesionalEfectorServicio;

  const HomeScreen({
    Key? key,
    required this.userId,
    required this.userName,
    this.authToken,
    this.idProfesionalEfectorServicio,
  }) : super(key: key);

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  late final HomePanelApi _homePanelApi = HomePanelApi(
    authToken: widget.authToken,
    userId: widget.userId,
    appClient: 'personalsalud-flutter',
  );
  late final EmergencyGuardiaApi _emergencyApi = EmergencyGuardiaApi(
    authToken: widget.authToken,
    userId: widget.userId,
  );
  late ConsultaAsyncApi _consultaAsyncApi = ConsultaAsyncApi(
    authToken: widget.authToken,
    userId: widget.userId,
  );

  List<Turno> _turnos = [];
  List<Map<String, dynamic>> _consultasAsync = [];
  List<Map<String, dynamic>> _consultasAsyncGroups = [];
  String _tituloConsultasAsync = 'Consultas clínicas por mensaje';
  int _consultasAsyncSlaIncumplidos = 0;
  final Set<int> _tomandoAsyncIds = {};
  List<InternadoItem> _internados = [];
  /// `recorrido` = piso→sala→cama; `nombre` = A–Z.
  String _internadosOrden = 'recorrido';
  List<EmergencyBoardItem> _guardiaTablero = [];
  List<CirugiaAgendaItem> _cirugias = [];
  List<HomePanelKpiGroup> _kpiGroups = [];
  bool _sessionTieneHorario = false;
  bool _puedeTriage = false;
  bool _puedeIngresar = false;
  bool _puedeAtender = false;
  bool _puedeDocumentar = false;
  String? _mensajeSinHorario;
  Map<String, dynamic>? _staffContext;
  String _lastListKind = '';
  bool _isLoading = true;
  String _errorMessage = '';

  DateTime _fechaSeleccionada = DateTime(
    DateTime.now().year,
    DateTime.now().month,
    DateTime.now().day,
  );

  String _encounterClass = 'AMB';

  /// Poll del tablero EMER (cancelable). Antes era Future.delayed sin cancel →
  /// cada refresh/ciclo armaba otra cadena y las requests se acumulaban.
  Timer? _tableroPollTimer;
  int _tableroPollSeconds = 30;

  @override
  void initState() {
    super.initState();
    _homePanelApi.userId = widget.userId;
    _emergencyApi.userId = widget.userId;
    _init();
  }

  @override
  void dispose() {
    _stopTableroPoll();
    super.dispose();
  }

  Future<void> _init() async {
    await _loadAuthToken();
    await _loadEncounterAndData();
  }

  Future<void> _loadAuthToken() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      // Tras el wizard, prefs tiene el context_token con encounter_class en el JWT.
      final token = prefs.getString('auth_token');
      if (token != null && token.isNotEmpty) {
        _homePanelApi.authToken = token;
        _emergencyApi.authToken = token;
        _consultaAsyncApi = ConsultaAsyncApi(authToken: token, userId: widget.userId);
      } else if (widget.authToken != null && widget.authToken!.isNotEmpty) {
        _homePanelApi.authToken = widget.authToken;
        _emergencyApi.authToken = widget.authToken;
        _consultaAsyncApi = ConsultaAsyncApi(authToken: widget.authToken, userId: widget.userId);
      } else {
        _homePanelApi.userId = widget.userId;
      }
    } catch (e) {
      setState(() {
        _errorMessage = userFriendlyErrorMessage(e);
        _isLoading = false;
      });
    }
  }

  Future<void> _loadEncounterAndData() async {
    final prefs = await SharedPreferences.getInstance();
    final encounter = prefs.getString('encounter_class');
    if (encounter == null || encounter.isEmpty) {
      if (!mounted) return;
      await recoverPersonalsaludOperationalSession(
        userId: widget.userId,
        userName: widget.userName,
        authToken: _homePanelApi.authToken ?? widget.authToken,
      );
      return;
    }
    setState(() {
      _encounterClass = encounter;
    });
    await _cargarListadoPacientes();
  }

  Future<void> _cargarListadoPacientes({bool silent = false}) async {
    if (!silent) {
      setState(() {
        _isLoading = true;
        _errorMessage = '';
        _lastListKind = '';
      });
    }
    try {
      final fechaStr = DateFormat('yyyy-MM-dd').format(_fechaSeleccionada);
      final panel = await _homePanelApi.getPanel(
        fecha: fechaStr,
        sections: silent && _encounterClass == 'EMER'
            ? 'staff_horario_activo,emergency_board,emergency_indicators'
            : null,
      );
      if (!mounted) return;
      _applyHomePanel(
        panel,
        partial: silent && _encounterClass == 'EMER',
      );
      if (_encounterClass == 'EMER') {
        _rememberTableroPollInterval(panel);
        // Solo armar el timer en carga completa. El silent lo reprograma el callback del Timer.
        if (!silent) {
          _startTableroPoll();
        }
      } else {
        _stopTableroPoll();
      }
    } catch (e) {
      if (!mounted) return;
      if (isPersonalsaludEncounterSessionError(e)) {
        await recoverPersonalsaludOperationalSession(
          userId: widget.userId,
          userName: widget.userName,
          authToken: _homePanelApi.authToken ?? widget.authToken,
        );
        return;
      }
      if (BearerSessionAuth.isAuthSessionError(e)) {
        await returnPersonalsaludToLogin(
          message: 'Tu sesión expiró. Ingresá de nuevo.',
        );
        return;
      }
      setState(() {
        _errorMessage = userFriendlyErrorMessage(e);
        _isLoading = false;
      });
    }
  }

  void _rememberTableroPollInterval(HomePanelResponse panel) {
    final candidates = <int>[];
    for (final kind in ['emergency_board', 'staff_horario_activo']) {
      final sec = panel.sectionByKind(kind)?.pollIntervalSeconds;
      if (sec != null && sec > 0) {
        candidates.add(sec);
      }
    }
    if (candidates.isEmpty) {
      return;
    }
    candidates.sort();
    // Intervalo del tablero (típicamente 30s); piso 15s para no saturar la API.
    _tableroPollSeconds = candidates.first.clamp(15, 300);
  }

  void _startTableroPoll() {
    _stopTableroPoll();
    if (_encounterClass != 'EMER') return;
    _tableroPollTimer = Timer(Duration(seconds: _tableroPollSeconds), () async {
      if (!mounted || _encounterClass != 'EMER') return;
      await _cargarListadoPacientes(silent: true);
      if (!mounted || _encounterClass != 'EMER') return;
      _startTableroPoll();
    });
  }

  void _stopTableroPoll() {
    _tableroPollTimer?.cancel();
    _tableroPollTimer = null;
  }

  void _applyHomePanel(HomePanelResponse panel, {bool partial = false}) {
    setState(() {
      if (!partial) {
        _turnos = [];
        _internados = [];
        _guardiaTablero = [];
        _cirugias = [];
        _sessionTieneHorario = false;
        _puedeTriage = false;
        _puedeIngresar = false;
        _puedeAtender = false;
        _puedeDocumentar = false;
        _mensajeSinHorario = null;
        _consultasAsync = [];
        _consultasAsyncGroups = [];
        _consultasAsyncSlaIncumplidos = 0;
        _lastListKind = '';
        _staffContext = null;
      }
      _errorMessage = '';

      final newKpis = homePanelKpiGroupsFromResponse(panel);
      if (newKpis.isNotEmpty || !partial) {
        _kpiGroups = newKpis;
      }

      final ctx = panel.sectionByKind('staff_session_context');
      if (ctx != null) {
        _staffContext = Map<String, dynamic>.from(ctx.data);
      } else if (!partial) {
        _staffContext = null;
      }

      final horario = panel.sectionByKind('staff_horario_activo');
      if (horario != null) {
        final session = horario.data['session'];
        _sessionTieneHorario = session is Map && session['tiene_horario'] == true;
        final msg = session is Map ? session['mensaje_sin_horario'] : null;
        _mensajeSinHorario = msg is String && msg.trim().isNotEmpty ? msg.trim() : null;
      } else if (!partial) {
        _sessionTieneHorario = false;
        _mensajeSinHorario = null;
      }

      final board = panel.sectionByKind('emergency_board');
      if (board != null) {
        final items = board.data['items'] as List<dynamic>? ?? [];
        _guardiaTablero = items
            .map((e) => EmergencyBoardItem.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList();
        _puedeTriage = board.data['puede_triage'] == true;
        _puedeIngresar = board.data['puede_ingresar'] == true;
        _puedeAtender = board.data['puede_atender'] == true;
        _puedeDocumentar = board.data['puede_documentar'] == true;
        _lastListKind = 'guardias';
      }

      final appt = panel.sectionByKind('appointments_day');
      if (appt != null) {
        final items = appt.data['items'] as List<dynamic>? ?? [];
        _turnos = items
            .map((e) => Turno.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList();
        _lastListKind = 'turnos';
      }

      final asyncSec = panel.sectionByKind('async_consultations_queue');
      if (asyncSec != null) {
        final items = asyncSec.data['items'] as List<dynamic>? ?? [];
        _consultasAsync = items
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList();
        final rawGroups = asyncSec.data['groups'] as List<dynamic>? ?? [];
        _consultasAsyncGroups = rawGroups
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList();
        // Título de página: solo el del header (top nav). Preferir panel.title.
        final panelTitle = panel.title.trim();
        if (panelTitle.isNotEmpty) {
          _tituloConsultasAsync = panelTitle;
        }
        _consultasAsyncSlaIncumplidos =
            asyncSec.data['sla_incumplidos'] as int? ?? 0;
      } else if (!partial) {
        _consultasAsync = [];
        _consultasAsyncGroups = [];
        _consultasAsyncSlaIncumplidos = 0;
      }

      final inpat = panel.sectionByKind('inpatients');
      if (inpat != null) {
        final items = inpat.data['items'] as List<dynamic>? ?? [];
        _internados = items
            .map((e) => InternadoItem.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList();
        _lastListKind = 'internados';
      }

      final surg = panel.sectionByKind('surgeries_day');
      if (surg != null) {
        final items = surg.data['items'] as List<dynamic>? ?? [];
        _cirugias = items
            .map((e) => CirugiaAgendaItem.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList();
        _lastListKind = 'cirugias';
      }

      if (_lastListKind.isEmpty && panel.layout == 'staff_dashboard') {
        _lastListKind = 'staff_dashboard';
      }

      _isLoading = false;
    });
  }

  Widget _wrapWithPanelKpis(Widget child) {
    if (_kpiGroups.isEmpty && _staffContext == null) {
      return child;
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (_staffContext != null)
          HomePanelStaffContextBanner(data: _staffContext!),
        if (_kpiGroups.isNotEmpty) ...[
          HomePanelKpiGroupsList(groups: _kpiGroups),
          const SizedBox(height: BioSpacing.sm),
        ],
        Expanded(child: child),
      ],
    );
  }

  static const String _msgSinHorarioGuardiaFallback =
      'No tenés horario de guardia cargado. Para ver el tablero y atender, '
      'configurá tus horarios en el Asistente («Configurar mis horarios») o pedile a '
      'coordinación / administración del centro que te los asigne.';

  static const String _msgSinHorarioPisoFallback =
      'No tenés horario de piso cargado. Para ver internados, configurá tus '
      'horarios en el Asistente («Configurar mis horarios») o pedile a coordinación / '
      'administración del centro que te los asigne.';


  Widget _buildStaffDashboard() {
    if (_kpiGroups.isEmpty && _staffContext == null) {
      return _buildEmpty(
        icon: Icons.dashboard_outlined,
        text: 'No hay indicadores disponibles para tu rol en este efector.',
      );
    }
    return ListView(
      padding: const EdgeInsets.only(bottom: BioSpacing.xl),
      children: [
        if (_staffContext != null) HomePanelStaffContextBanner(data: _staffContext!),
        if (_kpiGroups.isNotEmpty) HomePanelKpiGroupsList(groups: _kpiGroups),
      ],
    );
  }

  void _cambiarFecha(int dias) {
    setState(() {
      _fechaSeleccionada = _soloFecha(_fechaSeleccionada).add(Duration(days: dias));
    });
    _cargarListadoPacientes();
  }

  void _irAHoy() {
    setState(() {
      _fechaSeleccionada = _soloFecha(DateTime.now());
    });
    _cargarListadoPacientes();
  }

  DateTime _soloFecha(DateTime fecha) =>
      DateTime(fecha.year, fecha.month, fecha.day);

  String _formatearFechaAmigable(DateTime fecha) {
    final hoy = _soloFecha(DateTime.now());
    final f = _soloFecha(fecha);
    final diferencia = f.difference(hoy).inDays;
    if (diferencia == 0) return 'Hoy';
    if (diferencia == 1) return 'Mañana';
    if (diferencia == -1) return 'Ayer';
    return DateFormat('EEEE, d \'de\' MMMM', 'es').format(fecha);
  }

  Turno? _obtenerSiguienteTurno() {
    if (_turnos.isEmpty) return null;
    final ahora = nowProducto();
    final turnosReales = _turnos
        .where((turno) => turno.id != 999999 && turno.estado == 'PENDIENTE')
        .toList();
    if (turnosReales.isEmpty) return null;
    for (final turno in turnosReales) {
      final inicio = parseTurnoInicioProducto({
        'fecha': turno.fecha,
        'hora': turno.hora,
      });
      if (inicio != null && inicio.isAfter(ahora)) {
        return turno;
      }
    }
    return null;
  }

  List<Turno> _getPendientes(Turno? siguienteTurno) {
    final siguienteId = siguienteTurno?.id;
    return _turnos
        .where((t) =>
            t.estado == 'PENDIENTE' &&
            t.id != 999999 &&
            (siguienteId == null || t.id != siguienteId))
        .toList();
  }

  List<Turno> _getConsultasCargadas() {
    return _turnos
        .where((t) => t.estado == 'ATENDIDO' && t.id != 999999)
        .toList();
  }

  /// Mapeo estado del turno → intent semántico (UiBadge).
  UiIntent _intentEstado(String estado) {
    switch (estado) {
      case 'PENDIENTE':
        return UiIntent.warning;
      case 'ATENDIDO':
        return UiIntent.success;
      case 'CANCELADO':
        return UiIntent.danger;
      case 'EN_ATENCION':
        return UiIntent.info;
      default:
        return UiIntent.neutral;
    }
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final esHoy = _fechaSeleccionada.year == DateTime.now().year &&
        _fechaSeleccionada.month == DateTime.now().month &&
        _fechaSeleccionada.day == DateTime.now().day;
    final siguienteTurno = esHoy ? _obtenerSiguienteTurno() : null;
    final puedeFiltrarFecha =
        _encounterClass == 'AMB' || _encounterClass == 'IMP';

    return Container(
      color: tokens.paperBackground,
      child: Column(
        children: [
          _buildHeader(context, puedeFiltrarFecha),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _errorMessage.isNotEmpty
                    ? _buildError(context)
                    : _lastListKind == 'staff_dashboard'
                    ? _buildStaffDashboard()
                    : _encounterClass == 'IMP'
                        ? _wrapWithPanelKpis(
                            _lastListKind == 'cirugias'
                                ? _buildCirugiasList()
                                : _buildInternadosList(),
                          )
                        : _encounterClass == 'EMER'
                            ? _wrapWithPanelKpis(_buildGuardiaTableroList())
                            : _encounterClass == 'VR'
                                ? _buildVrHomeContent()
                                : _wrapWithPanelKpis(
                                    _buildAmbHomeContent(siguienteTurno),
                                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader(BuildContext context, bool puedeFiltrarFecha) {
    final tokens = context.bio;
    return SafeArea(
      bottom: false,
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: BioSpacing.lg,
          vertical: BioSpacing.lg,
        ),
        decoration: BoxDecoration(
          color: tokens.paperSurface,
          border: BioBorder.bottom(BorderWidth.medium, tokens.paperBorderEmphasis),
        ),
        child: Row(
          children: [
            Expanded(
              child: Text(
                _encounterClass == 'IMP'
                    ? (_lastListKind == 'cirugias'
                        ? 'Agenda quirúrgica'
                        : 'Pacientes internados')
                    : _encounterClass == 'EMER'
                        ? 'Tablero de guardia'
                        : _encounterClass == 'VR'
                            ? _tituloConsultasAsync
                            : _formatearFechaAmigable(_fechaSeleccionada),
                style: BioTypography.h3,
              ),
            ),
            if (puedeFiltrarFecha) ...[
              BioButton(
                label: 'Anterior',
                icon: Icons.chevron_left,
                intent: UiIntent.neutral,
                variant: BioButtonVariant.outline,
                size: BioButtonSize.sm,
                onPressed: () => _cambiarFecha(-1),
              ),
              BioSpacing.gapW(BioSpacing.xs),
              BioButton(
                label: 'Hoy',
                intent: UiIntent.neutral,
                variant: BioButtonVariant.outline,
                size: BioButtonSize.sm,
                onPressed: _irAHoy,
              ),
              BioSpacing.gapW(BioSpacing.xs),
              BioButton(
                label: 'Siguiente',
                iconRight: Icons.chevron_right,
                intent: UiIntent.neutral,
                variant: BioButtonVariant.outline,
                size: BioButtonSize.sm,
                onPressed: () => _cambiarFecha(1),
              ),
              if (_encounterClass == 'IMP') ...[
                BioSpacing.gapW(BioSpacing.xs),
                IconButton(
                  icon: const Icon(Icons.refresh),
                  onPressed: _isLoading ? null : _cargarListadoPacientes,
                ),
              ],
            ] else if (_encounterClass == 'EMER') ...[
              if (_puedeIngresar) ...[
                BioButton(
                  label: 'Ingresar',
                  icon: Icons.person_add_alt_1,
                  intent: UiIntent.primary,
                  size: BioButtonSize.sm,
                  onPressed: () {
                    EmergencyGuardiaActions.openIngreso(
                      context: context,
                      api: _emergencyApi,
                      onChanged: () {
                        _cargarListadoPacientes(silent: true);
                      },
                    );
                  },
                ),
                BioSpacing.gapW(BioSpacing.xs),
              ],
              IconButton(
                icon: const Icon(Icons.refresh),
                onPressed: _isLoading ? null : _cargarListadoPacientes,
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildError(BuildContext context) {
    return Center(
      child: Padding(
        padding: BioSpacing.pageAll,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            BioAlert.danger(message: _errorMessage),
            BioSpacing.gapH(BioSpacing.lg),
            BioButton.primary(
              label: 'Reintentar',
              icon: Icons.refresh,
              onPressed: _cargarListadoPacientes,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmpty({required IconData icon, required String text}) {
    final tokens = context.bio;
    return Center(
      child: Padding(
        padding: BioSpacing.pageAll,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 48, color: tokens.textMuted),
            BioSpacing.gapH(BioSpacing.md),
            Text(
              text,
              style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAmbHomeContent(Turno? siguienteTurno) {
    if (_turnos.isEmpty) {
      return _buildEmpty(
        icon: Icons.event_busy_outlined,
        text: 'No hay turnos programados para esta fecha.',
      );
    }

    return _buildTurnosPorEstado(siguienteTurno);
  }

  Widget _buildVrHomeContent() {
    final groups = _resolvedAsyncStaffGroups();
    if (groups.isEmpty) {
      return _buildEmpty(
        icon: Icons.chat_bubble_outline,
        text: 'No hay consultas clínicas por mensaje pendientes.',
      );
    }

    return ListView(
      padding: const EdgeInsets.symmetric(
        horizontal: BioSpacing.lg,
        vertical: BioSpacing.lg,
      ),
      children: [
        if (_consultasAsyncSlaIncumplidos > 0) ...[
          Align(
            alignment: Alignment.centerRight,
            child: BioBadge.danger('$_consultasAsyncSlaIncumplidos plazo vencido'),
          ),
          BioSpacing.gapH(BioSpacing.sm),
        ],
        ...groups.expand(_buildAsyncGroupSection),
      ],
    );
  }

  /// Misma organización que web: orden del API (`groups`) o fallback Las mías → Por tomar.
  List<Map<String, dynamic>> _resolvedAsyncStaffGroups() {
    if (_consultasAsyncGroups.isNotEmpty) {
      return _consultasAsyncGroups;
    }
    if (_consultasAsync.isEmpty) {
      return const [];
    }
    final mias = <Map<String, dynamic>>[];
    final porTomar = <Map<String, dynamic>>[];
    for (final item in _consultasAsync) {
      final asignacion = item['asignacion'] is Map
          ? Map<String, dynamic>.from(item['asignacion'] as Map)
          : <String, dynamic>{};
      final acciones = item['acciones'] is Map
          ? Map<String, dynamic>.from(item['acciones'] as Map)
          : <String, dynamic>{};
      final esMio = asignacion['es_mio'] == true;
      final puedeTomar = acciones['tomar'] == true;
      final status = item['status']?.toString() ?? '';
      const abiertos = {'planned', 'in-progress', 'on-hold'};
      if (!abiertos.contains(status)) {
        continue;
      }
      if (esMio) {
        mias.add(item);
      } else if (puedeTomar || status == 'planned') {
        porTomar.add(item);
      }
    }
    return [
      {
        'id': 'mias',
        'title': 'Las mías',
        'empty_message': 'No tenés consultas tomadas en curso.',
        'items': mias,
      },
      {
        'id': 'por_tomar',
        'title': 'Por tomar',
        'empty_message': 'No hay solicitudes pendientes de tomar.',
        'items': porTomar,
      },
    ];
  }

  List<Widget> _buildAsyncGroupSection(Map<String, dynamic> group) {
    final title = group['title']?.toString().trim() ?? '';
    final emptyMessage = group['empty_message']?.toString().trim() ??
        'Sin solicitudes en esta sección.';
    final rawItems = group['items'] as List<dynamic>? ?? [];
    final items = rawItems
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();

    return [
      if (title.isNotEmpty) ...[
        _seccionSubtitulo(title),
        BioSpacing.gapH(BioSpacing.sm),
      ],
      if (items.isEmpty)
        _emptyInline(emptyMessage)
      else
        ...items.map(
          (item) => Padding(
            padding: const EdgeInsets.only(bottom: BioSpacing.md),
            child: _buildAsyncSolicitudCard(item),
          ),
        ),
      BioSpacing.gapH(BioSpacing.lg),
    ];
  }

  Widget _buildTurnosPorEstado(Turno? siguienteTurno) {
    final pendientes = _getPendientes(siguienteTurno);
    final cargadas = _getConsultasCargadas();
    final pendientesPresencial =
        pendientes.where((t) => !t.esTeleconsulta).toList();
    final pendientesVideo =
        pendientes.where((t) => t.esTeleconsulta).toList();
    const maxCardWidth = 420.0;

    return ListView(
      padding: const EdgeInsets.symmetric(
        horizontal: BioSpacing.lg,
        vertical: BioSpacing.lg,
      ),
      children: [
        if (siguienteTurno != null) ...[
          _seccionSubtitulo('Siguiente turno'),
          BioSpacing.gapH(BioSpacing.sm),
          Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: maxCardWidth),
              child: _buildSiguienteTurnoCard(siguienteTurno),
            ),
          ),
          BioSpacing.gapH(BioSpacing.xl),
        ],
        if (pendientes.isEmpty)
          _emptyInline('No hay turnos pendientes.')
        else ...[
          if (pendientesVideo.isNotEmpty) ...[
            _seccionSubtitulo('Videollamada (${pendientesVideo.length})'),
            BioSpacing.gapH(BioSpacing.sm),
            ...pendientesVideo.map((t) => _turnoCardPad(t, maxCardWidth)),
            BioSpacing.gapH(BioSpacing.lg),
          ],
          if (pendientesPresencial.isNotEmpty) ...[
            _seccionSubtitulo(
              pendientesVideo.isEmpty
                  ? 'Pendientes'
                  : 'Presencial (${pendientesPresencial.length})',
            ),
            BioSpacing.gapH(BioSpacing.sm),
            ...pendientesPresencial.map((t) => _turnoCardPad(t, maxCardWidth)),
          ],
        ],
        BioSpacing.gapH(BioSpacing.xl),
        _seccionSubtitulo('Consultas cargadas'),
        BioSpacing.gapH(BioSpacing.sm),
        if (cargadas.isEmpty)
          _emptyInline('No hay consultas cargadas.')
        else
          ...cargadas.map(
            (t) => _turnoCardPad(t, maxCardWidth, resumen: true),
          ),
      ],
    );
  }

  Widget _turnoCardPad(
    Turno t,
    double maxCardWidth, {
    bool resumen = false,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: BioSpacing.md),
      child: Center(
        child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: maxCardWidth),
          child: _buildTurnoCard(t, resumenConsultaCargada: resumen),
        ),
      ),
    );
  }

  Widget _buildAsyncSolicitudCard(Map<String, dynamic> item) {
    final paciente = item['paciente'] is Map
        ? Map<String, dynamic>.from(item['paciente'] as Map)
        : <String, dynamic>{};
    final nombrePaciente =
        paciente['nombre_completo']?.toString().trim() ?? 'Paciente';
    final servicio = item['servicio']?.toString().trim() ?? '';
    final solicitudTipo = item['solicitud_tipo']?.toString().trim() ?? '';
    final preview = item['reason_preview']?.toString().trim() ?? '';
    final createdAt = _formatAsyncCreatedAt(item['created_at']?.toString());
    final status = item['status']?.toString() ?? '';
    final statusLabel =
        item['status_label']?.toString().trim() ?? status;
    final acciones = item['acciones'] is Map
        ? Map<String, dynamic>.from(item['acciones'] as Map)
        : <String, dynamic>{};
    final puedeTomar = acciones['tomar'] == true;
    final abrirChat = acciones['abrir_chat'] == true;
    final encounterRaw = item['encounter_id'];
    final encounterId = encounterRaw is int
        ? encounterRaw
        : int.tryParse(encounterRaw?.toString() ?? '') ?? 0;
    final tomando = _tomandoAsyncIds.contains(encounterId);

    final prioridad = item['prioridad'] is Map
        ? Map<String, dynamic>.from(item['prioridad'] as Map)
        : null;
    final prioridadLabel = prioridad?['label']?.toString().trim() ?? '';
    final prioridadIntent = prioridad?['intent']?.toString().trim() ?? '';
    final prioridadUiIntent = _uiIntentFromApi(prioridadIntent);

    final sla = item['sla'] is Map
        ? Map<String, dynamic>.from(item['sla'] as Map)
        : null;
    final slaIncumplido = sla?['incumplido'] == true;
    final slaHoras = sla?['horas_objetivo'];

    UiIntent statusIntent = UiIntent.neutral;
    if (status == 'planned') statusIntent = UiIntent.warning;
    if (status == 'in-progress') statusIntent = UiIntent.success;

    return BioCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (solicitudTipo.isNotEmpty)
                Flexible(child: BioBadge.info(solicitudTipo))
              else
                const SizedBox.shrink(),
              if (statusLabel.isNotEmpty)
                BioBadge(label: statusLabel, intent: statusIntent),
            ],
          ),
          BioSpacing.gapH(BioSpacing.xs),
          Text(nombrePaciente, style: BioTypography.title),
          if (servicio.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(servicio, style: BioTypography.bodySm),
          ],
          if (createdAt.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(createdAt, style: BioTypography.caption),
          ],
          if (preview.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              preview,
              style: BioTypography.bodySm,
              maxLines: 4,
              overflow: TextOverflow.ellipsis,
            ),
          ],
          if (slaIncumplido) ...[
            BioSpacing.gapH(BioSpacing.xs),
            BioBadge.danger('Plazo vencido${slaHoras != null ? ' ($slaHoras h)' : ''}'),
          ],
          if (prioridadLabel.isNotEmpty || puedeTomar || abrirChat) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                if (prioridadLabel.isNotEmpty)
                  BioBadge(label: prioridadLabel, intent: prioridadUiIntent)
                else
                  const SizedBox.shrink(),
                const Spacer(),
                Wrap(
                  spacing: BioSpacing.sm,
                  runSpacing: BioSpacing.xs,
                  alignment: WrapAlignment.end,
                  children: [
                    if (puedeTomar)
                      BioButton.primary(
                        label: 'Tomar y responder',
                        size: BioButtonSize.sm,
                        icon: Icons.play_arrow_outlined,
                        loading: tomando,
                        onPressed: tomando || encounterId <= 0
                            ? null
                            : () => _tomarAsyncCaso(item),
                      ),
                    if (abrirChat)
                      BioButton.outlinePrimary(
                        label: 'Ver conversación',
                        size: BioButtonSize.sm,
                        icon: Icons.chat_bubble_outline,
                        onPressed: encounterId <= 0
                            ? null
                            : () => _abrirChatAsync(item),
                      ),
                  ],
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  UiIntent _uiIntentFromApi(String raw) {
    switch (raw.trim().toLowerCase()) {
      case 'danger':
        return UiIntent.danger;
      case 'warning':
        return UiIntent.warning;
      case 'success':
        return UiIntent.success;
      case 'info':
        return UiIntent.info;
      case 'primary':
        return UiIntent.primary;
      case 'secondary':
        return UiIntent.secondary;
      default:
        return UiIntent.neutral;
    }
  }

  String _formatAsyncCreatedAt(String? raw) {
    final s = raw?.trim() ?? '';
    if (s.isEmpty) return '';
    final dt = DateTime.tryParse(s);
    if (dt == null) return s;
    return DateFormat('dd/MM/yyyy HH:mm', 'es').format(dt.toLocal());
  }

  Future<void> _tomarAsyncCaso(Map<String, dynamic> item) async {
    final encounterRaw = item['encounter_id'];
    final encounterId = encounterRaw is int
        ? encounterRaw
        : int.tryParse(encounterRaw?.toString() ?? '');
    if (encounterId == null || encounterId <= 0) return;

    setState(() => _tomandoAsyncIds.add(encounterId));
    try {
      final res = await _consultaAsyncApi.tomarComoStaff(encounterId);
      if (res['success'] != true) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(res['message']?.toString() ?? 'No se pudo tomar la solicitud.')),
        );
        return;
      }
      await _cargarListadoPacientes(silent: true);
      if (!mounted) return;
      _abrirChatAsync(item);
    } finally {
      if (mounted) {
        setState(() => _tomandoAsyncIds.remove(encounterId));
      }
    }
  }

  void _abrirChatAsync(Map<String, dynamic> item) async {
    final encounterRaw = item['encounter_id'];
    final encounterId = encounterRaw is int
        ? encounterRaw
        : int.tryParse(encounterRaw?.toString() ?? '');
    if (encounterId == null || encounterId <= 0) return;

    final paciente = item['paciente'] is Map
        ? Map<String, dynamic>.from(item['paciente'] as Map)
        : <String, dynamic>{};
    final nombrePaciente =
        paciente['nombre_completo']?.toString().trim() ?? 'Paciente';
    final servicio = item['servicio']?.toString().trim() ?? '';
    final titulo = servicio.isNotEmpty ? '$nombrePaciente · $servicio' : nombrePaciente;

    await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => ChatConsultaScreen(
          consultaId: encounterId,
          authToken: _homePanelApi.authToken ?? widget.authToken,
          userId: widget.userId,
          userName: widget.userName,
          titulo: titulo,
        ),
      ),
    );
    if (!mounted) return;
    // Refresco al volver: las resueltas no deben quedar en «Las mías».
    await _cargarListadoPacientes(silent: true);
  }

  Widget _seccionSubtitulo(String texto) {
    return Text(
      texto,
      style: BioTypography.h3.copyWith(
        color: IntentPalette.of(UiIntent.primary).base,
        fontWeight: FontWeight.w700,
      ),
    );
  }

  Widget _emptyInline(String text) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: BioSpacing.md),
        child: Text(
          text,
          style: BioTypography.bodySm.copyWith(color: context.bio.textMuted),
        ),
      ),
    );
  }

  Widget _buildCirugiasList() {
    if (_cirugias.isEmpty) {
      return _buildEmpty(
        icon: Icons.medical_information_outlined,
        text: 'No hay cirugías agendadas para esta fecha.',
      );
    }
    return ListView.separated(
      padding: BioSpacing.pageAll,
      itemCount: _cirugias.length,
      separatorBuilder: (_, __) => BioSpacing.gapH(BioSpacing.sm),
      itemBuilder: (context, index) {
        final c = _cirugias[index];
        return _buildSimpleTile(
          icon: Icons.local_hospital_outlined,
          title: c.nombrePaciente,
          subtitle: [
            if (c.salaNombre.isNotEmpty) 'Sala ${c.salaNombre}',
            if (c.fechaHoraInicio != null) c.fechaHoraInicio!,
            c.estadoLabel,
          ].where((e) => e.isNotEmpty).join(' · '),
          onTap: () => _verHistoriaClinicaCirugia(c),
        );
      },
    );
  }

  Widget _buildInternadosList() {
    if (!_sessionTieneHorario) {
      final msg = (_mensajeSinHorario != null &&
              !_mensajeSinHorario!.toLowerCase().contains('guardia'))
          ? _mensajeSinHorario!
          : _msgSinHorarioPisoFallback;
      return _buildEmpty(
        icon: Icons.badge_outlined,
        text: msg,
      );
    }
    if (_internados.isEmpty) {
      return _buildEmpty(
        icon: Icons.bed_outlined,
        text: 'No hay pacientes internados.',
      );
    }

    final entries = _internadosOrden == 'nombre'
        ? _internadosEntriesPorNombre(_internados)
        : _internadosEntriesPorRecorrido(_internados);

    return RefreshIndicator(
      onRefresh: () => _cargarListadoPacientes(silent: true),
      child: ListView(
        padding: BioSpacing.pageAll,
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          Row(
            children: [
              BioChip(
                label: 'Por recorrido',
                selected: _internadosOrden == 'recorrido',
                onTap: () => setState(() => _internadosOrden = 'recorrido'),
              ),
              BioSpacing.gapW(BioSpacing.sm),
              BioChip(
                label: 'Por paciente',
                selected: _internadosOrden == 'nombre',
                onTap: () => setState(() => _internadosOrden = 'nombre'),
              ),
            ],
          ),
          BioSpacing.gapH(BioSpacing.md),
          for (var i = 0; i < entries.length; i++) ...[
            entries[i],
            if (i < entries.length - 1) BioSpacing.gapH(BioSpacing.sm),
          ],
        ],
      ),
    );
  }

  List<Widget> _internadosEntriesPorNombre(List<InternadoItem> items) {
    final sorted = List<InternadoItem>.from(items)
      ..sort((a, b) => a.nombreCompleto.toLowerCase().compareTo(b.nombreCompleto.toLowerCase()));
    return [
      for (final i in sorted) _buildInternadoCard(i, showUbicacion: true),
    ];
  }

  List<Widget> _internadosEntriesPorRecorrido(List<InternadoItem> items) {
    final byPiso = <String, List<InternadoItem>>{};
    final pisoOrder = <String>[];
    final pisoLabel = <String, String>{};
    final pisoNro = <String, int>{};

    String pisoKeyOf(InternadoItem i) {
      final label = (i.piso ?? '').trim().toLowerCase();
      if (label.isNotEmpty) return 'l:$label';
      if (i.idPiso != null) return 'i:${i.idPiso}';
      return 'x';
    }

    String salaKeyOf(InternadoItem i) {
      final label = (i.sala ?? '').trim().toLowerCase();
      if (label.isNotEmpty) return 'l:$label';
      if (i.idSala != null) return 'i:${i.idSala}';
      return 'x';
    }

    for (final i in items) {
      final key = pisoKeyOf(i);
      if (!byPiso.containsKey(key)) {
        byPiso[key] = [];
        pisoOrder.add(key);
        pisoLabel[key] = (i.piso?.trim().isNotEmpty == true) ? i.piso! : 'Piso';
        pisoNro[key] = i.nroPiso;
      }
      byPiso[key]!.add(i);
    }
    pisoOrder.sort((a, b) => (pisoNro[a] ?? 0).compareTo(pisoNro[b] ?? 0));

    final out = <Widget>[];
    for (final pisoKey in pisoOrder) {
      final pisoItems = byPiso[pisoKey]!;

      final bySala = <String, List<InternadoItem>>{};
      final salaOrder = <String>[];
      final salaLabel = <String, String>{};
      final salaNro = <String, int>{};
      for (final i in pisoItems) {
        final sKey = salaKeyOf(i);
        if (!bySala.containsKey(sKey)) {
          bySala[sKey] = [];
          salaOrder.add(sKey);
          salaLabel[sKey] = (i.sala?.trim().isNotEmpty == true) ? i.sala! : 'Sala';
          salaNro[sKey] = i.nroSala;
        }
        bySala[sKey]!.add(i);
      }
      salaOrder.sort((a, b) => (salaNro[a] ?? 0).compareTo(salaNro[b] ?? 0));

      out.add(_internadosSectionHeader(pisoLabel[pisoKey]!, intent: UiIntent.primary));

      for (final salaKey in salaOrder) {
        final salaItems = bySala[salaKey]!
          ..sort((a, b) {
            final byCama = a.nroCama.compareTo(b.nroCama);
            if (byCama != 0) return byCama;
            return a.nombreCompleto.toLowerCase().compareTo(b.nombreCompleto.toLowerCase());
          });
        out.add(_internadosSectionHeader(salaLabel[salaKey]!, intent: UiIntent.success));
        for (final i in salaItems) {
          out.add(_buildInternadoCard(i, showUbicacion: false));
        }
      }
    }
    return out;
  }

  Widget _internadosSectionHeader(String label, {required UiIntent intent}) {
    final palette = IntentPalette.of(intent);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(
        horizontal: BioSpacing.sm,
        vertical: BioSpacing.xs,
      ),
      decoration: BoxDecoration(
        color: palette.softBg,
        borderRadius: BioRadius.all(BioRadius.sm),
      ),
      child: Text(
        label,
        style: BioTypography.bodySm.copyWith(
          color: palette.base,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  Widget _buildInternadoCard(InternadoItem i, {required bool showUbicacion}) {
    final tokens = context.bio;
    final meta = <String>[
      if (showUbicacion && (i.piso?.isNotEmpty ?? false)) i.piso!,
      if (showUbicacion && (i.sala?.isNotEmpty ?? false)) i.sala!,
      if (i.documento?.isNotEmpty ?? false) i.documento!,
    ].join(' · ');

    void openAtender() => _verHistoriaClinica(
          i.idPersona,
          parent: 'INTERNACION',
          parentId: i.id,
        );

    return BioCard(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color: tokens.paperBackground,
              borderRadius: BioRadius.all(BioRadius.sm),
              border: BioBorder.all(BorderWidth.thin, tokens.paperBorderDefault),
            ),
            child: Text(
              i.cama != null && i.cama!.isNotEmpty ? 'Cama ${i.cama}' : 'Cama',
              style: BioTypography.caption.copyWith(fontWeight: FontWeight.w600),
            ),
          ),
          BioSpacing.gapW(BioSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(i.nombreCompleto, style: BioTypography.title),
                if (meta.isNotEmpty) ...[
                  BioSpacing.gapH(2),
                  Text(
                    meta,
                    style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
                  ),
                ],
                BioSpacing.gapH(BioSpacing.sm),
                Wrap(
                  spacing: BioSpacing.sm,
                  runSpacing: BioSpacing.sm,
                  children: [
                    BioButton.primary(
                      label: 'Atender',
                      size: BioButtonSize.sm,
                      onPressed: openAtender,
                    ),
                    BioButton(
                      label: 'Cambio cama',
                      intent: UiIntent.info,
                      variant: BioButtonVariant.outline,
                      size: BioButtonSize.sm,
                      onPressed: () => _openInternacionUiForm(
                        i,
                        path: '/clinical/internacion/${i.id}/cambio-cama-formulario',
                        title: 'Cambio de cama',
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _openInternacionUiForm(
    InternadoItem i, {
    required String path,
    required String title,
  }) async {
    final token = _homePanelApi.authToken ?? widget.authToken;
    if (token == null || token.isEmpty) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Sesión no disponible para esta acción.')),
      );
      return;
    }
    final uri = Uri.parse(resolveApiAbsoluteUrl(path));
    if (!mounted) return;
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => UiJsonScreen(
          apiAbsoluteUrl: uri.toString(),
          authToken: token,
          appClient: 'bioenlace-personalsalud',
          title: title,
          onSubmitSuccess: (_) async {
            await _cargarListadoPacientes(silent: true);
          },
        ),
      ),
    );
    if (mounted) {
      await _cargarListadoPacientes(silent: true);
    }
  }

  Color? _colorFromHex(String? hex) {
    if (hex == null || hex.isEmpty) return null;
    var h = hex.replaceFirst('#', '');
    if (h.length == 6) h = 'FF$h';
    try {
      return Color(int.parse(h, radix: 16));
    } catch (_) {
      return null;
    }
  }

  Future<void> _onGuardiaTap(EmergencyBoardItem g) async {
    if (g.puedeVerConsulta) {
      await _verHistoriaClinica(
        g.idPersona,
        parent: 'ENCOUNTER',
        parentId: g.encounterId,
        resumenConsultaCargada: true,
      );
      return;
    }
    if (g.needsTriage) {
      if (_puedeTriage) {
        await EmergencyGuardiaActions.openTriage(
          context: context,
          item: g,
          api: _emergencyApi,
          onChanged: () {
            _cargarListadoPacientes(silent: true);
          },
        );
        return;
      }
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Pendiente de triage. Lo registra admisión o enfermería.',
            ),
          ),
        );
      }
      return;
    }
    if (!_sessionTieneHorario) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              _mensajeSinHorario ?? _msgSinHorarioGuardiaFallback,
            ),
          ),
        );
      }
      return;
    }
    if (_puedeAtender) {
      try {
        final estado = g.circuitoEstado ?? '';
        if (estado != 'en_atencion') {
          await _emergencyApi.iniciarAtencion(g.id);
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('No se pudo iniciar atención: $e')),
          );
        }
        return;
      }
      _verHistoriaClinica(
        g.idPersona,
        parent: 'GUARDIA',
        parentId: g.id,
      );
      return;
    }
    if (_puedeDocumentar) {
      _verHistoriaClinica(
        g.idPersona,
        parent: 'GUARDIA',
        parentId: g.id,
      );
      return;
    }
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('El médico atiende el caso. Enfermería registra triage o una nota.'),
        ),
      );
    }
  }

  Widget _buildGuardiaTableroList() {
    if (!_sessionTieneHorario) {
      final msg = _mensajeSinHorario ?? _msgSinHorarioGuardiaFallback;
      return _buildEmpty(
        icon: Icons.badge_outlined,
        text: msg,
      );
    }
    if (_guardiaTablero.isEmpty) {
      return _buildEmpty(
        icon: Icons.emergency_outlined,
        text: 'No hay pacientes en el tablero de guardia.',
      );
    }
    return RefreshIndicator(
      onRefresh: () => _cargarListadoPacientes(silent: true),
      child: ListView.separated(
        padding: BioSpacing.pageAll,
        physics: const AlwaysScrollableScrollPhysics(),
        itemCount: _guardiaTablero.length,
        separatorBuilder: (_, __) => BioSpacing.gapH(BioSpacing.sm),
        itemBuilder: (context, index) {
          final g = _guardiaTablero[index];
          return _buildGuardiaTableroCard(g);
        },
      ),
    );
  }

  Widget _buildGuardiaTableroCard(EmergencyBoardItem g) {
    final estadoIntent = _guardiaCircuitoIntent(g);
    final cerrado = EmergencyGuardiaActions.episodioCerrado(g);
    void refresh() => _cargarListadoPacientes(silent: true);

    return BioCard(
      onTap: () => _onGuardiaTap(g),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(g.nombreCompleto, style: BioTypography.title),
                    if (g.triageReasonText != null &&
                        g.triageReasonText!.isNotEmpty) ...[
                      BioSpacing.gapH(BioSpacing.xs),
                      Text(g.triageReasonText!, style: BioTypography.bodySm),
                    ],
                    BioSpacing.gapH(BioSpacing.sm),
                    Wrap(
                      spacing: BioSpacing.xs,
                      runSpacing: BioSpacing.xs,
                      children: [
                        if (g.slaViolado)
                          const BioBadge(
                            label: 'Plazo médico',
                            intent: UiIntent.danger,
                          ),
                        if (g.internacionPendiente)
                          const BioBadge(
                            label: 'Cama pend.',
                            intent: UiIntent.info,
                          ),
                        if (g.identidadPendiente)
                          const BioBadge(
                            label: 'Identidad pendiente',
                            intent: UiIntent.warning,
                          ),
                        if (g.ordersLabPending > 0)
                          BioBadge(
                            label: '${g.ordersLabPending} lab pend.',
                            intent: UiIntent.warning,
                          ),
                        BioBadge(
                          label: g.circuitoEstadoLabel ??
                              g.circuitoEstado ??
                              '—',
                          intent: estadoIntent,
                        ),
                        Text(
                          '${formatDuracionMinutos(g.minutosEspera)} en espera',
                          style: BioTypography.caption,
                        ),
                        if (g.profesionalAsignado != null &&
                            g.profesionalAsignado!.isNotEmpty)
                          Text(
                            g.profesionalAsignado!,
                            style: BioTypography.caption,
                          ),
                      ],
                    ),
                  ],
                ),
              ),
              Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (!cerrado)
                    IconButton(
                      icon: const Icon(Icons.more_vert),
                      tooltip: 'Más acciones',
                      onPressed: () {
                        EmergencyGuardiaActions.showActionSheet(
                          context: context,
                          item: g,
                          api: _emergencyApi,
                          onChanged: refresh,
                          sessionTieneHorario: _sessionTieneHorario,
                          puedeAtender: _puedeAtender,
                          puedeTriage: _puedeTriage,
                          puedeIngresar: _puedeIngresar,
                        );
                      },
                    ),
                  Icon(
                    g.puedeVerConsulta
                        ? Icons.visibility_outlined
                        : (g.needsTriage
                            ? Icons.assignment_outlined
                            : Icons.chevron_right),
                    color: context.bio.textMuted,
                  ),
                ],
              ),
            ],
          ),
          if (_guardiaPrimaryActions(g, cerrado: cerrado).isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Wrap(
              spacing: BioSpacing.sm,
              runSpacing: BioSpacing.sm,
              children: _guardiaPrimaryActions(g, cerrado: cerrado),
            ),
          ],
        ],
      ),
    );
  }

  List<Widget> _guardiaPrimaryActions(
    EmergencyBoardItem g, {
    required bool cerrado,
  }) {
    if (cerrado) return const [];
    final actions = <Widget>[];
    if (g.needsTriage && _puedeTriage) {
      actions.add(
        BioButton.outlinePrimary(
          label: 'Triage',
          size: BioButtonSize.sm,
          onPressed: () => EmergencyGuardiaActions.openTriage(
            context: context,
            item: g,
            api: _emergencyApi,
            onChanged: () => _cargarListadoPacientes(silent: true),
          ),
        ),
      );
    }
    if (_puedeAtender && !g.needsTriage) {
      actions.add(
        BioButton.primary(
          label: 'Atender',
          size: BioButtonSize.sm,
          onPressed: () => _onGuardiaTap(g),
        ),
      );
    }
    if (_puedeDocumentar && !_puedeAtender && !g.needsTriage) {
      actions.add(
        BioButton.outlinePrimary(
          label: 'Nota',
          size: BioButtonSize.sm,
          onPressed: () => _onGuardiaTap(g),
        ),
      );
    }
    if (!_puedeAtender &&
        EmergencyGuardiaActions.puedeRegistrarPacienteSeRetiro(
          item: g,
          puedeAtender: _puedeAtender,
          puedeTriage: _puedeTriage,
          puedeIngresar: _puedeIngresar,
        )) {
      actions.add(
        BioButton.outlinePrimary(
          label: 'Se retiró',
          size: BioButtonSize.sm,
          onPressed: () => EmergencyGuardiaActions.openPacienteSeRetiro(
            context,
            g,
            _emergencyApi,
            () => _cargarListadoPacientes(silent: true),
          ),
        ),
      );
    }
    return actions;
  }

  UiIntent _guardiaCircuitoIntent(EmergencyBoardItem g) {
    if (g.needsTriage) {
      switch (g.triageEsperaNivel) {
        case 'rojo':
          return UiIntent.danger;
        case 'naranja':
          return UiIntent.warning;
        default:
          return UiIntent.neutral;
      }
    }
    if (g.circuitoEstado == 'en_atencion') {
      return UiIntent.info;
    }
    if (g.circuitoEstado == 'atendido') {
      return UiIntent.success;
    }
    return UiIntent.neutral;
  }

  Widget _buildSimpleTile({
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    final primary = IntentPalette.of(UiIntent.primary).base;
    return BioCard(
      onTap: onTap,
      child: Row(
        children: [
          Icon(icon, color: primary, size: 22),
          BioSpacing.gapW(BioSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: BioTypography.title),
                if (subtitle.isNotEmpty) ...[
                  BioSpacing.gapH(2),
                  Text(subtitle, style: BioTypography.bodySm),
                ],
              ],
            ),
          ),
          Icon(Icons.chevron_right, color: context.bio.textMuted),
        ],
      ),
    );
  }

  Widget _buildSiguienteTurnoCard(Turno turno) {
    final primary = IntentPalette.of(UiIntent.primary).base;
    void openTimeline() => _verHistoriaClinica(
          turno.idPersona,
          parent: 'TURNO',
          parentId: turno.id,
        );
    return BioCard.intent(
      intent: UiIntent.primary,
      onTap: openTimeline,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.person_outline, color: primary, size: 22),
              BioSpacing.gapW(BioSpacing.sm),
              Expanded(
                child: Text(
                  turno.paciente?.nombreCompleto ?? 'Sin paciente',
                  style: BioTypography.h3,
                ),
              ),
            ],
          ),
          BioSpacing.gapH(BioSpacing.md),
          _filaInfo(
            Icons.access_time,
            'Hora: ${turno.hora}',
            textStyle: BioTypography.body,
          ),
          BioSpacing.gapH(BioSpacing.xs),
          _filaInfo(
            Icons.videocam_outlined,
            'Modalidad: ${turno.modalidadLabel}',
          ),
          if (turno.servicio != null && turno.servicio!.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            _filaInfo(
              Icons.local_hospital_outlined,
              'Servicio: ${turno.servicio}',
            ),
          ],
          if (turno.modalidadInsight != null) ...[
            BioSpacing.gapH(BioSpacing.sm),
            _buildModalidadInsightBox(turno.modalidadInsight!),
          ],
          BioSpacing.gapH(BioSpacing.md),
          Align(
            alignment: Alignment.centerRight,
            child: BioButton.outlinePrimary(
              label: 'Historia clínica',
              icon: Icons.medical_services_outlined,
              size: BioButtonSize.sm,
              onPressed: openTimeline,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTurnoCard(Turno turno, {bool resumenConsultaCargada = false}) {
    final primary = IntentPalette.of(UiIntent.primary).base;
    final estadoIntent = _intentEstado(turno.estado);
    void openTimeline() => _verHistoriaClinica(
          turno.idPersona,
          parent: 'TURNO',
          parentId: turno.id,
          resumenConsultaCargada: resumenConsultaCargada,
        );
    return BioCard(
      onTap: openTimeline,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.person_outline, color: primary, size: 22),
              BioSpacing.gapW(BioSpacing.sm),
              Expanded(
                child: Text(
                  turno.paciente?.nombreCompleto ?? 'Sin paciente',
                  style: BioTypography.h3,
                ),
              ),
              BioBadge(label: turno.estadoLabel, intent: estadoIntent),
            ],
          ),
          BioSpacing.gapH(BioSpacing.sm),
          Wrap(
            spacing: BioSpacing.xs,
            runSpacing: BioSpacing.xs,
            children: [
              BioBadge(
                label: turno.modalidadLabel,
                intent: turno.esTeleconsulta ? UiIntent.info : UiIntent.neutral,
              ),
            ],
          ),
          BioSpacing.gapH(BioSpacing.md),
          _filaInfo(Icons.access_time, 'Hora: ${turno.hora}'),
          BioSpacing.gapH(BioSpacing.xs),
          _filaInfo(
            Icons.local_hospital_outlined,
            'Servicio: ${turno.servicio ?? "Sin servicio"}',
          ),
          if (turno.observaciones != null && turno.observaciones!.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            _filaInfo(
              Icons.note_outlined,
              'Observaciones: ${turno.observaciones}',
              small: true,
            ),
          ],
          if (turno.modalidadInsight != null) ...[
            BioSpacing.gapH(BioSpacing.sm),
            _buildModalidadInsightBox(turno.modalidadInsight!),
          ],
          BioSpacing.gapH(BioSpacing.md),
          Align(
            alignment: Alignment.centerRight,
            child: BioButton.outlinePrimary(
              label: resumenConsultaCargada ? 'Ver consulta' : 'Historia clínica',
              icon: Icons.medical_services_outlined,
              size: BioButtonSize.sm,
              onPressed: openTimeline,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildModalidadInsightBox(Map<String, dynamic> insight) {
    final tokens = context.bio;
    final summary = insight['summary']?.toString().trim() ?? '';
    if (summary.isEmpty) return const SizedBox.shrink();
    final tone = insight['tone']?.toString() ?? 'info';
    final intent = tone == 'secondary' ? UiIntent.neutral : UiIntent.info;
    final palette = IntentPalette.of(intent);
    final modalidades = insight['modalidades'];
    final footer = insight['footer']?.toString().trim() ?? '';
    final agendaCfg = insight['agenda_config'] is Map
        ? Map<String, dynamic>.from(insight['agenda_config'] as Map)
        : null;
    final linkLabel = agendaCfg?['link_label']?.toString().trim() ?? '';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(BioSpacing.sm),
      decoration: BoxDecoration(
        color: palette.base.withValues(alpha: 0.08),
        borderRadius: BioRadius.all(BioRadius.sm),
        border: Border.all(color: palette.base.withValues(alpha: 0.35)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            summary,
            style: BioTypography.bodySm.copyWith(color: tokens.textBody),
          ),
          if (modalidades is List && modalidades.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            ...modalidades.whereType<Map>().map((m) {
              final label = m['label']?.toString() ?? m['code']?.toString() ?? '';
              final desc = m['description']?.toString().trim() ?? '';
              return Padding(
                padding: const EdgeInsets.only(top: 2),
                child: Text(
                  desc.isEmpty ? '· $label' : '· $label: $desc',
                  style: BioTypography.caption.copyWith(color: tokens.textMuted),
                ),
              );
            }),
          ],
          if (footer.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              footer,
              style: BioTypography.caption.copyWith(color: tokens.textMuted),
            ),
          ],
          if (linkLabel.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              linkLabel,
              style: BioTypography.caption.copyWith(
                color: palette.base,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _filaInfo(IconData icon, String text, {bool small = false, TextStyle? textStyle}) {
    final tokens = context.bio;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 16, color: tokens.textMuted),
        BioSpacing.gapW(BioSpacing.xs),
        Expanded(
          child: Text(
            text,
            style: textStyle ??
                (small ? BioTypography.caption : BioTypography.bodySm),
          ),
        ),
      ],
    );
  }

  static void _onTapSinTimeline(BuildContext context) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Historia clínica no disponible temporalmente.'),
      ),
    );
  }

  Future<void> _verHistoriaClinica(
    int personaId, {
    String? parent,
    int? parentId,
    bool resumenConsultaCargada = false,
  }) async {
    final refreshed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (context) => PatientTimelineScreen(
          personaId: personaId,
          authToken: widget.authToken,
          soloVer: resumenConsultaCargada || parent == null,
          resumenConsultaCargada: resumenConsultaCargada,
          consultParent: parent,
          consultParentId: parentId,
        ),
      ),
    );
    if (refreshed == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Consulta guardada')),
      );
      await _cargarListadoPacientes(silent: true);
    }
  }

  Future<void> _verHistoriaClinicaCirugia(CirugiaAgendaItem c) async {
    final saved = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (context) => PatientTimelineScreen(
          personaId: c.idPersona,
          authToken: widget.authToken,
          soloVer: false,
          consultParent: 'CIRUGIA',
          consultParentId: c.id,
        ),
      ),
    );
    if (saved == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Consulta guardada')),
      );
      await _cargarListadoPacientes(silent: true);
    }
  }
}
