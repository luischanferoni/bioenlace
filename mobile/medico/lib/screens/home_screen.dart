// lib/screens/home_screen.dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import '../models/turno.dart';
import '../services/internados_service.dart';
import '../services/guardia_service.dart';
import '../services/pacientes_service.dart';
import 'patient_timeline_screen.dart';

/// Pantalla principal del médico. Contenido según encounter class:
/// AMB/VR/OBSENC/HH = turnos; IMP = pacientes internados; EMER = ingresos en guardia.
class HomeScreen extends StatefulWidget {
  final String userId;
  final String userName;
  final String? authToken;
  final String? rrhhId;

  const HomeScreen({
    Key? key,
    required this.userId,
    required this.userName,
    this.authToken,
    this.rrhhId,
  }) : super(key: key);

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final PacientesService _pacientesService = PacientesService();

  List<Turno> _turnos = [];
  List<InternadoItem> _internados = [];
  List<GuardiaItem> _guardia = [];
  bool _isLoading = true;
  String _errorMessage = '';
  DateTime _fechaSeleccionada = DateTime.now();

  String _encounterClass = 'AMB';
  // efector_id se conserva en prefs por otras pantallas (no se usa aquí).

  @override
  void initState() {
    super.initState();
    _pacientesService.userId = widget.userId;
    if (widget.authToken != null && widget.authToken!.isNotEmpty) {
      _pacientesService.authToken = widget.authToken;
    } else {
      _loadAuthToken();
    }
    _loadEncounterAndData();
  }

