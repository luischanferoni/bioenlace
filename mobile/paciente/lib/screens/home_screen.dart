import 'dart:async';

import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../services/turnos_service.dart';
import '../utils/turno_resolucion_utils.dart';
import '../config/paciente_intents.dart';
import 'care_plan_detail_screen.dart';
import 'care_plans_list_screen.dart';
import 'chat_motivos_screen.dart';

/// Proximidad de un turno respecto al día actual (sólo fecha, sin hora).
enum _ProximidadPendiente { hoy, manana, masAdelante }

/// Pantalla de inicio del paciente: saludo + listado de próximos turnos
/// (con `EN_RESOLUCION` priorizado) y, en otra pestaña, el historial.
class HomeScreen extends StatefulWidget {
  final String userId;
  final String userName;
  final String? authToken;
  final void Function(Map<String, dynamic> turno)? onResolverTurno;
  final void Function(String intentId, {Map<String, String>? draft})? onStartAssistantFlow;
  final VoidCallback? onOpenAlertas;
  final int alertasNoLeidas;

  const HomeScreen({
    Key? key,
    required this.userId,
    required this.userName,
    this.authToken,
    this.onResolverTurno,
    this.onStartAssistantFlow,
    this.onOpenAlertas,
    this.alertasNoLeidas = 0,
  }) : super(key: key);

  @override
  State<HomeScreen> createState() => HomeScreenState();
}

class HomeScreenState extends State<HomeScreen> {
  /// Tamaño de página API (máx. 100) para traer todos los próximos en una o pocas requests.
  static const int _proximosPageSize = 100;
  /// Historial: páginas pequeñas cargadas al hacer scroll.
  static const int _pasadosPageLimit = 12;

  late TurnosService _turnosService;
  late CarePlanService _carePlanService;
  late final HomePanelApi _homePanelApi = HomePanelApi(
    authToken: widget.authToken,
    appClient: 'paciente-flutter',
  );
  final ScrollController _scrollController = ScrollController();

  List<Map<String, dynamic>> _carePlansActivos = [];
  bool _loadingCarePlans = false;

  final List<Map<String, dynamic>> _pendientes = [];
  final List<Map<String, dynamic>> _enResolucion = [];
  final List<Map<String, dynamic>> _pasados = [];

  int _totalPasados = 0;
  bool _loadingMasPasados = false;
  bool _refrescandoTabActivo = true;
  String? _error;

  /// 0 = próximos turnos, 1 = historial.
  int _tabTurnos = 0;

  String _displayName = '';

  @override
  void initState() {
    super.initState();
    _turnosService = TurnosService(authToken: widget.authToken);
    _carePlanService = CarePlanService(authToken: widget.authToken);
    _scrollController.addListener(_onScroll);
    unawaited(_bootstrap());
  }

  Future<void> _bootstrap() async {
    await _loadDisplayNameAndActor();
    if (!mounted) return;
    await _cargarInicial();
  }

  Future<void> _loadDisplayNameAndActor() async {
    final name = await PersonDisplayName.resolveForHome(
      userName: widget.userName,
    );
    if (!mounted) return;
    setState(() => _displayName = name);

    final actorId = int.tryParse(widget.userId) ?? 0;
    await PersonRepresentationContext.instance.bindActor(
      actorPersonaId: actorId,
      actorLabel: name,
      authToken: widget.authToken,
    );
  }

  int? get _subjectPersonaId {
    final ctx = PersonRepresentationContext.instance;
    return ctx.actingForOther ? ctx.subjectPersonaId : null;
  }

  @override
  void dispose() {
    _scrollController.removeListener(_onScroll);
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (!_scrollController.hasClients) return;
    final pos = _scrollController.position;
    if (_tabTurnos != 1) return;
    if (pos.maxScrollExtent - pos.pixels < 400) {
      _cargarMasPasados();
    }
  }

  bool get _hayMasPasados => _pasados.length < _totalPasados;

