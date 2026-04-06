// lib/screens/patient_timeline_screen.dart
import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../models/timeline_event.dart';
import '../services/historia_clinica_service.dart';
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
  final HistoriaClinicaService _historiaClinicaService = HistoriaClinicaService();
  final ConsultaGuardarService _consultaGuardar = ConsultaGuardarService();
  HistoriaClinicaResponse? _historiaClinicaData;
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
      _historiaClinicaService.authToken = widget.authToken;
      _consultaGuardar.authToken = widget.authToken;
    }
    _cargarHistoriaClinica();
  }

  @override
  void dispose() {
    _chatController.dispose();
    _chatFocusNode.dispose();
    super.dispose();
  }

  Future<void> _cargarHistoriaClinica() async {
    setState(() {
      _isLoading = true;
      _errorMessage = '';
    });

    try {
      final data =
          await _historiaClinicaService.getHistoriaClinica(widget.personaId);
      setState(() {
        _historiaClinicaData = data;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _errorMessage = 'Error al cargar historia clínica: ${e.toString()}';
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
                        onPressed: _cargarHistoriaClinica,
                        child: const Text('Reintentar'),
                      ),
                    ],
                  ),
                )
              : _historiaClinicaData == null
                  ? const Center(child: Text('No hay datos disponibles'))
                  : Column(
                      children: [
                        Expanded(
                          child: SingleChildScrollView(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                // Header con datos del paciente
                                _buildPacienteHeader(_historiaClinicaData!.persona),
                                // Información médica
                                _buildInformacionMedica(
                                    _historiaClinicaData!.informacionMedica),
                                _buildSignosVitales(
                                    _historiaClinicaData!.signosVitales),
                                // Motivos de esta consulta (API + app paciente)
                                _buildMotivosConsulta(_historiaClinicaData!),
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

  String? _signoValorNodo(dynamic node, String key) {
    if (node is! Map) return null;
    final v = node[key];
    if (v == null) return null;
    final s = v.toString().trim();
    return s.isEmpty ? null : s;
  }

  Widget _buildSignosVitales(SignosVitalesClinica sv) {
    final u = sv.ultimosSv;
    final peso = _signoValorNodo(u?['peso'], 'value');
    final talla = _signoValorNodo(u?['talla'], 'value');
    final imc = _signoValorNodo(u?['imc'], 'value');
    String? tension;
    final ta = u?['ta'];
    if (ta is Map) {
      final sys = ta['sistolica']?.toString().trim() ?? '';
      final dia = ta['diastolica']?.toString().trim() ?? '';
      if (sys.isNotEmpty && dia.isNotEmpty) {
        tension = '$sys/$dia mmHg';
      }
    }
    final hayResumen =
        peso != null || talla != null || imc != null || tension != null;

    final titulo = sv.fechaTitulo.isNotEmpty
        ? 'SIGNOS VITALES ACTUALES (${sv.fechaTitulo})'
        : 'SIGNOS VITALES ACTUALES';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16.0),
      color: Colors.white,
      margin: const EdgeInsets.only(bottom: 8.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            titulo,
            style: AppTheme.h4Style.copyWith(
              color: AppTheme.primaryColor,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 12),
          if (!hayResumen)
            Text(
              'Sin datos',
              style: AppTheme.subTitleStyle,
            )
          else
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (peso != null) Text('Peso: $peso kg', style: AppTheme.subTitleStyle),
                if (talla != null) Text('Altura: $talla cm', style: AppTheme.subTitleStyle),
                if (imc != null) Text('IMC: $imc', style: AppTheme.subTitleStyle),
                if (tension != null)
                  Text('Tensión arterial: $tension', style: AppTheme.subTitleStyle),
              ],
            ),
          if (sv.datosSv.isNotEmpty && (sv.tieneMasSv || sv.datosSv.length > 1)) ...[
            const SizedBox(height: 12),
            ExpansionTile(
              tilePadding: EdgeInsets.zero,
              title: Text(
                'Historial (${sv.totalSv} registros)',
                style: AppTheme.h6Style,
              ),
              children: [
                for (final row in sv.datosSv.take(12))
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8.0),
                    child: Text(
                      _formatFilaSignosResumen(row),
                      style: AppTheme.subTitleStyle.copyWith(fontSize: 12),
                    ),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  String _formatFilaSignosResumen(Map<String, dynamic> row) {
    final fechaRaw = row['fecha_atencion'] ?? row['fecha'];
    final partes = <String>[];
    if (fechaRaw != null && '$fechaRaw'.trim().isNotEmpty) {
      partes.add('$fechaRaw');
    }
    final ta = row['ta1_sistolica'] != null && row['ta1_diastolica'] != null
        ? '${row['ta1_sistolica']}/${row['ta1_diastolica']}'
        : row['ta']?.toString();
    if (ta != null && ta.trim().isNotEmpty) partes.add('PA $ta');
    if (row['peso'] != null) partes.add('Peso ${row['peso']}');
    if (row['talla'] != null) partes.add('Talla ${row['talla']}');
    return partes.isEmpty ? '—' : partes.join(' · ');
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

  Widget _buildMotivosConsulta(HistoriaClinicaResponse hc) {
    final resumen = hc.informacionMedica.motivosConsulta;
    final msgs = hc.motivosConsultaPaciente.messages;
    final hayResumen = resumen != null && resumen.trim().isNotEmpty;

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
          if (hayResumen)
            Text(
              resumen,
              style: AppTheme.subTitleStyle,
            )
          else if (msgs.isNotEmpty)
            Text(
              'Aún no hay texto de motivo consolidado; revise los mensajes enviados por el paciente desde la app.',
              style: AppTheme.subTitleStyle,
            )
          else
            Text(
              'Sin motivos registrados para esta consulta.',
              style: AppTheme.subTitleStyle,
            ),
          if (msgs.isNotEmpty) ...[
            const SizedBox(height: 16),
            Text(
              'Mensajes del paciente (app)',
              style: AppTheme.h6Style.copyWith(
                color: AppTheme.primaryColor,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            ...msgs.map(_buildMotivoMensajeTile),
          ],
        ],
      ),
    );
  }

  Widget _buildMotivoMensajeTile(MotivoConsultaMensajeApi m) {
    final tipo = m.messageType.toLowerCase();
    final uri = Uri.tryParse(m.content);
    final esHttp = uri != null &&
        (uri.isScheme('http') || uri.isScheme('https'));

    Widget cuerpo;
    if (tipo == 'texto') {
      cuerpo = Text(
        m.content,
        style: AppTheme.subTitleStyle,
      );
    } else if (tipo == 'imagen' && esHttp) {
      cuerpo = Image.network(
        m.content,
        height: 140,
        fit: BoxFit.contain,
        errorBuilder: (_, __, ___) => SelectableText(m.content),
      );
    } else if (tipo == 'audio' && esHttp) {
      cuerpo = SelectableText(
        'Audio: ${m.content}',
        style: AppTheme.subTitleStyle,
      );
    } else {
      cuerpo = SelectableText(
        m.content,
        style: AppTheme.subTitleStyle,
      );
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: 12.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (m.createdAt.isNotEmpty)
            Text(
              m.createdAt,
              style: AppTheme.subTitleStyle.copyWith(
                fontSize: 11,
                color: Colors.grey.shade600,
              ),
            ),
          cuerpo,
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

