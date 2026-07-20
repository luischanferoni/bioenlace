// lib/screens/home_screen.dart
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
import 'emergency/emergency_triage_screen.dart';
import 'patient_timeline_screen.dart';
import 'internacion/internacion_mapa_screen.dart';
import 'chat_consulta_screen.dart';

/// Pantalla principal del médico. Contenido según encounter class:
/// AMB/VR/OBSENC/HH = turnos; IMP = internados/cirugías; EMER = tablero operativo de guardia.
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
  String _tituloConsultasAsync = 'Consultas clínicas por mensaje';
  int _consultasAsyncSlaIncumplidos = 0;
  final Set<int> _tomandoAsyncIds = {};
  List<InternadoItem> _internados = [];
  List<EmergencyBoardItem> _guardiaTablero = [];
  List<CirugiaAgendaItem> _cirugias = [];
  List<HomePanelKpiGroup> _kpiGroups = [];
  List<Map<String, dynamic>> _coberturaActiva = [];
  String? _coberturaTitle;
  bool _sessionTieneCobertura = false;
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
            ? 'staff_cobertura_activa,emergency_board,emergency_indicators'
            : null,
      );
      if (!mounted) return;
      _applyHomePanel(
        panel,
        partial: silent && _encounterClass == 'EMER',
      );
      if (_encounterClass == 'EMER') {
        _startTableroPoll();
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

  void _startTableroPoll() {
    _stopTableroPoll();
    if (_encounterClass != 'EMER') return;
    Future.delayed(const Duration(seconds: 30), () async {
      if (!mounted || _encounterClass != 'EMER') return;
      await _cargarListadoPacientes(silent: true);
      _startTableroPoll();
    });
  }

  void _stopTableroPoll() {
    // repoll encadenado; al cambiar encounter se corta por _encounterClass check
  }

  void _applyHomePanel(HomePanelResponse panel, {bool partial = false}) {
    setState(() {
      if (!partial) {
        _turnos = [];
        _internados = [];
        _guardiaTablero = [];
        _cirugias = [];
        _coberturaActiva = [];
        _coberturaTitle = null;
        _sessionTieneCobertura = false;
        _consultasAsync = [];
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

      final cobertura = panel.sectionByKind('staff_cobertura_activa');
      if (cobertura != null) {
        final items = cobertura.data['items'] as List<dynamic>? ?? [];
        _coberturaActiva = items
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList();
        _coberturaTitle = cobertura.data['title'] as String?;
        final session = cobertura.data['session'];
        _sessionTieneCobertura = session is Map && session['tiene_cobertura'] == true;
      } else if (!partial) {
        _coberturaActiva = [];
        _coberturaTitle = null;
        _sessionTieneCobertura = false;
      }

      final board = panel.sectionByKind('emergency_board');
      if (board != null) {
        final items = board.data['items'] as List<dynamic>? ?? [];
        _guardiaTablero = items
            .map((e) => EmergencyBoardItem.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList();
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
        final titulo = asyncSec.data['title']?.toString().trim();
        if (titulo != null && titulo.isNotEmpty) {
          _tituloConsultasAsync = titulo;
        }
        _consultasAsyncSlaIncumplidos =
            asyncSec.data['sla_incumplidos'] as int? ?? 0;
      } else if (!partial) {
        _consultasAsync = [];
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
    if (_kpiGroups.isEmpty && _staffContext == null && _coberturaActiva.isEmpty) {
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
        if (_coberturaActiva.isNotEmpty) ...[
          _buildCoberturaActivaStrip(),
          const SizedBox(height: BioSpacing.sm),
        ],
        Expanded(child: child),
      ],
    );
  }

  Widget _buildCoberturaActivaStrip() {
    final title = _coberturaTitle ?? 'Cobertura';
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: BioSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: BioTypography.caption),
          const SizedBox(height: BioSpacing.xs),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: _coberturaActiva.map((c) {
                final persona = c['persona'] is Map
                    ? Map<String, dynamic>.from(c['persona'] as Map)
                    : <String, dynamic>{};
                final nombre = (persona['nombre_completo'] as String?)?.trim();
                final rol = (c['rol'] as String?)?.trim();
                final inicio = (c['inicio'] as String?) ?? '';
                final fin = (c['fin'] as String?) ?? '';
                final horaIni = inicio.length >= 16 ? inicio.substring(11, 16) : inicio;
                final horaFin = fin.length >= 16 ? fin.substring(11, 16) : fin;
                final label = [
                  if (nombre != null && nombre.isNotEmpty) nombre,
                  if (rol != null && rol.isNotEmpty) rol,
                  if (horaIni.isNotEmpty || horaFin.isNotEmpty) '$horaIni–$horaFin',
                ].join(' · ');
                return Padding(
                  padding: const EdgeInsets.only(right: BioSpacing.xs),
                  child: Chip(
                    label: Text(label.isEmpty ? 'Cobertura' : label),
                    visualDensity: VisualDensity.compact,
                  ),
                );
              }).toList(),
            ),
          ),
        ],
      ),
    );
  }

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
        .where((t) =>
            (t.estado == 'ATENDIDO' || t.estado == 'EN_ATENCION') &&
            t.id != 999999)
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
                            : _wrapWithPanelKpis(_buildAmbHomeContent(siguienteTurno)),
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
                  icon: const Icon(Icons.bed_outlined),
                  tooltip: 'Mapa de camas',
                  onPressed: _isLoading
                      ? null
                      : () {
                          Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => InternacionMapaScreen(
                                authToken: widget.authToken,
                                userId: widget.userId,
                              ),
                            ),
                          );
                        },
                ),
                IconButton(
                  icon: const Icon(Icons.refresh),
                  onPressed: _isLoading ? null : _cargarListadoPacientes,
                ),
              ],
            ] else if (_encounterClass == 'EMER')
              IconButton(
                icon: const Icon(Icons.refresh),
                onPressed: _isLoading ? null : _cargarListadoPacientes,
              ),
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
    if (_consultasAsync.isEmpty && _turnos.isEmpty) {
      return _buildEmpty(
        icon: Icons.event_busy_outlined,
        text: 'No hay turnos programados para esta fecha.',
      );
    }
    return _buildTurnosPorEstado(siguienteTurno);
  }

  Widget _buildTurnosPorEstado(Turno? siguienteTurno) {
    final pendientes = _getPendientes(siguienteTurno);
    final cargadas = _getConsultasCargadas();
    const maxCardWidth = 420.0;

    return ListView(
      padding: const EdgeInsets.symmetric(
        horizontal: BioSpacing.lg,
        vertical: BioSpacing.lg,
      ),
      children: [
        if (_consultasAsync.isNotEmpty) ...[
          _buildAsyncBandejaSection(),
          BioSpacing.gapH(BioSpacing.xl),
        ],
        if (_turnos.isEmpty && _consultasAsync.isNotEmpty)
          _emptyInline('No hay turnos programados para esta fecha.')
        else ...[
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
        _seccionSubtitulo('Pendientes'),
        BioSpacing.gapH(BioSpacing.sm),
        if (pendientes.isEmpty)
          _emptyInline('No hay turnos pendientes.')
        else
          ...pendientes.map((t) => Padding(
                padding: const EdgeInsets.only(bottom: BioSpacing.md),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: maxCardWidth),
                    child: _buildTurnoCard(t, resumenConsultaCargada: false),
                  ),
                ),
              )),
        BioSpacing.gapH(BioSpacing.xl),
        _seccionSubtitulo('Consultas cargadas'),
        BioSpacing.gapH(BioSpacing.sm),
        if (cargadas.isEmpty)
          _emptyInline('No hay consultas cargadas.')
        else
          ...cargadas.map((t) => Padding(
                padding: const EdgeInsets.only(bottom: BioSpacing.md),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: maxCardWidth),
                    child: _buildTurnoCard(t, resumenConsultaCargada: true),
                  ),
                ),
              )),
        ],
      ],
    );
  }

  Widget _buildAsyncBandejaSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Text(
                _tituloConsultasAsync,
                style: BioTypography.h3.copyWith(
                  color: IntentPalette.of(UiIntent.primary).base,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            if (_consultasAsyncSlaIncumplidos > 0)
              BioBadge.danger('$_consultasAsyncSlaIncumplidos SLA vencido'),
          ],
        ),
        BioSpacing.gapH(BioSpacing.sm),
        ..._consultasAsync.map(
          (item) => Padding(
            padding: const EdgeInsets.only(bottom: BioSpacing.md),
            child: _buildAsyncSolicitudCard(item),
          ),
        ),
      ],
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
    final rank = prioridad?['rank'];
    final prioridadRank = rank is int ? rank : int.tryParse('$rank') ?? 0;

    final sla = item['sla'] is Map
        ? Map<String, dynamic>.from(item['sla'] as Map)
        : null;
    final slaIncumplido = sla?['incumplido'] == true;
    final slaHoras = sla?['horas_objetivo'];

    final intakeLines = _asyncIntakeLines(item['intake_context']);

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
              Expanded(
                child: Text(nombrePaciente, style: BioTypography.title),
              ),
              if (statusLabel.isNotEmpty)
                BioBadge(label: statusLabel, intent: statusIntent),
            ],
          ),
          if (servicio.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(servicio, style: BioTypography.bodySm),
          ],
          if (solicitudTipo.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            BioBadge.info(solicitudTipo),
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
          if (intakeLines.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.sm),
            ...intakeLines.map(
              (line) => Padding(
                padding: const EdgeInsets.only(bottom: BioSpacing.xs),
                child: Text(line, style: BioTypography.caption),
              ),
            ),
          ],
          if (prioridadRank > 0 && prioridadRank <= 3) ...[
            BioSpacing.gapH(BioSpacing.xs),
            BioBadge.warning('Prioridad $prioridadRank'),
          ],
          if (slaIncumplido) ...[
            BioSpacing.gapH(BioSpacing.xs),
            BioBadge.danger('SLA vencido${slaHoras != null ? ' ($slaHoras h)' : ''}'),
          ],
          if (puedeTomar || abrirChat) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Wrap(
              spacing: BioSpacing.sm,
              runSpacing: BioSpacing.xs,
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
        ],
      ),
    );
  }

  List<String> _asyncIntakeLines(dynamic intakeContext) {
    if (intakeContext is! Map) return [];
    final ctx = Map<String, dynamic>.from(intakeContext);
    final summary = ctx['summary']?.toString().trim() ?? '';
    if (summary.isNotEmpty) {
      return [summary];
    }
    final lines = ctx['lines'];
    if (lines is! List) return [];
    final out = <String>[];
    for (final line in lines) {
      if (line is! Map) continue;
      final label = line['label']?.toString().trim() ?? '';
      final value = line['value']?.toString().trim() ?? '';
      if (label.isEmpty && value.isEmpty) continue;
      out.add(label.isNotEmpty ? '$label: $value' : value);
    }
    return out;
  }

  String _formatAsyncCreatedAt(String? raw) {
    final s = raw?.trim() ?? '';
    if (s.isEmpty) return '';
    final dt = DateTime.tryParse(s);
    if (dt == null) return s;
    return DateFormat('d/M/y HH:mm', 'es').format(dt.toLocal());
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

  void _abrirChatAsync(Map<String, dynamic> item) {
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

    Navigator.push(
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
    if (_internados.isEmpty) {
      return _buildEmpty(
        icon: Icons.bed_outlined,
        text: 'No hay pacientes internados.',
      );
    }
    return ListView.separated(
      padding: BioSpacing.pageAll,
      itemCount: _internados.length,
      separatorBuilder: (_, __) => BioSpacing.gapH(BioSpacing.sm),
      itemBuilder: (context, index) {
        final i = _internados[index];
        return _buildSimpleTile(
          icon: Icons.person_outline,
          title: i.nombreCompleto,
          subtitle: [
            if (i.cama != null) 'Cama ${i.cama}',
            if (i.sala != null) 'Sala ${i.sala}',
            if (i.documento != null) 'Doc. ${i.documento}',
          ].where((e) => e.isNotEmpty).join(' · '),
          onTap: () => _onTapSinTimeline(context),
        );
      },
    );
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
    if (g.needsTriage) {
      final ok = await Navigator.push<bool>(
        context,
        MaterialPageRoute(
          builder: (context) => EmergencyTriageScreen(
            guardiaId: g.id,
            pacienteNombre: g.nombreCompleto,
            api: _emergencyApi,
          ),
        ),
      );
      if (ok == true) {
        await _cargarListadoPacientes(silent: true);
      }
      return;
    }
    try {
      await _emergencyApi.iniciarAtencion(g.id);
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
  }

  Widget _buildGuardiaTableroList() {
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
    final nivelColor = _colorFromHex(g.triageLevelColor) ??
        (g.prioridadTriage != null
            ? IntentPalette.of(
                g.prioridadTriage! <= 2 ? UiIntent.danger : UiIntent.warning,
              ).base
            : context.bio.textMuted);
    final estadoIntent = g.needsTriage
        ? UiIntent.warning
        : (g.circuitoEstado == 'en_atencion'
            ? UiIntent.info
            : UiIntent.neutral);

    return BioCard(
      onTap: () => _onGuardiaTap(g),
      child: Container(
        decoration: g.slaViolado
            ? BoxDecoration(
                border: Border.all(
                  color: IntentPalette.of(UiIntent.danger).base.withOpacity(0.6),
                  width: 2,
                ),
                borderRadius: BorderRadius.circular(8),
              )
            : null,
        padding: g.slaViolado ? const EdgeInsets.all(4) : EdgeInsets.zero,
        child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 40,
            alignment: Alignment.center,
            padding: const EdgeInsets.symmetric(vertical: BioSpacing.xs),
            decoration: BoxDecoration(
              color: nivelColor.withOpacity(0.15),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: nivelColor, width: 2),
            ),
            child: Text(
              g.prioridadTriage != null ? '${g.prioridadTriage}' : '?',
              style: BioTypography.title.copyWith(
                color: nivelColor,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          BioSpacing.gapW(BioSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(g.nombreCompleto, style: BioTypography.title),
                if (g.documento != null && g.documento!.isNotEmpty) ...[
                  BioSpacing.gapH(2),
                  Text('Doc. ${g.documento}', style: BioTypography.bodySm),
                ],
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
                      BioBadge(
                        label: g.slaTipo == 'triage' ? 'SLA triage' : 'SLA médico',
                        intent: UiIntent.danger,
                      ),
                    if (g.internacionPendiente)
                      const BioBadge(
                        label: 'Cama pend.',
                        intent: UiIntent.info,
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
                      '${g.minutosEspera} min',
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
              if (!g.needsTriage &&
                  !EmergencyGuardiaActions.episodioCerrado(g))
                IconButton(
                  icon: const Icon(Icons.more_vert),
                  tooltip: 'Más acciones',
                  onPressed: () {
                    EmergencyGuardiaActions.showActionSheet(
                      context: context,
                      item: g,
                      api: _emergencyApi,
                      onChanged: () => _cargarListadoPacientes(silent: true),
                      sessionTieneCobertura: _sessionTieneCobertura,
                    );
                  },
                ),
              Icon(
                g.needsTriage
                    ? Icons.assignment_outlined
                    : Icons.chevron_right,
                color: context.bio.textMuted,
              ),
            ],
          ),
        ],
        ),
      ),
    );
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
          if (turno.servicio != null && turno.servicio!.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            _filaInfo(
              Icons.local_hospital_outlined,
              'Servicio: ${turno.servicio}',
            ),
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
    final tokens = context.bio;
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
          // tokens used for layout consistency
          if (tokens.textMuted == Colors.transparent) const SizedBox.shrink(),
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
      await _cargarListadoPacientes(silent: true);
    }
  }

  void _verHistoriaClinicaCirugia(CirugiaAgendaItem c) {
    Navigator.push(
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
  }
}
