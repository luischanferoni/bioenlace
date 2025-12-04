// lib/screens/home_screen.dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import '../models/turno.dart';
import '../services/turnos_service.dart';
import 'patient_timeline_screen.dart';

/// Pantalla principal del médico - Listado de turnos (igual que la web)
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
  final TurnosService _turnosService = TurnosService();
  List<Turno> _turnos = [];
  bool _isLoading = true;
  String _errorMessage = '';
  DateTime _fechaSeleccionada = DateTime.now();

  @override
  void initState() {
    super.initState();
    // Configurar userId para desarrollo/simulación
    _turnosService.userId = widget.userId;
    
    if (widget.authToken != null && widget.authToken!.isNotEmpty) {
      _turnosService.authToken = widget.authToken;
    } else {
      // Si no hay token, intentar obtenerlo de SharedPreferences
      _loadAuthToken();
    }
    _cargarTurnos();
  }

  Future<void> _loadAuthToken() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token');
      if (token != null && token.isNotEmpty) {
        setState(() {
          _turnosService.authToken = token;
        });
      } else {
        // Si no hay token, usar userId para desarrollo/simulación
        setState(() {
          _turnosService.userId = widget.userId;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Error al cargar token de autenticación: ${e.toString()}';
        _isLoading = false;
      });
    }
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
    // Excluir el turno simulado (id=999999) del cálculo del siguiente turno
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

  @override
  Widget build(BuildContext context) {
    final esHoy = _fechaSeleccionada.year == DateTime.now().year &&
        _fechaSeleccionada.month == DateTime.now().month &&
        _fechaSeleccionada.day == DateTime.now().day;
    final siguienteTurno = esHoy ? _obtenerSiguienteTurno() : null;

    return Container(
      color: AppTheme.backgroundColor,
      child: Column(
        children: [
          // Header con fecha y navegación (igual que la web)
          SafeArea(
            bottom: false,
            child: Container(
              padding: const EdgeInsets.symmetric(
                horizontal: 16.0,
                vertical: 16.0,
              ),
              color: Colors.white,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Text(
                      _formatearFechaAmigable(_fechaSeleccionada),
                      style: AppTheme.h2Style.copyWith(
                        color: AppTheme.dark,
                        fontSize: 20,
                      ),
                    ),
                  ),
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
                  ),
                ],
              ),
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
                              // Card de siguiente turno (solo si es hoy) - igual que la web
                              if (siguienteTurno != null)
                                _buildSiguienteTurnoCard(siguienteTurno),
                              if (siguienteTurno != null)
                                const SizedBox(height: 16),
                              // Lista de turnos en grid (igual que la web: col-md-6 col-lg-4)
                              // En mobile mostramos 1 columna, en tablet 2 columnas
                              LayoutBuilder(
                                builder: (context, constraints) {
                                  final crossAxisCount = constraints.maxWidth > 600 ? 2 : 1;
                                  // Excluir el siguiente turno de la lista si existe (para evitar duplicados)
                                  final siguienteId = siguienteTurno?.id;
                                  final turnosParaLista = siguienteId != null
                                      ? _turnos.where((turno) => turno.id != siguienteId).toList()
                                      : _turnos;
                                  return GridView.builder(
                                    shrinkWrap: true,
                                    physics: const NeverScrollableScrollPhysics(),
                                    gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                                      crossAxisCount: crossAxisCount,
                                      childAspectRatio: crossAxisCount == 1 ? 1.2 : 1.1,
                                      crossAxisSpacing: 16,
                                      mainAxisSpacing: 12,
                                    ),
                                    itemCount: turnosParaLista.length,
                                    itemBuilder: (context, index) {
                                      return _buildTurnoCard(turnosParaLista[index]);
                                    },
                                  );
                                },
                              ),
                            ],
                          ),
          ),
        ],
      ),
    );
  }

  Widget _buildSiguienteTurnoCard(Turno turno) {
    return Card(
      elevation: 4,
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
      elevation: 2,
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
                  ElevatedButton(
                    onPressed: () => _verHistoriaClinica(turno.idPersona),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.primaryColor,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    ),
                    child: const Text('Cargar consulta'),
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
}
