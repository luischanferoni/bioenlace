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
  final HistoriaClinicaService _historiaClinicaService =
      HistoriaClinicaService();
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
      final int? turnoId = widget.consultParent == 'TURNO'
          ? widget.consultParentId
          : null;
      final data = await _historiaClinicaService.getHistoriaClinica(
        widget.personaId,
        turnoId: turnoId,
      );
      if (!mounted) return;
      setState(() {
        _historiaClinicaData = data;
        _isLoading = false;
      });
    } on HistoriaClinicaVentanaException catch (e) {
      if (!mounted) return;
      setState(() {
        _errorMessage = e.message;
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _errorMessage = 'Error al cargar historia clínica: ${e.toString()}';
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: const BioAppBar(title: 'Historia clínica'),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _errorMessage.isNotEmpty
              ? Center(
                  child: Padding(
                    padding: BioSpacing.pageAll,
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        BioAlert.danger(message: _errorMessage),
                        BioSpacing.gapH(BioSpacing.lg),
                        BioButton.primary(
                          label: 'Reintentar',
                          icon: Icons.refresh,
                          onPressed: _cargarHistoriaClinica,
                        ),
                      ],
                    ),
                  ),
                )
              : _historiaClinicaData == null
                  ? Center(
                      child: Padding(
                        padding: BioSpacing.pageAll,
                        child: BioAlert.info(
                          message: 'No hay datos disponibles',
                        ),
                      ),
                    )
                  : Column(
                      children: [
                        Expanded(
                          child: SingleChildScrollView(
                            padding: const EdgeInsets.all(BioSpacing.md),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                _buildPacienteHeader(
                                    _historiaClinicaData!.persona),
                                BioSpacing.gapH(BioSpacing.md),
                                _buildInformacionMedica(
                                    _historiaClinicaData!.informacionMedica),
                                BioSpacing.gapH(BioSpacing.md),
                                _buildSignosVitales(
                                    _historiaClinicaData!.signosVitales),
                                BioSpacing.gapH(BioSpacing.md),
                                _buildMotivosConsulta(_historiaClinicaData!),
                              ],
                            ),
                          ),
                        ),
                        if (_mostrarBarraConsulta) _buildChatInputBar(context),
                      ],
                    ),
    );
  }

  Widget _buildPacienteHeader(PersonaData persona) {
    return BioCard.intent(
      intent: UiIntent.primary,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Text(persona.nombreCompleto, style: BioTypography.h3),
          ),
          if (persona.edad != null)
            Text('${persona.edad} años', style: BioTypography.title),
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
        ? 'Signos vitales actuales (${sv.fechaTitulo})'
        : 'Signos vitales actuales';

    return BioCard.intent(
      intent: UiIntent.info,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(titulo, style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.md),
          if (!hayResumen)
            Text('Sin datos', style: BioTypography.bodySm)
          else
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (peso != null) Text('Peso: $peso kg', style: BioTypography.body),
                if (talla != null)
                  Text('Altura: $talla cm', style: BioTypography.body),
                if (imc != null) Text('IMC: $imc', style: BioTypography.body),
                if (tension != null)
                  Text('Tensión arterial: $tension', style: BioTypography.body),
              ],
            ),
          if (sv.datosSv.isNotEmpty &&
              (sv.tieneMasSv || sv.datosSv.length > 1)) ...[
            BioSpacing.gapH(BioSpacing.md),
            ExpansionTile(
              tilePadding: EdgeInsets.zero,
              title: Text(
                'Historial (${sv.totalSv} registros)',
                style: BioTypography.title,
              ),
              children: [
                for (final row in sv.datosSv.take(12))
                  Padding(
                    padding: const EdgeInsets.only(bottom: BioSpacing.sm),
                    child: Text(
                      _formatFilaSignosResumen(row),
                      style: BioTypography.caption,
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
    return BioCard.intent(
      intent: UiIntent.warning,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Condición actual', style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.lg),
          _buildSeccionInfo(
            'Diagnósticos recientes',
            info.condicionesActivas
                .map((c) => c.termino ?? 'Sin término')
                .toList(),
          ),
          BioSpacing.gapH(BioSpacing.lg),
          _buildSeccionInfo(
            'Condiciones activas',
            info.condicionesActivas
                .map((c) => c.termino ?? 'Sin término')
                .toList(),
          ),
          BioSpacing.gapH(BioSpacing.lg),
          _buildSeccionInfo(
            'Condiciones crónicas',
            info.condicionesCronicas
                .map((c) => c.termino ?? 'Sin término')
                .toList(),
          ),
          BioSpacing.gapH(BioSpacing.lg),
          _buildSeccionInfo(
            'Hallazgos',
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
        Text(titulo, style: BioTypography.overline),
        BioSpacing.gapH(BioSpacing.sm),
        if (items.isEmpty)
          Text('Sin datos', style: BioTypography.bodySm)
        else
          Wrap(
            spacing: BioSpacing.sm,
            runSpacing: BioSpacing.sm,
            children: items
                .map((item) => BioBadge.info(item.toUpperCase()))
                .toList(),
          ),
      ],
    );
  }

  Widget _buildMotivosConsulta(HistoriaClinicaResponse hc) {
    final mp = hc.motivosConsultaPaciente;
    final resumenIa = (mp.resumenIa ?? hc.informacionMedica.motivosConsulta ?? '')
        .trim();
    final hayResumen = resumenIa.isNotEmpty;
    final msgs = mp.messages;
    final sugerencias = mp.sugerenciasClinicas;
    final turnoCtx = mp.turno;
    final subtituloTurno = turnoCtx != null && turnoCtx.etiquetaCorta.isNotEmpty
        ? 'Turno ${turnoCtx.etiquetaCorta}'
        : (mp.turnoId != null ? 'Turno #${mp.turnoId}' : null);

    return BioCard.intent(
      intent: UiIntent.success,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Motivos de esta consulta', style: BioTypography.title),
          if (subtituloTurno != null) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              subtituloTurno,
              style: BioTypography.caption.copyWith(
                color: context.bio.textMuted,
              ),
            ),
          ],
          BioSpacing.gapH(BioSpacing.md),
          if (hayResumen) ...[
            Text('Resumen (IA)', style: BioTypography.overline),
            BioSpacing.gapH(BioSpacing.xs),
            Text(resumenIa, style: BioTypography.body),
          ] else if (mp.resumenIaPendiente)
            Text(
              'Generando resumen de motivos… Actualizá en unos segundos.',
              style: BioTypography.bodySm,
            )
          else if (msgs.isEmpty)
            Text(
              'Sin motivos registrados para esta consulta.',
              style: BioTypography.bodySm,
            ),
          if (sugerencias != null && sugerencias.tieneContenido) ...[
            BioSpacing.gapH(BioSpacing.lg),
            Text(
              'Orientación preliminar (IA)',
              style: BioTypography.overline,
            ),
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              'Sugerencias de apoyo; no reemplazan el criterio profesional.',
              style: BioTypography.caption.copyWith(
                color: context.bio.textMuted,
              ),
            ),
            if (sugerencias.diagnosticos.isNotEmpty) ...[
              BioSpacing.gapH(BioSpacing.sm),
              Text('Diagnósticos a considerar', style: BioTypography.bodySm),
              BioSpacing.gapH(BioSpacing.xs),
              ...sugerencias.diagnosticos.map(
                (d) => Padding(
                  padding: const EdgeInsets.only(bottom: BioSpacing.xs),
                  child: Text(
                    '• ${d.termino}${d.justificacion != null && d.justificacion!.isNotEmpty ? ' — ${d.justificacion}' : ''}',
                    style: BioTypography.bodySm,
                  ),
                ),
              ),
            ],
            if (sugerencias.practicas.isNotEmpty) ...[
              BioSpacing.gapH(BioSpacing.sm),
              Text('Prácticas / estudios', style: BioTypography.bodySm),
              BioSpacing.gapH(BioSpacing.xs),
              ...sugerencias.practicas.map(
                (p) => Padding(
                  padding: const EdgeInsets.only(bottom: BioSpacing.xs),
                  child: Text(
                    '• ${p.termino}${p.justificacion != null && p.justificacion!.isNotEmpty ? ' — ${p.justificacion}' : ''}',
                    style: BioTypography.bodySm,
                  ),
                ),
              ),
            ],
          ],
          if (msgs.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.lg),
            ExpansionTile(
              tilePadding: EdgeInsets.zero,
              title: Text(
                'Detalle enviado por el paciente (${msgs.length})',
                style: BioTypography.overline,
              ),
              children: msgs.map(_buildMotivoMensajeTile).toList(),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildMotivoMensajeTile(MotivoConsultaMensajeApi m) {
    final tokens = context.bio;
    final tipo = m.messageType.toLowerCase();
    final uri = Uri.tryParse(resolveMediaContentUrl(m.content));
    final esHttp = uri != null &&
        (uri.isScheme('http') || uri.isScheme('https'));

    Widget cuerpo;
    if (tipo == 'texto') {
      cuerpo = Text(m.content, style: BioTypography.body);
    } else if (isImageMessageType(tipo) && m.content.trim().isNotEmpty) {
      cuerpo = ChatMediaImage(
        source: m.content,
        bearerToken: widget.authToken,
        width: double.infinity,
        height: 200,
        fit: BoxFit.contain,
      );
    } else if (tipo == 'audio' && esHttp) {
      cuerpo = SelectableText(
        'Audio: ${m.content}',
        style: BioTypography.bodySm,
      );
    } else {
      cuerpo = SelectableText(m.content, style: BioTypography.bodySm);
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: BioSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (m.createdAt.isNotEmpty)
            Text(
              m.createdAt,
              style: BioTypography.caption.copyWith(color: tokens.textMuted),
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
        _snack('Consulta guardada', UiIntent.success);
        _chatController.clear();
        _chatFocusNode.unfocus();
      } catch (e) {
        if (!mounted) return;
        _snack('Error: $e', UiIntent.danger);
      } finally {
        if (mounted) setState(() => _guardandoConsulta = false);
      }
      return;
    }

    if (!widget.soloVer) {
      _snack(
        'Defina contexto de consulta (parent) o usá el flujo web para analizar con IA.',
        UiIntent.warning,
      );
      return;
    }
  }

  void _snack(String msg, UiIntent intent) {
    final palette = IntentPalette.of(intent);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(msg), backgroundColor: palette.base),
    );
  }

  Widget _buildChatInputBar(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return AssistantChatComposerBar(
      controller: _chatController,
      focusNode: _chatFocusNode,
      onSend: _enviarConsulta,
      isSending: _guardandoConsulta,
      hintText: 'Escribir consulta…',
      maxLines: 6,
      leading: [
        IconButton(
          icon: const Icon(Icons.mic_none),
          color: cs.onSurfaceVariant,
          onPressed: _guardandoConsulta
              ? null
              : () => _snack(
                    'Envío de audios en desarrollo',
                    UiIntent.info,
                  ),
        ),
      ],
    );
  }
}
