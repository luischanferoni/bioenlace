// lib/screens/patient_timeline_screen.dart
import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../models/timeline_event.dart';
import '../services/timeline_service.dart';
import '../services/consulta_guardar_service.dart';

class PatientTimelineScreen extends StatefulWidget {
  final int personaId;
  final String? authToken;
  /// true = solo ver historia clínica (sin formulario); false = barra para escribir notas
  final bool soloVer;
  /// Contexto de consulta (ej. `CIRUGIA`, `TURNO`) alineado con web / `validarPermisoAtencion`.
  final String? consultParent;
  final int? consultParentId;

  const PatientTimelineScreen({
    Key? key,
    required this.personaId,
    this.authToken,
    this.soloVer = true,
    this.consultParent,
    this.consultParentId,
  }) : super(key: key);

  @override
  State<PatientTimelineScreen> createState() => _PatientTimelineScreenState();
}

class _PatientTimelineScreenState extends State<PatientTimelineScreen> {
  final TimelineService _timelineService = TimelineService();
  final ConsultaGuardarService _consultaGuardar = ConsultaGuardarService();
  TimelineResponse? _timelineData;
  bool _isLoading = true;
  String _errorMessage = '';
  bool _guardandoConsulta = false;

  final TextEditingController _chatController = TextEditingController();
  final FocusNode _chatFocusNode = FocusNode();

  bool get _mostrarBarraConsulta =>
      !widget.soloVer ||
      (widget.consultParent != null &&
          widget.consultParent!.isNotEmpty &&
          widget.consultParentId != null);

  @override
  void initState() {
    super.initState();
    if (widget.authToken != null) {
      _timelineService.authToken = widget.authToken;
      _consultaGuardar.authToken = widget.authToken;
    }
    _cargarTimeline();
  }

  @override
  void dispose() {
    _chatController.dispose();
    _chatFocusNode.dispose();
    super.dispose();
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
      appBar: AppBar(title: const Text('Historia Clínica')),
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
                  : Column(
                      children: [
                        Expanded(
                          child: SingleChildScrollView(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                // Header con datos del paciente
                                _buildPacienteHeader(_timelineData!.persona),
                                // Información médica
                                _buildInformacionMedica(_timelineData!.informacionMedica),
                                // Motivos de esta consulta
                                _buildMotivosConsulta(_timelineData!.timeline),
                              ],
                            ),
                          ),
                        ),
                        if (_mostrarBarraConsulta) _buildChatInputBar(),
                      ],
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
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  persona.nombreCompleto,
                  style: AppTheme.h4Style,
                ),
              ),
              if (persona.edad != null)
                Text(
                  '${persona.edad} años',
                  style: AppTheme.h4Style,
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
            'CONDICIÓN ACTUAL',
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

  Widget _buildMotivosConsulta(List<TimelineEvent> eventos) {
    TimelineEvent? ultimoTurnoConMotivo;
    for (var i = eventos.length - 1; i >= 0; i--) {
      final e = eventos[i];
      if (e.tipo == 'Turno' && e.resumen != null && e.resumen!.trim().isNotEmpty) {
        ultimoTurnoConMotivo = e;
        break;
      }
    }

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16.0),
      color: Colors.white,
      margin: const EdgeInsets.only(bottom: 8.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'MOTIVOS DE ESTA CONSULTA',
            style: AppTheme.h4Style.copyWith(
              color: AppTheme.primaryColor,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 12),
          if (ultimoTurnoConMotivo == null)
            Text(
              'Sin motivos registrados para esta consulta.',
              style: AppTheme.subTitleStyle,
            )
          else
            Text(
              ultimoTurnoConMotivo.resumen!,
              style: AppTheme.subTitleStyle,
            ),
        ],
      ),
    );
  }

  Future<void> _enviarConsulta() async {
    final text = _chatController.text.trim();
    if (text.isEmpty) return;

    if (widget.consultParent != null &&
        widget.consultParent!.isNotEmpty &&
        widget.consultParentId != null) {
      setState(() => _guardandoConsulta = true);
      try {
        await _consultaGuardar.guardar(
          idPersona: widget.personaId,
          parent: widget.consultParent!,
          parentId: widget.consultParentId!,
          texto: text,
        );
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Consulta guardada')),
        );
        _chatController.clear();
        _chatFocusNode.unfocus();
      } catch (e) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      } finally {
        if (mounted) setState(() => _guardandoConsulta = false);
      }
      return;
    }

    if (!widget.soloVer) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Defina contexto de consulta (parent) o use el flujo web para analizar con IA.'),
        ),
      );
      return;
    }
  }

  Widget _buildChatInputBar() {
    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12.0, vertical: 8.0),
        color: Colors.white,
        child: Row(
          children: [
            Expanded(
              child: TextField(
                controller: _chatController,
                focusNode: _chatFocusNode,
                minLines: 4,
                maxLines: 8,
                decoration: const InputDecoration(
                  hintText: 'Escribir consulta',
                  border: OutlineInputBorder(),
                  isDense: true,
                  contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                ),
              ),
            ),
            const SizedBox(width: 8),
            IconButton(
              icon: const Icon(Icons.mic),
              color: AppTheme.primaryColor,
              onPressed: () {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Envío de audios en desarrollo'),
                  ),
                );
              },
            ),
            IconButton(
              icon: _guardandoConsulta
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.send),
              color: AppTheme.primaryColor,
              onPressed: _guardandoConsulta ? null : _enviarConsulta,
            ),
          ],
        ),
      ),
    );
  }
}

