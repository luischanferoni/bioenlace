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
  final String? rrhhId;
  final String? userId;

  const AppointmentsScreen({
    Key? key,
    this.authToken,
    this.rrhhId,
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
        rrhhId: widget.rrhhId,
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
    return _turnos.firstWhere(
      (turno) {
        final fechaHora = turno.fechaHora;
        return fechaHora != null && fechaHora.isAfter(ahora);
      },
      orElse: () => _turnos.first,
    );
  }

  @override
  Widget build(BuildContext context) {
    final esHoy = _fechaSeleccionada.year == DateTime.now().year &&
        _fechaSeleccionada.month == DateTime.now().month &&
        _fechaSeleccionada.day == DateTime.now().day;
    final siguienteTurno = esHoy ? _obtenerSiguienteTurno() : null;

    return Scaffold(
      appBar: AppBar(
        title: Text(
          'Turnos',
          style: AppTheme.h2Style.copyWith(color: Colors.white),
        ),
        backgroundColor: AppTheme.primaryColor,
        elevation: 0,
      ),
      body: Container(
        color: AppTheme.backgroundColor,
        child: Column(
          children: [
            // Header con fecha y navegación
            Container(
              padding: const EdgeInsets.all(16.0),
              color: Colors.white,
              child: Column(
                children: [
                  Text(
                    _formatearFechaAmigable(_fechaSeleccionada),
                    style: AppTheme.h2Style.copyWith(
                      color: AppTheme.dark,
                      fontSize: 20,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      ElevatedButton.icon(
                        onPressed: () => _cambiarFecha(-1),
                        icon: const Icon(Icons.chevron_left),
                        label: const Text('Anterior'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.white,
                          foregroundColor: AppTheme.secondaryColor,
                          side: const BorderSide(color: AppTheme.secondaryColor),
                        ),
                      ),
                      const SizedBox(width: 8),
                      ElevatedButton(
                        onPressed: _irAHoy,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.white,
                          foregroundColor: AppTheme.secondaryColor,
                          side: const BorderSide(color: AppTheme.secondaryColor),
                        ),
                        child: const Text('Hoy'),
                      ),
                      const SizedBox(width: 8),
                      ElevatedButton.icon(
                        onPressed: () => _cambiarFecha(1),
                        icon: const Icon(Icons.chevron_right),
                        label: const Text('Siguiente'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.white,
                          foregroundColor: AppTheme.secondaryColor,
                          side: const BorderSide(color: AppTheme.secondaryColor),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            // Contenido
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator())
                  : _errorMessage.isNotEmpty
                      ? Center(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(
                                Icons.error_outline,
                                size: 48,
                                color: AppTheme.dangerColor,
                              ),
                              const SizedBox(height: 16),
                              Text(
                                _errorMessage,
                                style: AppTheme.subTitleStyle,
                                textAlign: TextAlign.center,
                              ),
                              const SizedBox(height: 16),
                              ElevatedButton(
                                onPressed: _cargarTurnos,
                                child: const Text('Reintentar'),
                              ),
                            ],
                          ),
                        )
                      : _turnos.isEmpty
                          ? Center(
                              child: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(
                                    Icons.info_outline,
                                    size: 48,
                                    color: AppTheme.infoColor,
                                  ),
                                  const SizedBox(height: 16),
                                  Text(
                                    'No hay turnos programados para esta fecha.',
                                    style: AppTheme.subTitleStyle,
                                  ),
                                ],
                              ),
                            )
                          : ListView(
                              padding: const EdgeInsets.all(16.0),
                              children: [
                                // Card de siguiente turno (solo si es hoy)
                                if (siguienteTurno != null)
                                  _buildSiguienteTurnoCard(siguienteTurno),
                                if (siguienteTurno != null)
                                  const SizedBox(height: 16),
                                // Lista de turnos
                                ..._turnos.map((turno) => Padding(
                                      padding: const EdgeInsets.only(bottom: 12.0),
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

  Widget _buildSiguienteTurnoCard(Turno turno) {
    return Card(
      elevation: 4,
      color: AppTheme.primaryColor.withOpacity(0.1),
      child: InkWell(
        onTap: () => _verHistoriaClinica(turno.idPersona),
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
      elevation: 2,
      child: InkWell(
        onTap: () => _verHistoriaClinica(turno.idPersona),
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
              const SizedBox(height: 12),
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
                  if (turno.idConsulta != null)
                    Padding(
                      padding: const EdgeInsets.only(right: 8.0),
                      child: ElevatedButton.icon(
                        icon: const Icon(Icons.chat, size: 18),
                        onPressed: () => _abrirChat(turno.idConsulta!, turno.paciente?.nombreCompleto ?? 'Paciente'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppTheme.secondaryColor,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        ),
                        label: const Text('Chat'),
                      ),
                    ),
                  ElevatedButton(
                    onPressed: () => _verHistoriaClinica(turno.idPersona),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.primaryColor,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    ),
                    child: const Text('Ver Historia Clínica'),
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

