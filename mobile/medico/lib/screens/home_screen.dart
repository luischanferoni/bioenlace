// lib/screens/home_screen.dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import '../models/turno.dart';
import '../models/cirugia_agenda_item.dart';
import '../services/internados_service.dart';
import '../services/emergency_guardia_api.dart';
import '../services/pacientes_service.dart';
import 'emergency/emergency_triage_screen.dart';
import 'patient_timeline_screen.dart';

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
  final PacientesService _pacientesService = PacientesService();
  late final EmergencyGuardiaApi _emergencyApi = EmergencyGuardiaApi(
    authToken: widget.authToken,
    userId: widget.userId,
  );

  List<Turno> _turnos = [];
  List<InternadoItem> _internados = [];
  List<EmergencyBoardItem> _guardiaTablero = [];
  List<CirugiaAgendaItem> _cirugias = [];
  String _lastListKind = '';
  bool _isLoading = true;
  String _errorMessage = '';
  DateTime _fechaSeleccionada = DateTime.now();

  String _encounterClass = 'AMB';

  @override
  void initState() {
    super.initState();
    _pacientesService.userId = widget.userId;
    _emergencyApi.userId = widget.userId;
    _init();
  }

  @override
  void dispose() {
    _stopTableroPoll();
    super.dispose();
  }

  Future<void> _init() async {
    if (widget.authToken != null && widget.authToken!.isNotEmpty) {
      _pacientesService.authToken = widget.authToken;
    } else {
      await _loadAuthToken();
    }
    await _loadEncounterAndData();
  }

  Future<void> _loadAuthToken() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token');
      if (token != null && token.isNotEmpty) {
        _pacientesService.authToken = token;
        _emergencyApi.authToken = token;
      } else {
        _pacientesService.userId = widget.userId;
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Error al cargar token de autenticación: ${e.toString()}';
        _isLoading = false;
      });
    }
  }

  Future<void> _loadEncounterAndData() async {
    final prefs = await SharedPreferences.getInstance();
    final encounter = prefs.getString('encounter_class') ?? 'AMB';
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
      if (_encounterClass == 'EMER') {
        final items = await _emergencyApi.getTablero();
        if (!mounted) return;
        setState(() {
          _guardiaTablero = items;
          _turnos = [];
          _internados = [];
          _cirugias = [];
          _lastListKind = 'guardias';
          _isLoading = false;
          _errorMessage = '';
        });
        _startTableroPoll();
        return;
      }
      _stopTableroPoll();
      final fechaStr = DateFormat('yyyy-MM-dd').format(_fechaSeleccionada);
      final res = await _pacientesService.getListado(fecha: fechaStr);
      if (!mounted) return;
      setState(() {
        _turnos = [];
        _internados = [];
        _guardiaTablero = [];
        _cirugias = [];
        _lastListKind = res.kind;
        if (res.kind == 'turnos') {
          _turnos = res.data
              .map((e) => Turno.fromJson(e as Map<String, dynamic>))
              .toList();
        } else if (res.kind == 'internados') {
          _internados = res.data
              .map((e) => InternadoItem.fromJson(e as Map<String, dynamic>))
              .toList();
        } else if (res.kind == 'cirugias') {
          _cirugias = res.data
              .map((e) => CirugiaAgendaItem.fromJson(e as Map<String, dynamic>))
              .toList();
        } else {
          _errorMessage = 'Respuesta inválida del servidor (kind=${res.kind})';
        }
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _errorMessage = 'Error al cargar pacientes: ${e.toString()}';
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

  void _cambiarFecha(int dias) {
    setState(() {
      _fechaSeleccionada = _fechaSeleccionada.add(Duration(days: dias));
    });
    _cargarListadoPacientes();
  }

  void _irAHoy() {
    setState(() {
      _fechaSeleccionada = DateTime.now();
    });
    _cargarListadoPacientes();
  }

  String _formatearFechaAmigable(DateTime fecha) {
    final hoy = DateTime.now();
    final diferencia =
        fecha.difference(DateTime(hoy.year, hoy.month, hoy.day)).inDays;
    if (diferencia == 0) return 'Hoy';
    if (diferencia == 1) return 'Mañana';
    if (diferencia == -1) return 'Ayer';
    return DateFormat('EEEE, d \'de\' MMMM', 'es').format(fecha);
  }

  Turno? _obtenerSiguienteTurno() {
    if (_turnos.isEmpty) return null;
    final ahora = DateTime.now();
    final turnosReales = _turnos.where((turno) => turno.id != 999999).toList();
    if (turnosReales.isEmpty) return null;
    return turnosReales.firstWhere(
      (turno) {
        final fechaHora = turno.fechaHora;
        return fechaHora != null && fechaHora.isAfter(ahora);
      },
      orElse: () => turnosReales.first,
    );
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
                    : _encounterClass == 'IMP'
                        ? (_lastListKind == 'cirugias'
                            ? _buildCirugiasList()
                            : _buildInternadosList())
                        : _encounterClass == 'EMER'
                            ? _buildGuardiaTableroList()
                            : _turnos.isEmpty
                                ? _buildEmpty(
                                    icon: Icons.event_busy_outlined,
                                    text: 'No hay turnos programados para esta fecha.',
                                  )
                                : _buildTurnosPorEstado(siguienteTurno),
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
                    child: _buildTurnoCard(t),
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
                    child: _buildTurnoCard(t),
                  ),
                ),
              )),
      ],
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
          Icon(
            g.needsTriage ? Icons.assignment_outlined : Icons.chevron_right,
            color: context.bio.textMuted,
          ),
        ],
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
    return BioCard.intent(
      intent: UiIntent.primary,
      onTap: () =>
          _verHistoriaClinica(turno.idPersona, parent: 'TURNO', parentId: turno.id),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.calendar_today_outlined, color: primary, size: 22),
              BioSpacing.gapW(BioSpacing.sm),
              Text(
                'Siguiente turno',
                style: BioTypography.h3.copyWith(
                  color: primary,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          BioSpacing.gapH(BioSpacing.md),
          Text(
            'Paciente: ${turno.paciente?.nombreCompleto ?? "Sin paciente"}',
            style: BioTypography.title,
          ),
          BioSpacing.gapH(BioSpacing.xs),
          Text('Fecha: ${turno.fecha}', style: BioTypography.bodySm),
          BioSpacing.gapH(BioSpacing.xs),
          Text('Hora: ${turno.hora}', style: BioTypography.bodySm),
          BioSpacing.gapH(BioSpacing.sm),
          Text(
            'Tocá para ver la historia clínica',
            style: BioTypography.caption.copyWith(
              color: context.bio.textMuted,
              fontStyle: FontStyle.italic,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTurnoCard(Turno turno) {
    final tokens = context.bio;
    final primary = IntentPalette.of(UiIntent.primary).base;
    final estadoIntent = _intentEstado(turno.estado);
    return BioCard(
      onTap: () =>
          _verHistoriaClinica(turno.idPersona, parent: 'TURNO', parentId: turno.id),
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
              label: 'Historia clínica',
              icon: Icons.medical_services_outlined,
              size: BioButtonSize.sm,
              onPressed: () => _verHistoriaClinica(
                turno.idPersona,
                parent: 'TURNO',
                parentId: turno.id,
              ),
            ),
          ),
          // tokens used for layout consistency
          if (tokens.textMuted == Colors.transparent) const SizedBox.shrink(),
        ],
      ),
    );
  }

  Widget _filaInfo(IconData icon, String text, {bool small = false}) {
    final tokens = context.bio;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 16, color: tokens.textMuted),
        BioSpacing.gapW(BioSpacing.xs),
        Expanded(
          child: Text(
            text,
            style: (small ? BioTypography.caption : BioTypography.bodySm),
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

  void _verHistoriaClinica(int personaId, {String? parent, int? parentId}) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => PatientTimelineScreen(
          personaId: personaId,
          authToken: widget.authToken,
          soloVer: parent == null,
          consultParent: parent,
          consultParentId: parentId,
        ),
      ),
    );
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
