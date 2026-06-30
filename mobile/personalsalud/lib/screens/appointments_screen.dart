// lib/screens/appointments_screen.dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:shared/shared.dart';

import '../models/turno.dart';
import '../services/turnos_service.dart';
import 'patient_timeline_screen.dart';
import 'chat_consulta_screen.dart';

class AppointmentsScreen extends StatefulWidget {
  final String? authToken;
  final String? idProfesionalEfectorServicio;
  final String? userId;

  const AppointmentsScreen({
    Key? key,
    this.authToken,
    this.idProfesionalEfectorServicio,
    this.userId,
  }) : super(key: key);

  @override
  State<AppointmentsScreen> createState() => _AppointmentsScreenState();
}

class _AppointmentsScreenState extends State<AppointmentsScreen> {
  final TurnosService _turnosService = TurnosService();
  List<Turno> _turnos = [];
  bool _isLoading = true;
  String _errorMessage = '';
  DateTime _fechaSeleccionada = DateTime.now();

  @override
  void initState() {
    super.initState();
    if (widget.authToken != null) {
      _turnosService.authToken = widget.authToken;
    }
    _cargarTurnos();
  }

  Future<void> _cargarTurnos() async {
    setState(() {
      _isLoading = true;
      _errorMessage = '';
    });
    try {
      final fechaStr = DateFormat('yyyy-MM-dd').format(_fechaSeleccionada);
      final turnos = await _turnosService.getTurnosPorFecha(
        fechaStr,
        idProfesionalEfectorServicio: widget.idProfesionalEfectorServicio,
      );
      setState(() {
        _turnos = turnos;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _errorMessage = 'Error al cargar turnos: ${e.toString()}';
        _isLoading = false;
      });
    }
  }

  void _cambiarFecha(int dias) {
    setState(() {
      _fechaSeleccionada = _fechaSeleccionada.add(Duration(days: dias));
    });
    _cargarTurnos();
  }

  void _irAHoy() {
    setState(() {
      _fechaSeleccionada = DateTime.now();
    });
    _cargarTurnos();
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
    final ahora = nowProducto();
    Turno? candidato;
    for (final turno in _turnos) {
      final inicio = parseTurnoInicioProducto({
        'fecha': turno.fecha,
        'hora': turno.hora,
      });
      if (inicio != null && inicio.isAfter(ahora)) {
        candidato = turno;
        break;
      }
    }
    return candidato;
  }

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

  String? _labelTipoAtencion(String? tipo) {
    switch (tipo) {
      case 'teleconsulta':
        return 'Teleconsulta';
      case 'presencial':
        return 'Presencial';
      default:
        return null;
    }
  }

