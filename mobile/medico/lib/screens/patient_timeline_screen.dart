// lib/screens/patient_timeline_screen.dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:shared/shared.dart';

import '../models/timeline_event.dart';
import '../services/timeline_service.dart';

class PatientTimelineScreen extends StatefulWidget {
  final int personaId;
  final String? authToken;

  const PatientTimelineScreen({
    Key? key,
    required this.personaId,
    this.authToken,
  }) : super(key: key);

  @override
  State<PatientTimelineScreen> createState() => _PatientTimelineScreenState();
}

class _PatientTimelineScreenState extends State<PatientTimelineScreen> {
  final TimelineService _timelineService = TimelineService();
  TimelineResponse? _timelineData;
  bool _isLoading = true;
  String _errorMessage = '';

  @override
  void initState() {
    super.initState();
    if (widget.authToken != null) {
      _timelineService.authToken = widget.authToken;
    }
    _cargarTimeline();
  }

  Future<void> _cargarTimeline() async {
    setState(() {
      _isLoading = true;
      _errorMessage = '';
    });

    try {
      final data = await _timelineService.getTimeline(widget.personaId);
      setState(() {
        _timelineData = data;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _errorMessage = 'Error al cargar timeline: ${e.toString()}';
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          'Historia Clínica',
          style: AppTheme.h2Style.copyWith(color: Colors.white),
        ),
        backgroundColor: AppTheme.primaryColor,
        elevation: 0,
      ),
      body: _isLoading
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
                        onPressed: _cargarTimeline,
                        child: const Text('Reintentar'),
                      ),
                    ],
                  ),
                )
              : _timelineData == null
                  ? const Center(child: Text('No hay datos disponibles'))
                  : SingleChildScrollView(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Header con datos del paciente
                          _buildPacienteHeader(_timelineData!.persona),
                          // Información médica
                          _buildInformacionMedica(_timelineData!.informacionMedica),
                          // Timeline
                          _buildTimeline(_timelineData!.timeline),
                        ],
                      ),
                    ),
    );
  }

  Widget _buildPacienteHeader(PersonaData persona) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16.0),
      color: Colors.white,
      margin: const EdgeInsets.only(bottom: 8.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Nombre Completo:',
            style: AppTheme.h5Style.copyWith(fontWeight: FontWeight.bold),
          ),
          Text(
            persona.nombreCompleto,
            style: AppTheme.h4Style,
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'F. Nacimiento:',
                      style: AppTheme.subTitleStyle.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    Text(
                      persona.fechaNacimiento != null
                          ? DateFormat('dd MMMM yyyy', 'es').format(
                              DateTime.parse(persona.fechaNacimiento!))
                          : 'No disponible',
                      style: AppTheme.subTitleStyle,
                    ),
                    if (persona.edad != null)
                      Text(
                        '(${persona.edad} años)',
                        style: AppTheme.subTitleStyle,
                      ),
                  ],
                ),
              ),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Nro. Documento:',
                      style: AppTheme.subTitleStyle.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    Text(
                      persona.documento ?? 'No disponible',
                      style: AppTheme.subTitleStyle,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildInformacionMedica(InformacionMedica info) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16.0),
      color: Colors.white,
      margin: const EdgeInsets.only(bottom: 8.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'INFORMACIÓN MÉDICA',
            style: AppTheme.h4Style.copyWith(
              color: AppTheme.primaryColor,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 16),
          // Diagnósticos recientes
          _buildSeccionInfo(
            'DIAGNÓSTICOS RECIENTES',
            info.condicionesActivas
                .map((c) => c.termino ?? 'Sin término')
                .toList(),
          ),
          const SizedBox(height: 16),
          // Condiciones activas
          _buildSeccionInfo(
            'CONDICIONES ACTIVAS',
            info.condicionesActivas
                .map((c) => c.termino ?? 'Sin término')
                .toList(),
          ),
          const SizedBox(height: 16),
          // Condiciones crónicas
          _buildSeccionInfo(
            'CONDICIONES CRÓNICAS',
            info.condicionesCronicas
                .map((c) => c.termino ?? 'Sin término')
                .toList(),
          ),
          const SizedBox(height: 16),
          // Hallazgos (alergias)
          _buildSeccionInfo(
            'HALLAZGOS',
            info.hallazgos.map((h) => h.termino ?? 'Sin término').toList(),
          ),
        ],
      ),
    );
  }

  Widget _buildSeccionInfo(String titulo, List<String> items) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          titulo,
          style: AppTheme.h6Style.copyWith(
            decoration: TextDecoration.underline,
          ),
        ),
        const SizedBox(height: 8),
        if (items.isEmpty)
          Text(
            'Sin datos',
            style: AppTheme.subTitleStyle,
          )
        else
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: items.map((item) {
              return Chip(
                label: Text(
                  item.toUpperCase(),
                  style: const TextStyle(fontSize: 11),
                ),
                backgroundColor: AppTheme.infoColor.withOpacity(0.2),
                labelStyle: TextStyle(color: AppTheme.infoColor),
              );
            }).toList(),
          ),
      ],
    );
  }

  Widget _buildTimeline(List<TimelineEvent> eventos) {
    if (eventos.isEmpty) {
      return Container(
        padding: const EdgeInsets.all(32.0),
        child: Center(
          child: Text(
            'No hay eventos en el timeline',
            style: AppTheme.subTitleStyle,
          ),
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.all(16.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'TIMELINE',
            style: AppTheme.h4Style.copyWith(
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 16),
          ...eventos.map((evento) => Padding(
                padding: const EdgeInsets.only(bottom: 16.0),
                child: _buildEventoCard(evento),
              )),
        ],
      ),
    );
  }

  Widget _buildEventoCard(TimelineEvent evento) {
    final fecha = evento.fechaDateTime;
    final fechaFormateada = fecha != null
        ? DateFormat('dd/MM/yyyy HH:mm', 'es').format(fecha)
        : evento.fecha;

    return Card(
      elevation: 2,
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8.0),
                  decoration: BoxDecoration(
                    color: _getTipoColor(evento.tipo).withOpacity(0.2),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(
                    _getTipoIcono(evento.tipo),
                    color: _getTipoColor(evento.tipo),
                    size: 24,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        evento.tipo,
                        style: AppTheme.h4Style.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      Text(
                        fechaFormateada,
                        style: AppTheme.subTitleStyle,
                      ),
                    ],
                  ),
                ),
              ],
            ),
            if (evento.servicio != null) ...[
              const SizedBox(height: 8),
              Row(
                children: [
                  Icon(Icons.local_hospital,
                      size: 16, color: AppTheme.subTitleTextColor),
                  const SizedBox(width: 4),
                  Text(
                    'Servicio: ${evento.servicio}',
                    style: AppTheme.subTitleStyle,
                  ),
                ],
              ),
            ],
            if (evento.profesional != null) ...[
              const SizedBox(height: 4),
              Row(
                children: [
                  Icon(Icons.person,
                      size: 16, color: AppTheme.subTitleTextColor),
                  const SizedBox(width: 4),
                  Text(
                    'Profesional: ${evento.profesional}',
                    style: AppTheme.subTitleStyle,
                  ),
                ],
              ),
            ],
            if (evento.efector != null) ...[
              const SizedBox(height: 4),
              Row(
                children: [
                  Icon(Icons.business,
                      size: 16, color: AppTheme.subTitleTextColor),
                  const SizedBox(width: 4),
                  Text(
                    'Efector: ${evento.efector}',
                    style: AppTheme.subTitleStyle,
                  ),
                ],
              ),
            ],
            if (evento.resumen != null && evento.resumen!.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                evento.resumen!,
                style: AppTheme.subTitleStyle,
              ),
            ],
          ],
        ),
      ),
    );
  }

  IconData _getTipoIcono(String tipo) {
    switch (tipo) {
      case 'Turno':
        return Icons.calendar_today;
      case 'Consulta':
        return Icons.medical_services;
      case 'Internacion':
        return Icons.local_hospital;
      case 'Guardia':
        return Icons.emergency;
      case 'DocumentoExterno':
        return Icons.description;
      case 'EncuestaParchesMamarios':
        return Icons.assignment;
      case 'EstudiosImagenes':
        return Icons.image;
      case 'EstudiosLab':
        return Icons.science;
      case 'Forms':
        return Icons.article;
      default:
        return Icons.circle;
    }
  }

  Color _getTipoColor(String tipo) {
    switch (tipo) {
      case 'Turno':
        return AppTheme.primaryColor;
      case 'Consulta':
        return AppTheme.infoColor;
      case 'Internacion':
        return AppTheme.warningColor;
      case 'Guardia':
        return AppTheme.dangerColor;
      case 'DocumentoExterno':
        return AppTheme.secondaryColor;
      default:
        return AppTheme.dark;
    }
  }
}