  /// Trae el listado completo de un alcance (paginando en el servidor si hace falta).
  Future<({List<Map<String, dynamic>> turnos, String? error})> _fetchAllPorAlcance(
    String alcance,
  ) async {
    var offset = 0;
    var total = 1;
    final all = <Map<String, dynamic>>[];
    String? error;
    while (offset < total) {
      final r = await _turnosService.getMisTurnos(
        alcance: alcance,
        limit: _proximosPageSize,
        offset: offset,
        subjectPersonaId: _subjectPersonaId,
      );
      if (r['success'] != true) {
        error = r['message'] as String? ?? 'Error al cargar turnos';
        break;
      }
      final batch = _asMapList(r['turnos']);
      all.addAll(batch);
      total = r['total'] as int? ?? all.length;
      if (batch.isEmpty) break;
      offset += batch.length;
      if (batch.length < _proximosPageSize) break;
    }
    return (turnos: all, error: error);
  }

  /// Próximos unificados: primero EN_RESOLUCION, luego pendientes por fecha/hora.
  List<Map<String, dynamic>> get _proximosVisibles {
    final all = <Map<String, dynamic>>[..._enResolucion, ..._pendientes];
    all.sort((a, b) {
      final ar = TurnoResolucionUtils.esEnResolucion(a);
      final br = TurnoResolucionUtils.esEnResolucion(b);
      if (ar != br) {
        return ar ? -1 : 1;
      }
      final da = parseTurnoInicioProducto(a);
      final db = parseTurnoInicioProducto(b);
      if (da == null && db == null) return 0;
      if (da == null) return 1;
      if (db == null) return -1;
      return da.compareTo(db);
    });
    return all;
  }

  Future<void> _cargarEnResolucion() async {
    final r = await _fetchAllPorAlcance('en_resolucion');
    if (!mounted) return;
    setState(() {
      _enResolucion
        ..clear()
        ..addAll(r.turnos);
    });
  }

  /// Recarga próximos (incluye EN_RESOLUCION) tras resolver un turno en el asistente.
  Future<void> refrescarProximos() async {
    await _cargarInicial();
  }

  Future<void> _cargarCarePlans() async {
    setState(() => _loadingCarePlans = true);
    try {
      final panel = await _homePanelApi.getPanel(
        sections: 'care_plans_active',
        subjectPersonaId: _subjectPersonaId,
      );
      if (!mounted) return;
      final care = panel.sectionByKind('patient_care_plans_active');
      setState(() {
        _loadingCarePlans = false;
        if (care != null) {
          _carePlansActivos = _asMapList(care.data['items']);
        }
      });
    } catch (_) {
      final r = await _carePlanService.fetchActivePlans(
        subjectPersonaId: _subjectPersonaId,
      );
      if (!mounted) return;
      setState(() {
        _loadingCarePlans = false;
        if (r['success'] == true) {
          _carePlansActivos = _asMapList(r['data']);
        }
      });
    }
  }

  Future<void> _applyUpcomingFromPanel() async {
    final panel = await _homePanelApi.getPanel(
      sections: 'upcoming_appointments,care_plans_active',
      subjectPersonaId: _subjectPersonaId,
    );
    final upcoming = panel.sectionByKind('patient_upcoming_appointments');
    if (upcoming != null) {
      final enR = upcoming.data['en_resolucion'];
      final pend = upcoming.data['pendientes'];
      if (enR is Map) {
        _enResolucion
          ..clear()
          ..addAll(_asMapList(enR['turnos']));
      }
      if (pend is Map) {
        _pendientes
          ..clear()
          ..addAll(_asMapList(pend['turnos']));
      }
    }
    final care = panel.sectionByKind('patient_care_plans_active');
    if (care != null) {
      _carePlansActivos = _asMapList(care.data['items']);
      _loadingCarePlans = false;
    }
  }

  Future<void> _cargarInicial() async {
    setState(() {
      _refrescandoTabActivo = true;
      _loadingCarePlans = true;
      _error = null;
      _pendientes.clear();
      _enResolucion.clear();
      _pasados.clear();
      _totalPasados = 0;
    });
    try {
      await _applyUpcomingFromPanel();
    } catch (e) {
      final pendientesFuture = _fetchAllPorAlcance('pendientes');
      await _cargarEnResolucion();
      await _cargarCarePlans();
      final pendientesR = await pendientesFuture;
      if (!mounted) return;
      setState(() {
        _pendientes
          ..clear()
          ..addAll(pendientesR.turnos);
        if (pendientesR.error != null && pendientesR.turnos.isEmpty) {
          _error = pendientesR.error;
        }
      });
    }
    if (!mounted) return;
    setState(() {
      _refrescandoTabActivo = false;
      _loadingCarePlans = false;
    });
  }