  UiIntent _intentTipoAtencion(String? tipo) {
    return tipo == 'teleconsulta' ? UiIntent.info : UiIntent.neutral;
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final esHoy = _fechaSeleccionada.year == DateTime.now().year &&
        _fechaSeleccionada.month == DateTime.now().month &&
        _fechaSeleccionada.day == DateTime.now().day;
    final siguienteTurno = esHoy ? _obtenerSiguienteTurno() : null;

    return Scaffold(
      appBar: const BioAppBar(title: 'Turnos'),
      body: Container(
        color: tokens.paperBackground,
        child: Column(
          children: [
            _buildHeader(),
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator())
                  : _errorMessage.isNotEmpty
                      ? _buildError()
                      : _turnos.isEmpty
                          ? _buildEmpty()
                          : ListView(
                              padding: const EdgeInsets.all(BioSpacing.lg),
                              children: [
                                if (siguienteTurno != null) ...[
                                  _buildSiguienteTurnoCard(siguienteTurno),
                                  BioSpacing.gapH(BioSpacing.lg),
                                ],
                                ..._turnos.map((turno) => Padding(
                                      padding: const EdgeInsets.only(
                                          bottom: BioSpacing.md),
                                      child: _buildTurnoCard(turno),
                                    )),
                              ],
                            ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader() {
    final tokens = context.bio;
    return Container(
      padding: const EdgeInsets.all(BioSpacing.lg),
      decoration: BoxDecoration(
        color: tokens.paperSurface,
        border: BioBorder.bottom(BorderWidth.medium, tokens.paperBorderEmphasis),
      ),
      child: Column(
        children: [
          Text(
            _formatearFechaAmigable(_fechaSeleccionada),
            style: BioTypography.h3,
          ),
          BioSpacing.gapH(BioSpacing.md),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              BioButton(
                label: 'Anterior',
                icon: Icons.chevron_left,
                intent: UiIntent.neutral,
                variant: BioButtonVariant.outline,
                size: BioButtonSize.sm,
                onPressed: () => _cambiarFecha(-1),
              ),
              BioSpacing.gapW(BioSpacing.sm),
              BioButton(
                label: 'Hoy',
                intent: UiIntent.neutral,
                variant: BioButtonVariant.outline,
                size: BioButtonSize.sm,
                onPressed: _irAHoy,
              ),
              BioSpacing.gapW(BioSpacing.sm),
              BioButton(
                label: 'Siguiente',
                iconRight: Icons.chevron_right,
                intent: UiIntent.neutral,
                variant: BioButtonVariant.outline,
                size: BioButtonSize.sm,
                onPressed: () => _cambiarFecha(1),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildError() {
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
              onPressed: _cargarTurnos,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmpty() {
    final tokens = context.bio;
    return Center(
      child: Padding(
        padding: BioSpacing.pageAll,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.event_busy_outlined, size: 48, color: tokens.textMuted),
            BioSpacing.gapH(BioSpacing.md),
            Text(
              'No hay turnos programados para esta fecha.',
              style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSiguienteTurnoCard(Turno turno) {
    final primary = IntentPalette.of(UiIntent.primary).base;
    return BioCard.intent(
      intent: UiIntent.primary,
      onTap: () => _verHistoriaClinica(turno.idPersona,
          parent: 'TURNO', parentId: turno.id),
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
    final primary = IntentPalette.of(UiIntent.primary).base;
    final estadoIntent = _intentEstado(turno.estado);
    return BioCard(
      onTap: () => _verHistoriaClinica(turno.idPersona,
          parent: 'TURNO', parentId: turno.id),
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
              if (_labelTipoAtencion(turno.tipoAtencion) != null) ...[
                BioBadge(
                  label: _labelTipoAtencion(turno.tipoAtencion)!,
                  intent: _intentTipoAtencion(turno.tipoAtencion),
                ),
                BioSpacing.gapW(BioSpacing.xs),
              ],
              BioBadge(label: turno.estadoLabel, intent: estadoIntent),
            ],
          ),
          BioSpacing.gapH(BioSpacing.md),
          _filaInfo(Icons.access_time, 'Hora: ${turno.hora}'),
          BioSpacing.gapH(BioSpacing.xs),
          _filaInfo(Icons.local_hospital_outlined,
              'Servicio: ${turno.servicio ?? "Sin servicio"}'),
          if (turno.observaciones != null && turno.observaciones!.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            _filaInfo(Icons.note_outlined,
                'Observaciones: ${turno.observaciones}',
                small: true),
          ],
          BioSpacing.gapH(BioSpacing.md),
          Wrap(
            alignment: WrapAlignment.end,
            spacing: BioSpacing.sm,
            runSpacing: BioSpacing.xs,
            children: [
              if (turno.idConsulta != null)
                BioButton(
                  label: 'Chat',
                  icon: Icons.chat_bubble_outline,
                  intent: UiIntent.secondary,
                  variant: BioButtonVariant.soft,
                  size: BioButtonSize.sm,
                  onPressed: () => _abrirChat(turno.idConsulta!,
                      turno.paciente?.nombreCompleto ?? 'Paciente'),
                ),
              BioButton.outlinePrimary(
                label: 'Historia clínica',
                icon: Icons.medical_services_outlined,
                size: BioButtonSize.sm,
                onPressed: () => _verHistoriaClinica(turno.idPersona,
                    parent: 'TURNO', parentId: turno.id),
              ),
            ],
          ),
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
            style: small ? BioTypography.caption : BioTypography.bodySm,
          ),
        ),
      ],
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

  void _abrirChat(int consultaId, String nombrePaciente) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => ChatConsultaScreen(
          consultaId: consultaId,
          authToken: widget.authToken,
          userId: widget.userId ?? '0',
          userName: null,
          titulo: 'Chat con $nombrePaciente',
        ),
      ),
    );
  }
}