  Future<void> _loadAuthToken() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token');
      if (token != null && token.isNotEmpty) {
        setState(() {
          _pacientesService.authToken = token;
        });
      } else {
        setState(() {
          _pacientesService.userId = widget.userId;
        });
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

  Future<void> _cargarListadoPacientes() async {
    setState(() {
      _isLoading = true;
      _errorMessage = '';
    });
    try {
      final fechaStr = DateFormat('yyyy-MM-dd').format(_fechaSeleccionada);
      final res = await _pacientesService.getListado(fecha: fechaStr);
      setState(() {
        _turnos = [];
        _internados = [];
        _guardia = [];
        if (res.kind == 'turnos') {
          _turnos = res.data
              .map((e) => Turno.fromJson(e as Map<String, dynamic>))
              .toList();
        } else if (res.kind == 'internados') {
          _internados = res.data
              .map((e) => InternadoItem.fromJson(e as Map<String, dynamic>))
              .toList();
        } else if (res.kind == 'guardias') {
          _guardia = res.data
              .map((e) => GuardiaItem.fromJson(e as Map<String, dynamic>))
              .toList();
        } else {
          _errorMessage = 'Respuesta inválida del servidor (kind=${res.kind})';
        }
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _errorMessage = 'Error al cargar pacientes: ${e.toString()}';
        _isLoading = false;
      });
    }
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
    final diferencia = fecha.difference(DateTime(hoy.year, hoy.month, hoy.day)).inDays;

    if (diferencia == 0) {
      return 'Hoy';
    } else if (diferencia == 1) {
      return 'Mañana';
    } else if (diferencia == -1) {
      return 'Ayer';
    } else {
      return DateFormat('EEEE, d \'de\' MMMM', 'es').format(fecha);
    }
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

  /// Turnos pendientes (PENDIENTE), sin incluir el siguiente.
  List<Turno> _getPendientes(Turno? siguienteTurno) {
    final siguienteId = siguienteTurno?.id;
    return _turnos
        .where((t) =>
            t.estado == 'PENDIENTE' &&
            t.id != 999999 &&
            (siguienteId == null || t.id != siguienteId))
        .toList();
  }

  /// Turnos ya cargados: ATENDIDO o EN_ATENCION.
  List<Turno> _getConsultasCargadas() {
    return _turnos
        .where((t) =>
            (t.estado == 'ATENDIDO' || t.estado == 'EN_ATENCION') &&
            t.id != 999999)
        .toList();
  }

  VoidCallback get _onRetry {
    return _cargarListadoPacientes;
  }

  @override
  Widget build(BuildContext context) {
    final esHoy = _fechaSeleccionada.year == DateTime.now().year &&
        _fechaSeleccionada.month == DateTime.now().month &&
        _fechaSeleccionada.day == DateTime.now().day;
    final siguienteTurno = esHoy ? _obtenerSiguienteTurno() : null;
    final isTurnosView = _encounterClass != 'IMP' && _encounterClass != 'EMER';

    return Container(
      color: AppTheme.backgroundColor,
      child: Column(
        children: [
          SafeArea(
            bottom: false,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 16.0),
              color: Colors.white,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Text(
                      _encounterClass == 'IMP'
                          ? 'Pacientes internados'
                          : _encounterClass == 'EMER'
                              ? 'Ingresos en guardia'
                              : _formatearFechaAmigable(_fechaSeleccionada),
                      style: AppTheme.h2Style.copyWith(color: AppTheme.dark, fontSize: 20),
                    ),
                  ),
                  if (isTurnosView)
                    Row(
                      children: [
                        ElevatedButton.icon(
                          onPressed: () => _cambiarFecha(-1),
                          icon: const Icon(Icons.chevron_left, size: 16),
                          label: const Text('Anterior'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.white,
                            foregroundColor: AppTheme.secondaryColor,
                            side: const BorderSide(color: AppTheme.secondaryColor),
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                          ),
                        ),
                        const SizedBox(width: 8),
                        ElevatedButton(
                          onPressed: _irAHoy,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.white,
                            foregroundColor: AppTheme.secondaryColor,
                            side: const BorderSide(color: AppTheme.secondaryColor),
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                          ),
                          child: const Text('Hoy'),
                        ),
                        const SizedBox(width: 8),
                        ElevatedButton.icon(
                          onPressed: () => _cambiarFecha(1),
                          icon: const Icon(Icons.chevron_right, size: 16),
                          label: const Text('Siguiente'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.white,
                            foregroundColor: AppTheme.secondaryColor,
                            side: const BorderSide(color: AppTheme.secondaryColor),
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                          ),
                        ),
                      ],
                    )
                  else
                    IconButton(
                      icon: const Icon(Icons.refresh),
                      onPressed: _isLoading ? null : _cargarListadoPacientes,
                    ),
                ],
              ),
            ),
          ),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _errorMessage.isNotEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.error_outline, size: 48, color: AppTheme.dangerColor),
                            const SizedBox(height: 16),
                            Text(_errorMessage, style: AppTheme.subTitleStyle, textAlign: TextAlign.center),
                            const SizedBox(height: 16),
                            ElevatedButton(onPressed: _onRetry, child: const Text('Reintentar')),
                          ],
                        ),
                      )
                    : _encounterClass == 'IMP'
                        ? _buildInternadosList()
                        : _encounterClass == 'EMER'
                            ? _buildGuardiaList()
                            : _turnos.isEmpty
                                ? Center(
                                    child: Column(
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      children: [
                                        Icon(Icons.info_outline, size: 48, color: AppTheme.infoColor),
                                        const SizedBox(height: 16),
                                        Text('No hay turnos programados para esta fecha.', style: AppTheme.subTitleStyle),
                                      ],
                                    ),
                                  )
                                : _buildTurnosPorEstado(siguienteTurno),
          ),
        ],
      ),
    );
  }

  Widget _buildTurnosPorEstado(Turno? siguienteTurno) {
    final pendientes = _getPendientes(siguienteTurno);
    final cargadas = _getConsultasCargadas();
    const maxCardWidth = 420.0;

    return ListView(
      padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 16.0),
      children: [
        // Sección: Siguiente turno
        if (siguienteTurno != null) ...[
          _buildSeccionSubtitulo('Siguiente turno'),
          const SizedBox(height: 8),
          Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: maxCardWidth),
              child: _buildSiguienteTurnoCard(siguienteTurno),
            ),
          ),
          const SizedBox(height: 24),
        ],
        // Sección: Pendientes
        _buildSeccionSubtitulo('Pendientes'),
        const SizedBox(height: 8),
        if (pendientes.isEmpty)
          Center(
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 12.0),
              child: Text(
                'No hay turnos pendientes.',
                style: AppTheme.subTitleStyle,
              ),
            ),
          )
        else
          ...pendientes.map((t) => Padding(
                padding: const EdgeInsets.only(bottom: 12.0),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: maxCardWidth),
                    child: _buildTurnoCard(t),
                  ),
                ),
              )),
        const SizedBox(height: 24),
        // Sección: Consultas cargadas
        _buildSeccionSubtitulo('Consultas cargadas'),
        const SizedBox(height: 8),
        if (cargadas.isEmpty)
          Center(
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 12.0),
              child: Text(
                'No hay consultas cargadas.',
                style: AppTheme.subTitleStyle,
              ),
            ),
          )
        else
          ...cargadas.map((t) => Padding(
                padding: const EdgeInsets.only(bottom: 12.0),
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

  Widget _buildSeccionSubtitulo(String texto) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4.0),
      child: Text(
        texto,
        style: AppTheme.h5Style.copyWith(
          color: AppTheme.primaryColor,
          fontWeight: FontWeight.bold,
        ),
      ),
    );
  }

  Widget _buildInternadosList() {
    if (_internados.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.bed, size: 48, color: AppTheme.infoColor),
            const SizedBox(height: 16),
            Text('No hay pacientes internados.', style: AppTheme.subTitleStyle),
          ],
        ),
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.all(16.0),
      itemCount: _internados.length,
      itemBuilder: (context, index) {
        final i = _internados[index];
        return Card(
          elevation: 0,
          child: ListTile(
            leading: Icon(Icons.person, color: AppTheme.primaryColor),
            title: Text(i.nombreCompleto, style: AppTheme.h5Style),
            subtitle: Text(
              [if (i.cama != null) 'Cama ${i.cama}', if (i.sala != null) 'Sala ${i.sala}', if (i.documento != null) 'Doc. ${i.documento}']
                  .where((e) => e.isNotEmpty)
                  .join(' · '),
              style: AppTheme.subTitleStyle,
            ),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => _onTapSinTimeline(context),
          ),
        );
      },
    );
  }

  Widget _buildGuardiaList() {
    if (_guardia.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.emergency, size: 48, color: AppTheme.infoColor),
            const SizedBox(height: 16),
            Text('No hay ingresos en guardia.', style: AppTheme.subTitleStyle),
          ],
        ),
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.all(16.0),
      itemCount: _guardia.length,
      itemBuilder: (context, index) {
        final g = _guardia[index];
        return Card(
          elevation: 0,
          child: ListTile(
            leading: Icon(Icons.person, color: AppTheme.primaryColor),
            title: Text(g.nombreCompleto, style: AppTheme.h5Style),
            subtitle: Text(
              [if (g.fecha != null) g.fecha!, if (g.hora != null) g.hora!, if (g.documento != null) 'Doc. ${g.documento}']
                  .where((e) => e.isNotEmpty)
                  .join(' · '),
              style: AppTheme.subTitleStyle,
            ),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => _onTapSinTimeline(context),
          ),
        );
      },
    );
  }

  Widget _buildSiguienteTurnoCard(Turno turno) {
    return Card(
      elevation: 0,
      color: AppTheme.primaryColor.withOpacity(0.1),
      child: InkWell(
        onTap: () => _verHistoriaClinica(turno.idPersona),
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(
                    Icons.calendar_today,
                    color: AppTheme.primaryColor,
                    size: 24,
                  ),
                  const SizedBox(width: 8),
                  Text(
                    'Siguiente Turno',
                    style: AppTheme.h3Style.copyWith(
                      color: AppTheme.primaryColor,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Text(
                'Paciente: ${turno.paciente?.nombreCompleto ?? "Sin paciente"}',
                style: AppTheme.h5Style,
              ),
              const SizedBox(height: 4),
              Text(
                'Fecha: ${turno.fecha}',
                style: AppTheme.subTitleStyle,
              ),
              const SizedBox(height: 4),
              Text(
                'Hora: ${turno.hora}',
                style: AppTheme.subTitleStyle,
              ),
              const SizedBox(height: 8),
              Text(
                'Haz clic para ver la historia clínica',
                style: AppTheme.subTitleStyle.copyWith(
                  fontSize: 11,
                  fontStyle: FontStyle.italic,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildTurnoCard(Turno turno) {
    return Card(
      elevation: 0,
      child: InkWell(
        onTap: () => _verHistoriaClinica(turno.idPersona),
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(
                    Icons.person,
                    color: AppTheme.primaryColor,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      turno.paciente?.nombreCompleto ?? 'Sin paciente',
                      style: AppTheme.h3Style,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Icon(Icons.access_time, size: 16, color: AppTheme.subTitleTextColor),
                  const SizedBox(width: 4),
                  Text(
                    'Hora: ${turno.hora}',
                    style: AppTheme.subTitleStyle,
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Row(
                children: [
                  Icon(Icons.local_hospital, size: 16, color: AppTheme.subTitleTextColor),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      'Servicio: ${turno.servicio ?? "Sin servicio"}',
                      style: AppTheme.subTitleStyle,
                    ),
                  ),
                ],
              ),
              if (turno.observaciones != null && turno.observaciones!.isNotEmpty) ...[
                const SizedBox(height: 4),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.note, size: 16, color: AppTheme.subTitleTextColor),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        'Observaciones: ${turno.observaciones}',
                        style: AppTheme.subTitleStyle.copyWith(fontSize: 11),
                      ),
                    ),
                  ],
                ),
              ],
              const Spacer(),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Chip(
                    label: Text(
                      turno.estadoLabel,
                      style: const TextStyle(fontSize: 11),
                    ),
                    backgroundColor: _getEstadoColor(turno.estado).withOpacity(0.2),
                    labelStyle: TextStyle(color: _getEstadoColor(turno.estado)),
                  ),
                  TextButton.icon(
                    icon: const Icon(Icons.medical_services, size: 18),
                    label: const Text('Historia clínica'),
                    onPressed: () => _verHistoriaClinica(turno.idPersona),
                    style: TextButton.styleFrom(
                      foregroundColor: AppTheme.primaryColor,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Color _getEstadoColor(String estado) {
    switch (estado) {
      case 'PENDIENTE':
        return AppTheme.warningColor;
      case 'ATENDIDO':
        return AppTheme.successColor;
      case 'CANCELADO':
        return AppTheme.dangerColor;
      case 'EN_ATENCION':
        return AppTheme.infoColor;
      default:
        return AppTheme.secondaryColor;
    }
  }

  static void _onTapSinTimeline(BuildContext context) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Historia clínica no disponible temporalmente.'),
      ),
    );
  }

  void _verHistoriaClinica(int personaId) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => PatientTimelineScreen(
          personaId: personaId,
          authToken: widget.authToken,
        ),
      ),
    );
  }
}