  Future<void> _refrescoPullCompleto() async {
    setState(() {
      _error = null;
      _pendientes.clear();
      _enResolucion.clear();
      _pasados.clear();
    });
    try {
      await _applyUpcomingFromPanel();
    } catch (_) {
      final pendientesFuture = _fetchAllPorAlcance('pendientes');
      await _cargarEnResolucion();
      await _cargarCarePlans();
      final pendientesR = await pendientesFuture;
      if (!mounted) return;
      setState(() {
        _pendientes
          ..clear()
          ..addAll(pendientesR.turnos);
      });
    }
    final r2 = await _turnosService.getMisTurnos(
      alcance: 'pasados',
      limit: _pasadosPageLimit,
      offset: 0,
      subjectPersonaId: _subjectPersonaId,
    );
    if (!mounted) return;
    setState(() {
      if (r2['success'] == true) {
        _pasados
          ..clear()
          ..addAll(_asMapList(r2['turnos']));
        _totalPasados = r2['total'] as int? ?? _pasados.length;
        _error = null;
      } else {
        _error = r2['message'] as String?;
      }
    });
  }

  List<Map<String, dynamic>> _asMapList(dynamic raw) {
    if (raw is! List) return [];
    return raw.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  Future<void> _cargarMasPasados() async {
    if (_loadingMasPasados || !_hayMasPasados) return;
    setState(() => _loadingMasPasados = true);
    final r = await _turnosService.getMisTurnos(
      alcance: 'pasados',
      limit: _pasadosPageLimit,
      offset: _pasados.length,
      subjectPersonaId: _subjectPersonaId,
    );
    if (!mounted) return;
    setState(() {
      _loadingMasPasados = false;
      if (r['success'] == true) {
        _pasados.addAll(_asMapList(r['turnos']));
        _totalPasados = r['total'] as int? ?? _pasados.length;
      }
    });
  }

  Future<void> _recargarPendientesDesdeCero() async {
    setState(() {
      _pendientes.clear();
      _enResolucion.clear();
    });
    try {
      await _applyUpcomingFromPanel();
      if (!mounted) return;
      setState(() => _error = null);
    } catch (_) {
      final pendientesR = await _fetchAllPorAlcance('pendientes');
      await _cargarEnResolucion();
      if (!mounted) return;
      setState(() {
        _pendientes
          ..clear()
          ..addAll(pendientesR.turnos);
        if (pendientesR.error != null && pendientesR.turnos.isEmpty) {
          _error = pendientesR.error;
        } else if (pendientesR.error == null) {
          _error = null;
        }
      });
    }
  }

  Future<void> _recargarPasadosDesdeCero() async {
    setState(() {
      _pasados.clear();
      _totalPasados = 0;
    });
    final r = await _turnosService.getMisTurnos(
      alcance: 'pasados',
      limit: _pasadosPageLimit,
      offset: 0,
      subjectPersonaId: _subjectPersonaId,
    );
    if (!mounted) return;
    if (r['success'] == true) {
      setState(() {
        _pasados
          ..clear()
          ..addAll(_asMapList(r['turnos']));
        _totalPasados = r['total'] as int? ?? _pasados.length;
        _error = null;
      });
    } else {
      setState(() => _error = r['message'] as String?);
    }
  }

  Future<void> _alCambiarTab(int nuevoTab) async {
    if (nuevoTab == _tabTurnos) return;
    setState(() {
      _tabTurnos = nuevoTab;
      _refrescandoTabActivo = true;
      if (_scrollController.hasClients) {
        _scrollController.jumpTo(0);
      }
    });
    if (nuevoTab == 0) {
      await _recargarPendientesDesdeCero();
    } else {
      await _recargarPasadosDesdeCero();
    }
    if (!mounted) return;
    setState(() => _refrescandoTabActivo = false);
  }

  DateTime? _fechaTurnoSoloDia(Map<String, dynamic> t) {
    final raw = t['fecha']?.toString();
    if (raw == null || raw.isEmpty) return null;
    final parts = raw.split('-');
    if (parts.length != 3) return null;
    final y = int.tryParse(parts[0]);
    final mo = int.tryParse(parts[1]);
    final d = int.tryParse(parts[2]);
    if (y == null || mo == null || d == null) return null;
    return DateTime(y, mo, d);
  }

  _ProximidadPendiente _proximidadPendiente(Map<String, dynamic> t) {
    final slot = _fechaTurnoSoloDia(t);
    if (slot == null) return _ProximidadPendiente.masAdelante;
    final today = DateTime.now();
    final t0 = DateTime(today.year, today.month, today.day);
    final s0 = DateTime(slot.year, slot.month, slot.day);
    final diffDays = s0.difference(t0).inDays;
    if (diffDays == 0) return _ProximidadPendiente.hoy;
    if (diffDays == 1) return _ProximidadPendiente.manana;
    return _ProximidadPendiente.masAdelante;
  }

  /// Intent semántico para un turno próximo (decide el color de la cinta + badge).
  UiIntent _intentProximidad(_ProximidadPendiente p) {
    switch (p) {
      case _ProximidadPendiente.hoy:
        return UiIntent.danger;
      case _ProximidadPendiente.manana:
        return UiIntent.info;
      case _ProximidadPendiente.masAdelante:
        return UiIntent.success;
    }
  }

  String _labelProximidad(_ProximidadPendiente p) {
    switch (p) {
      case _ProximidadPendiente.hoy:
        return 'Hoy';
      case _ProximidadPendiente.manana:
        return 'Mañana';
      case _ProximidadPendiente.masAdelante:
        return 'Próximamente';
    }
  }

  String _saludo() {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'Buenos días';
    if (hour < 19) return 'Buenas tardes';
    return 'Buenas noches';
  }

  String _profesionalSinDni(String? raw) {
    if (raw == null || raw.isEmpty) return '';
    final s = raw.trim();
    const sep = ' - ';
    final i = s.lastIndexOf(sep);
    if (i <= 0) return s;
    final tail = s.substring(i + sep.length).trim();
    if (RegExp(r'^[0-9.\-\s]+$').hasMatch(tail)) {
      return s.substring(0, i).trim();
    }
    return s;
  }

  static const _weekdaysEs = [
    'domingo',
    'lunes',
    'martes',
    'miércoles',
    'jueves',
    'viernes',
    'sábado',
  ];

  String _fechaAmigable(String? fechaYmd) {
    if (fechaYmd == null || fechaYmd.isEmpty) return '';
    final parts = fechaYmd.split('-');
    if (parts.length != 3) return fechaYmd;
    final y = int.tryParse(parts[0]);
    final mo = int.tryParse(parts[1]);
    final d = int.tryParse(parts[2]);
    if (y == null || mo == null || d == null) return fechaYmd;
    final slot = DateTime(y, mo, d);
    final today = DateTime.now();
    final t0 = DateTime(today.year, today.month, today.day);
    final s0 = DateTime(slot.year, slot.month, slot.day);
    final diffDays = s0.difference(t0).inDays;
    if (diffDays == 0) return 'Hoy';
    if (diffDays == 1) return 'Mañana';
    if (diffDays == 2) return 'Pasado mañana';
    final name = _weekdaysEs[slot.weekday % 7];
    return '$name ${d.toString().padLeft(2, '0')}/${mo.toString().padLeft(2, '0')}';
  }

  /// `encounter_id` (alias legacy `id_consulta`) en el listado de turnos.
  int? _encounterIdDesdeTurno(Map<String, dynamic> t) {
    final raw = t['encounter_id'] ?? t['id_consulta'];
    if (raw == null) return null;
    if (raw is int) return raw > 0 ? raw : null;
    final n = int.tryParse(raw.toString());
    return n != null && n > 0 ? n : null;
  }

  void _abrirMotivosConsulta(
    BuildContext context,
    int consultaId,
    Map<String, dynamic> t,
  ) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ChatMotivosScreen(
          consultaId: consultaId,
          authToken: widget.authToken,
          userId: widget.userId,
          userName: widget.userName,
          titulo:
              'Motivos · ${_fechaAmigable(t['fecha']?.toString())} · ${_horaSinSegundos(t['hora']?.toString())}',
        ),
      ),
    );
  }

  void _abrirPrepararConsulta(BuildContext context, Map<String, dynamic> t) {
    abrirPrepararConsultaHub(
      context: context,
      turno: t,
      authToken: widget.authToken,
      subjectPersonaId: _subjectPersonaId,
      onOpenMotivos: _onOpenMotivosConsulta,
    );
  }

  void _abrirSeguimientoPostConsulta(BuildContext context, Map<String, dynamic> t) {
    abrirSeguimientoPostConsultaHub(
      context: context,
      turno: t,
      authToken: widget.authToken,
      subjectPersonaId: _subjectPersonaId,
      onOpenMotivos: _onOpenMotivosConsulta,
    );
  }

  void _onOpenMotivosConsulta(
    BuildContext context, {
    required int consultaId,
    required String titulo,
  }) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ChatMotivosScreen(
          consultaId: consultaId,
          authToken: widget.authToken,
          userId: widget.userId,
          userName: widget.userName,
          titulo: titulo,
        ),
      ),
    );
  }

  String _horaSinSegundos(String? hora) {
    if (hora == null || hora.trim().isEmpty) return '';
    final t = hora.trim();
    final idx = t.indexOf(':');
    if (idx < 0) return t;
    final hStr = t.substring(0, idx);
    final rest = t.substring(idx + 1);
    final idx2 = rest.indexOf(':');
    final minStr = idx2 >= 0 ? rest.substring(0, idx2) : rest;
    final h = int.tryParse(hStr);
    final m = int.tryParse(minStr);
    if (h != null && m != null) {
      return '${h.toString().padLeft(2, '0')}:${m.toString().padLeft(2, '0')}';
    }
    if (t.length >= 5) return t.substring(0, 5);
    return t;
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;

    return Scaffold(
      backgroundColor: tokens.paperBackground,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _refrescoPullCompleto,
          child: _error != null &&
                !_refrescandoTabActivo &&
                _proximosVisibles.isEmpty &&
                _pasados.isEmpty
            ? _buildErrorEstado(context)
            : _buildContenido(context),
        ),
      ),
    );
  }

  Widget _buildErrorEstado(BuildContext context) {
    return Center(
      child: Padding(
        padding: BioSpacing.pageAll,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            BioAlert.danger(message: _error!),
            BioSpacing.gapH(BioSpacing.lg),
            BioButton.primary(
              label: 'Reintentar',
              onPressed: _cargarInicial,
              icon: Icons.refresh,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildContenido(BuildContext context) {
    return ListView(
      controller: _scrollController,
      padding: const EdgeInsets.symmetric(
        horizontal: BioSpacing.lg,
        vertical: BioSpacing.xl,
      ),
      children: [
        _buildHeaderSaludo(context),
        if (_enResolucion.isNotEmpty) ...[
          BioSpacing.gapH(BioSpacing.lg),
          _buildEnResolucionBanner(context),
        ],
        if (_carePlansActivos.isNotEmpty || _loadingCarePlans) ...[
          BioSpacing.gapH(BioSpacing.lg),
          _buildTratamientoCard(context),
        ],
        BioSpacing.gapH(BioSpacing.md),
        if (_error != null &&
            (_proximosVisibles.isNotEmpty || _pasados.isNotEmpty)) ...[
          BioAlert.danger(message: _error!),
          BioSpacing.gapH(BioSpacing.md),
        ],
        _buildSelectorTab(context),
        if (_tabTurnos == 0) ...[
          BioSpacing.gapH(BioSpacing.md),
          if (_refrescandoTabActivo && _proximosVisibles.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: BioSpacing.xl),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_proximosVisibles.isEmpty)
            _buildEmptyProximos(context)
          else ...[
            ..._proximosVisibles.map((t) {
              final enRes = TurnoResolucionUtils.esEnResolucion(t);
              final prox = enRes ? null : _proximidadPendiente(t);
              return Padding(
                padding: const EdgeInsets.only(bottom: BioSpacing.sm),
                child: _buildTurnoCard(
                  context,
                  t,
                  futuro: true,
                  proximidad: prox,
                  enResolucion: enRes,
                ),
              );
            }),
          ],
        ] else ...[
          BioSpacing.gapH(BioSpacing.md),
          if (_refrescandoTabActivo && _pasados.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: BioSpacing.xl),
              child: Center(child: CircularProgressIndicator()),
            ),
          if (!_refrescandoTabActivo && _pasados.isEmpty) ...[
            _buildEmptyPasados(context),
            BioSpacing.gapH(BioSpacing.sm),
          ],
          ..._pasados.map((t) => Padding(
                padding: const EdgeInsets.only(bottom: BioSpacing.sm),
                child: _buildTurnoCard(context, t, futuro: false),
              )),
          if (_loadingMasPasados && _hayMasPasados) _buildLoaderInline(),
        ],
        BioSpacing.gapH(BioSpacing.xl),
      ],
    );
  }

  void _abrirDetalleCarePlan(Map<String, dynamic> plan) {
    final id = CarePlanUi.idFromMap(plan);
    if (id == null) return;
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => CarePlanDetailScreen(
          planId: id,
          authToken: widget.authToken,
          initialSummary: plan,
          onStartAssistantFlow: widget.onStartAssistantFlow,
        ),
      ),
    );
  }

  void _abrirListaCarePlans() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => CarePlansListScreen(
          plans: List<Map<String, dynamic>>.from(_carePlansActivos),
          authToken: widget.authToken,
        ),
      ),
    );
  }

  Widget _buildEnResolucionBanner(BuildContext context) {
    final turno = _enResolucion.first;
    final fecha = _fechaAmigable(turno['fecha']?.toString());
    final hora = _horaSinSegundos(turno['hora']?.toString());

    return BioCard.intent(
      intent: UiIntent.warning,
      onTap: widget.onResolverTurno != null ? () => widget.onResolverTurno!(turno) : null,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.warning_amber_outlined, color: IntentPalette.of(UiIntent.warning).base),
          BioSpacing.gapW(BioSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Turno en resolución', style: BioTypography.title),
                BioSpacing.gapH(BioSpacing.xs),
                Text(
                  '$fecha · $hora',
                  style: BioTypography.bodySm,
                ),
                if (widget.onResolverTurno != null) ...[
                  BioSpacing.gapH(BioSpacing.sm),
                  Text(
                    'Tocá para continuar',
                    style: BioTypography.caption.copyWith(color: context.bio.textMuted),
                  ),
                ],
              ],
            ),
          ),
          if (widget.onResolverTurno != null)
            Icon(Icons.chevron_right, color: context.bio.textMuted),
        ],
      ),
    );
  }

  Widget _buildTratamientoCard(BuildContext context) {
    if (_loadingCarePlans && _carePlansActivos.isEmpty) {
      return const BioCard(
        child: Padding(
          padding: EdgeInsets.all(BioSpacing.md),
          child: Center(
            child: SizedBox(
              width: 24,
              height: 24,
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
          ),
        ),
      );
    }

    final plan = _carePlansActivos.first;
    final status = plan['status']?.toString();
    final intent = CarePlanUi.intentForStatus(status);
    final lines = CarePlanUi.activitySummaries(plan, max: 3);
    final varios = _carePlansActivos.length > 1;

    return BioCard.intent(
      intent: intent,
      onTap: () => _abrirDetalleCarePlan(plan),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.medical_services_outlined, color: context.bio.textMuted, size: 22),
              BioSpacing.gapW(BioSpacing.sm),
              Expanded(
                child: Text('Tu tratamiento', style: BioTypography.h3),
              ),
              BioBadge(
                label: CarePlanUi.statusLabel(plan),
                intent: intent,
              ),
              BioSpacing.gapW(BioSpacing.xs),
              Icon(Icons.chevron_right, color: context.bio.textMuted, size: 22),
            ],
          ),
          BioSpacing.gapH(BioSpacing.xs),
          Text(CarePlanUi.categoryLabel(plan), style: BioTypography.title),
          if (lines.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.sm),
            ...lines.map(
              (line) => Padding(
                padding: const EdgeInsets.only(bottom: BioSpacing.xs),
                child: Text('• $line', style: BioTypography.body),
              ),
            ),
          ],
          if (varios) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Align(
              alignment: Alignment.centerLeft,
              child: BioButton(
                label: 'Ver todos (${_carePlansActivos.length})',
                intent: UiIntent.neutral,
                variant: BioButtonVariant.soft,
                size: BioButtonSize.sm,
                onPressed: _abrirListaCarePlans,
              ),
            ),
          ] else ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              'Ver detalle',
              style: BioTypography.caption.copyWith(color: context.bio.textMuted),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildHeaderSaludo(BuildContext context) {
    final nombre =
        _displayName.isNotEmpty ? _displayName : widget.userName.split(',').first.trim();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _saludo(),
                    style: BioTypography.title.copyWith(
                      fontSize: 17,
                      color: context.bio.textMuted,
                    ),
                  ),
                  BioSpacing.gapH(BioSpacing.xs),
                  Text(
                    nombre,
                    style: BioTypography.h3,
                  ),
                ],
              ),
            ),
            if (widget.onOpenAlertas != null)
              IconButton(
                tooltip: 'Alertas',
                onPressed: widget.onOpenAlertas,
                icon: Badge(
                  isLabelVisible: widget.alertasNoLeidas > 0,
                  backgroundColor: IntentPalette.of(UiIntent.danger).base,
                  label: Text(
                    widget.alertasNoLeidas > 99 ? '99+' : '${widget.alertasNoLeidas}',
                  ),
                  child: const Icon(Icons.notifications_outlined),
                ),
              ),
          ],
        ),
        PersonRepresentationActiveBanner(
          authToken: widget.authToken,
          onSubjectChanged: _cargarInicial,
          margin: const EdgeInsets.only(top: BioSpacing.sm),
        ),
        PersonRepresentationSubjectChip(
          authToken: widget.authToken,
          onSubjectChanged: _cargarInicial,
        ),
      ],
    );
  }

  Widget _buildSelectorTab(BuildContext context) {
    return BioSegmentedTabs(
      selectedIndex: _tabTurnos,
      onSelected: _alCambiarTab,
      tabs: const [
        BioSegmentedTab(label: 'Próximos', icon: Icons.event_outlined),
        BioSegmentedTab(label: 'Anteriores', icon: Icons.history),
      ],
    );
  }

  Widget _buildLoaderInline() {
    return const Padding(
      padding: EdgeInsets.symmetric(vertical: BioSpacing.sm),
      child: Center(
        child: SizedBox(
          width: 24,
          height: 24,
          child: CircularProgressIndicator(strokeWidth: 2),
        ),
      ),
    );
  }

  Widget _buildTurnoCard(
    BuildContext context,
    Map<String, dynamic> t, {
    required bool futuro,
    _ProximidadPendiente? proximidad,
    bool enResolucion = false,
  }) {
    final tokens = context.bio;
    final estado = t['estado_label']?.toString() ?? t['estado']?.toString() ?? '';
    final usaJourney = turnoTieneJourneyPayload(t);
    final usaJourneyPre = futuro && !enResolucion && usaJourney;
    final usaJourneyPost = !futuro && usaJourney;
    final prepararPendiente =
        usaJourneyPre && prepararConsultaTienePendientes(t);
    final seguimientoPendiente =
        usaJourneyPost && seguimientoPostConsultaTienePendientes(t);
    final puedeMotivos = !usaJourneyPre &&
        futuro &&
        !enResolucion &&
        turnoTieneEncounterParaMotivos(t) &&
        turnoMotivosInputAbiertoEnProducto(t);
    final puedeAsistenciaCohorte = !usaJourneyPre &&
        futuro &&
        !enResolucion &&
        turnoAsistenciaCohorteDisponibleEnProducto(t);
    final idConsulta = puedeMotivos ? _encounterIdDesdeTurno(t) : null;
    final turnoId = turnoIdDesdePayloadProducto(t);

    final cabecera = Text(
      '${_fechaAmigable(t['fecha']?.toString())} · ${_horaSinSegundos(t['hora']?.toString())}',
      style: BioTypography.title,
    );

    final servicio = t['servicio'] != null
        ? Text(t['servicio'].toString(), style: BioTypography.bodySm)
        : null;
    final profesional = t['profesional'] != null
        ? Text(
            'Con: ${_profesionalSinDni(t['profesional']?.toString())}',
            style: BioTypography.bodySm,
          )
        : null;

    Widget? badge;
    if (enResolucion) {
      badge = BioBadge.warning(
        'En resolución',
        icon: Icons.warning_amber_outlined,
      );
    } else if (futuro && proximidad != null) {
      badge = BioBadge(
        label: _labelProximidad(proximidad),
        intent: _intentProximidad(proximidad),
      );
    } else if (!futuro && estado.isNotEmpty) {
      badge = BioBadge.neutral(estado);
    }

    final acciones = <Widget>[];
    if (enResolucion && widget.onResolverTurno != null) {
      acciones.add(BioButton(
        label: 'Resolver',
        intent: UiIntent.warning,
        variant: BioButtonVariant.filled,
        size: BioButtonSize.sm,
        icon: Icons.build_circle_outlined,
        onPressed: () => widget.onResolverTurno!(t),
      ));
    } else if (!enResolucion && prepararPendiente) {
      acciones.add(BioButton.outlinePrimary(
        label: 'Preparar tu consulta',
        size: BioButtonSize.sm,
        icon: Icons.event_available_outlined,
        onPressed: () => _abrirPrepararConsulta(context, t),
      ));
    } else if (!enResolucion && seguimientoPendiente) {
      acciones.add(BioButton.outlinePrimary(
        label: 'Seguimiento post-consulta',
        size: BioButtonSize.sm,
        icon: Icons.health_and_safety_outlined,
        onPressed: () => _abrirSeguimientoPostConsulta(context, t),
      ));
    } else if (!enResolucion && idConsulta != null) {
      acciones.add(BioButton.outlinePrimary(
        label: 'Cargar motivos de consulta',
        size: BioButtonSize.sm,
        icon: Icons.edit_note,
        onPressed: () => _abrirMotivosConsulta(context, idConsulta, t),
      ));
    }
    if (!enResolucion && puedeAsistenciaCohorte && turnoId != null) {
      acciones.add(BioButton.outlinePrimary(
        label: 'Cuestionario pre-consulta',
        size: BioButtonSize.sm,
        icon: Icons.fact_check_outlined,
        onPressed: () {
          abrirAsistenciaPreConsulta(
            context: context,
            turnoId: turnoId,
            authToken: widget.authToken,
            subjectPersonaId: _subjectPersonaId,
          );
        },
      ));
    }

    final contenido = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(child: cabecera),
            if (badge != null) ...[
              BioSpacing.gapW(BioSpacing.sm),
              badge,
            ],
          ],
        ),
        if (servicio != null) ...[
          BioSpacing.gapH(BioSpacing.xs),
          servicio,
        ],
        if (profesional != null) ...[
          BioSpacing.gapH(BioSpacing.xs),
          profesional,
        ],
        if (acciones.isNotEmpty) ...[
          BioSpacing.gapH(BioSpacing.sm),
          Wrap(
            spacing: BioSpacing.sm,
            runSpacing: BioSpacing.xs,
            children: acciones,
          ),
        ],
      ],
    );

    if (enResolucion) {
      return BioCard.intent(
        intent: UiIntent.warning,
        padding: const EdgeInsets.symmetric(
          horizontal: BioSpacing.md,
          vertical: BioSpacing.md,
        ),
        child: contenido,
      );
    }
    if (futuro && proximidad != null) {
      return BioCard.intent(
        intent: _intentProximidad(proximidad),
        padding: const EdgeInsets.symmetric(
          horizontal: BioSpacing.md,
          vertical: BioSpacing.md,
        ),
        child: contenido,
      );
    }
    return BioCard(
      padding: const EdgeInsets.symmetric(
        horizontal: BioSpacing.md,
        vertical: BioSpacing.md,
      ),
      color: tokens.paperSurfaceSunken,
      child: contenido,
    );
  }

  Widget _buildEmptyProximos(BuildContext context) {
    return BioCard(
      color: context.bio.paperSurfaceSunken,
      child: Row(
        children: [
          Icon(Icons.event_available, color: context.bio.textMuted),
          BioSpacing.gapW(BioSpacing.md),
          Expanded(
            child: Text(
              'No tienes turnos pendientes. Puede pedir uno desde el asistente.',
              style: BioTypography.body,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyPasados(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: BioSpacing.sm),
      child: Text(
        'No hay turnos anteriores en tu historial.',
        style: BioTypography.bodySm.copyWith(color: context.bio.textMuted),
      ),
    );
  }
}
