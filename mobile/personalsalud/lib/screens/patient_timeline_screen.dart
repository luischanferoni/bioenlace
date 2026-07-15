// lib/screens/patient_timeline_screen.dart
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';
import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models/encounter_capture_analysis.dart';
import '../models/timeline_event.dart';
import '../services/historia_clinica_service.dart';
import '../services/consulta_guardar_service.dart';

class PatientTimelineScreen extends StatefulWidget {
  final int personaId;
  final String? authToken;

  /// true = solo ver historia clínica (sin formulario); false = barra para escribir notas
  final bool soloVer;

  /// Vista mínima para consultas ya cargadas (solo paciente + datos del médico).
  final bool resumenConsultaCargada;

  /// Contexto de consulta (ej. `CIRUGIA`, `TURNO`) alineado con web / `validarPermisoAtencion`.
  final String? consultParent;
  final int? consultParentId;

  const PatientTimelineScreen({
    Key? key,
    required this.personaId,
    this.authToken,
    this.soloVer = true,
    this.resumenConsultaCargada = false,
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
  final EncounterCaptureApi _encounterApi = EncounterCaptureApi();
  final DeviceSpeechDictation _dictation = DeviceSpeechDictation();
  final AudioRecorder _audioRecorder = AudioRecorder();
  HistoriaClinicaResponse? _historiaClinicaData;
  bool _isLoading = true;
  String _errorMessage = '';
  bool _isAnalyzing = false;
  bool _isSaving = false;
  bool _dictating = false;
  String? _pendingAudioPath;
  DeviceDictationResult? _lastDictation;
  Map<String, dynamic>? _lastAnalysis;
  EncounterCaptureAnalysis? _captureReview;
  String? _draftText;
  Set<String> _stagedItemIds = {};
  String _sttStatus = '';
  SttClientConfig _sttConfig = SttClientConfig.defaults;
  bool _audioOnlyRecording = false;

  final TextEditingController _chatController = TextEditingController();
  final FocusNode _chatFocusNode = FocusNode();

  bool get _captureBusy => _isAnalyzing || _isSaving;

  bool get _enRevisionCaptura => _captureReview != null;
  bool get _mostrarBarraConsulta =>
      !widget.resumenConsultaCargada &&
      (!widget.soloVer ||
          (widget.consultParent != null &&
              widget.consultParent!.isNotEmpty &&
              widget.consultParentId != null));

  @override
  void initState() {
    super.initState();
    if (widget.authToken != null) {
      _historiaClinicaService.authToken = widget.authToken;
      _consultaGuardar.authToken = widget.authToken;
      _encounterApi.authToken = widget.authToken;
    }
    _dictation.initialize();
    _loadSttConfig();
    _cargarHistoriaClinica();
  }

  Future<void> _loadSttConfig() async {
    try {
      final cfg = await _encounterApi.fetchSttConfig();
      if (!mounted) return;
      setState(() => _sttConfig = cfg);
    } catch (_) {
      // Mantener defaults locales.
    }
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
        _errorMessage = _mensajeErrorHistoriaClinica(e);
        _isLoading = false;
      });
    }
  }

  String _mensajeErrorHistoriaClinica(Object e) {
    final raw = e is Exception ? e.toString() : '$e';
    final msg = raw.startsWith('Exception: ')
        ? raw.substring('Exception: '.length)
        : raw;
    return 'Error al cargar historia clínica: $msg';
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: BioAppBar(
        title: widget.resumenConsultaCargada
            ? 'Consulta cargada'
            : 'Historia clínica',
      ),
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
                                if (widget.resumenConsultaCargada) ...[
                                  BioSpacing.gapH(BioSpacing.md),
                                  _buildDocumentacionMedico(
                                    _historiaClinicaData!.documentacionMedico,
                                  ),
                                ] else ...[
                                  BioSpacing.gapH(BioSpacing.md),
                                  _buildInformacionMedica(
                                      _historiaClinicaData!.informacionMedica),
                                  BioSpacing.gapH(BioSpacing.md),
                                  _buildSignosVitales(
                                      _historiaClinicaData!.signosVitales),
                                  BioSpacing.gapH(BioSpacing.md),
                                  if (_historiaClinicaData!
                                          .motivosConsultaPaciente.motivosIntake
                                          ?.tieneContenido ==
                                      true) ...[
                                    _buildMotivosIntake(
                                      _historiaClinicaData!
                                          .motivosConsultaPaciente.motivosIntake!,
                                    ),
                                    BioSpacing.gapH(BioSpacing.md),
                                  ],
                                  _buildMotivosConsulta(_historiaClinicaData!),
                                  if (_historiaClinicaData!.carePackCohorte
                                          ?.tieneContenido ==
                                      true) ...[
                                    BioSpacing.gapH(BioSpacing.md),
                                    _buildCarePackCohorte(
                                      _historiaClinicaData!.carePackCohorte!,
                                    ),
                                  ],
                                  if (_isAnalyzing) ...[
                                    BioSpacing.gapH(BioSpacing.md),
                                    _buildAnalyzingCard(),
                                  ],
                                  if (_captureReview != null) ...[
                                    BioSpacing.gapH(BioSpacing.md),
                                    _buildCaptureReviewPanel(_captureReview!),
                                  ],
                                ],
                              ],
                            ),
                          ),
                        ),
                        if (_enRevisionCaptura) _buildCaptureActionsBar(),
                        if (_mostrarBarraConsulta && !_enRevisionCaptura)
                          _buildChatInputBar(context),
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
          if (!widget.resumenConsultaCargada && persona.edad != null)
            Text('${persona.edad} años', style: BioTypography.title),
        ],
      ),
    );
  }

  Widget _buildDocumentacionMedico(DocumentacionMedico doc) {
    return BioCard.intent(
      intent: UiIntent.success,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Datos cargados', style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.md),
          if (!doc.tieneDatos || doc.secciones.isEmpty)
            Text('Sin datos registrados en esta consulta.', style: BioTypography.bodySm)
          else
            ...doc.secciones.map(
              (seccion) => Padding(
                padding: const EdgeInsets.only(bottom: BioSpacing.md),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(seccion.titulo, style: BioTypography.overline),
                    BioSpacing.gapH(BioSpacing.xs),
                    ...seccion.items.map(
                      (item) => Padding(
                        padding: const EdgeInsets.only(bottom: BioSpacing.xs),
                        child: Text(item, style: BioTypography.body),
                      ),
                    ),
                  ],
                ),
              ),
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
    final secciones = <({String titulo, List<String> items})>[
      (
        titulo: 'Diagnósticos recientes',
        items: _labelsFromCondiciones(info.condicionesActivas),
      ),
      (
        titulo: 'Condiciones crónicas',
        items: _labelsFromCondiciones(info.condicionesCronicas),
      ),
      (
        titulo: 'Alergias',
        items: _labelsFromHallazgos(info.hallazgos),
      ),
    ];

    return BioCard.intent(
      intent: UiIntent.warning,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Condición actual', style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.lg),
          LayoutBuilder(
            builder: (context, constraints) {
              const spacing = BioSpacing.md;
              const minCardWidth = 148.0;
              final maxColumns =
                  ((constraints.maxWidth + spacing) / (minCardWidth + spacing))
                      .floor()
                      .clamp(1, 4);
              final cardWidth =
                  (constraints.maxWidth - spacing * (maxColumns - 1)) /
                      maxColumns;

              return Wrap(
                spacing: spacing,
                runSpacing: spacing,
                children: [
                  for (final seccion in secciones)
                    SizedBox(
                      width: cardWidth,
                      child: _buildSeccionInfo(seccion.titulo, seccion.items),
                    ),
                ],
              );
            },
          ),
        ],
      ),
    );
  }

  List<String> _labelsFromCondiciones(List<Condicion> items) {
    return items
        .map((c) => c.termino?.trim())
        .whereType<String>()
        .where((t) => t.isNotEmpty)
        .toList();
  }

  List<String> _labelsFromHallazgos(List<Hallazgo> items) {
    return items
        .map((h) => h.termino?.trim())
        .whereType<String>()
        .where((t) => t.isNotEmpty)
        .toList();
  }

  Widget _buildSeccionInfo(String titulo, List<String> items) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(BioSpacing.sm),
      decoration: BoxDecoration(
        color: context.bio.paperSurfaceSunken,
        borderRadius: BorderRadius.circular(BioRadius.sm),
        border: Border.all(
          color: context.bio.paperBorderDefault,
          width: BorderWidth.thin,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(titulo, style: BioTypography.overline),
          BioSpacing.gapH(BioSpacing.sm),
          if (items.isEmpty)
            Text('Sin datos', style: BioTypography.bodySm)
          else
            Wrap(
              spacing: BioSpacing.xs,
              runSpacing: BioSpacing.xs,
              children: items
                  .map((item) => BioBadge.info(item.toUpperCase()))
                  .toList(),
            ),
        ],
      ),
    );
  }

  Widget _buildMotivosIntake(MotivosIntakeStaff intake) {
    return BioCard.intent(
      intent: UiIntent.info,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Preguntas previas al chat', style: BioTypography.title),
          if (intake.title != null && intake.title!.trim().isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              intake.title!.trim(),
              style: BioTypography.caption.copyWith(
                color: context.bio.textMuted,
              ),
            ),
          ],
          BioSpacing.gapH(BioSpacing.md),
          if (intake.notesForStaff != null &&
              intake.notesForStaff!.trim().isNotEmpty) ...[
            Text('Orientación', style: BioTypography.overline),
            BioSpacing.gapH(BioSpacing.xs),
            Text(intake.notesForStaff!.trim(), style: BioTypography.bodySm),
            BioSpacing.gapH(BioSpacing.md),
          ],
          if (intake.answers.isEmpty)
            Text(
              intake.status == 'submitted'
                  ? 'Respuestas registradas sin detalle disponible.'
                  : 'El paciente aún no completó las preguntas previas.',
              style: BioTypography.bodySm,
            )
          else ...[
            Text('Respuestas del paciente', style: BioTypography.overline),
            BioSpacing.gapH(BioSpacing.sm),
            ...intake.answers.map(
              (a) => Padding(
                padding: const EdgeInsets.only(bottom: BioSpacing.sm),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(a.question, style: BioTypography.bodySm),
                    BioSpacing.gapH(BioSpacing.xs),
                    Text(a.answer, style: BioTypography.body),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildMotivosConsulta(HistoriaClinicaResponse hc) {
    final mp = hc.motivosConsultaPaciente;
    final resumen = (mp.resumen ?? mp.resumenIa ?? hc.informacionMedica.motivosConsulta ?? '')
        .trim();
    final hayResumen = resumen.isNotEmpty;
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
            Text('Resumen', style: BioTypography.overline),
            BioSpacing.gapH(BioSpacing.xs),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: _buildResumenConImagenes(resumen, mp.imagenesAdjuntas),
            ),
          ] else if (mp.resumenPendiente)
            Text(
              'Generando resumen… Actualizá en unos segundos.',
              style: BioTypography.bodySm,
            )
          else
            Text(
              'Sin motivos registrados para esta consulta.',
              style: BioTypography.bodySm,
            ),
          if (sugerencias != null && sugerencias.tieneContenido) ...[
            BioSpacing.gapH(BioSpacing.lg),
            Text(
              'Orientación según motivos del paciente',
              style: BioTypography.overline,
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
        ],
      ),
    );
  }

  Widget _buildCarePackCohorte(CarePackCohorteStaff cohorte) {
    final assistance = cohorte.assistance;
    final profile = cohorte.cohortProfile;
    final profileParts = <String>[];
    if (profile != null) {
      for (final key in ['life_stage', 'sexo', 'motive_cluster', 'jurisdiction']) {
        final val = profile[key]?.toString().trim();
        if (val != null && val.isNotEmpty) {
          profileParts.add(val);
        }
      }
    }

    return BioCard.intent(
      intent: UiIntent.info,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Asistencia pre-consulta (cohorte)', style: BioTypography.title),
          if (cohorte.cohortKeyShort != null &&
              cohorte.cohortKeyShort!.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              'Cohorte ${cohorte.cohortKeyShort}',
              style: BioTypography.caption.copyWith(
                color: context.bio.textMuted,
              ),
            ),
          ],
          if (profileParts.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              profileParts.join(' · '),
              style: BioTypography.bodySm,
            ),
          ],
          BioSpacing.gapH(BioSpacing.md),
          if (assistance.notesForStaff != null &&
              assistance.notesForStaff!.trim().isNotEmpty) ...[
            Text('Orientación', style: BioTypography.overline),
            BioSpacing.gapH(BioSpacing.xs),
            Text(assistance.notesForStaff!.trim(), style: BioTypography.bodySm),
            BioSpacing.gapH(BioSpacing.md),
          ],
          if (assistance.answers.isEmpty)
            Text(
              assistance.status == 'submitted'
                  ? 'Respuestas registradas sin detalle disponible.'
                  : 'El paciente aún no completó el cuestionario.',
              style: BioTypography.bodySm,
            )
          else ...[
            Text('Respuestas del paciente', style: BioTypography.overline),
            BioSpacing.gapH(BioSpacing.sm),
            ...assistance.answers.map(
              (a) => Padding(
                padding: const EdgeInsets.only(bottom: BioSpacing.sm),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(a.question, style: BioTypography.bodySm),
                    BioSpacing.gapH(BioSpacing.xs),
                    Text(a.answer, style: BioTypography.body),
                  ],
                ),
              ),
            ),
          ],
          if (assistance.deltaRequested) ...[
            BioSpacing.gapH(BioSpacing.sm),
            BioBadge.warning('Requiere adaptación del pack'),
          ],
        ],
      ),
    );
  }

  List<Widget> _buildResumenConImagenes(
    String resumen,
    List<MotivoImagenAdjunta> imagenes,
  ) {
    final byRef = {for (final i in imagenes) i.ref: i.url};
    final pattern = RegExp(r'\[(imagen\d+)\]');
    final widgets = <Widget>[];
    var last = 0;

    for (final match in pattern.allMatches(resumen)) {
      if (match.start > last) {
        widgets.add(Text(
          resumen.substring(last, match.start),
          style: BioTypography.body,
        ));
      }
      final ref = match.group(1)!;
      final url = byRef[ref];
      if (url != null && url.isNotEmpty) {
        widgets.add(Padding(
          padding: const EdgeInsets.symmetric(vertical: BioSpacing.xs),
          child: ChatMediaImage(
            source: url,
            bearerToken: widget.authToken,
            width: double.infinity,
            height: 160,
            fit: BoxFit.contain,
          ),
        ));
      } else {
        widgets.add(Text('[$ref]', style: BioTypography.body));
      }
      last = match.end;
    }
    if (last < resumen.length) {
      widgets.add(Text(resumen.substring(last), style: BioTypography.body));
    }
    if (widgets.isEmpty) {
      widgets.add(Text(resumen, style: BioTypography.body));
    }

    return widgets;
  }

  bool get _tieneContextoCaptura =>
      widget.consultParent != null &&
      widget.consultParent!.isNotEmpty &&
      widget.consultParentId != null;

  Future<void> _toggleDictation() async {
    if (_captureBusy) return;
    if (_dictating || _audioOnlyRecording) {
      if (_audioOnlyRecording) {
        await _stopAudioOnlyRecording();
      } else {
        final result = await _dictation.stop();
        await _stopBackupRecording();
        if (!mounted) return;
        setState(() {
          _dictating = false;
          _lastDictation = result;
          if (result.text.trim().isNotEmpty) {
            _chatController.text = result.text.trim();
          }
          _sttStatus = _dictationStatusMessage(result, _chatController.text);
        });
      }
      return;
    }
    if (!_sttConfig.deviceEnabled && _sttConfig.serverEnabled) {
      await _startAudioOnlyRecording();
      return;
    }
    if (!_sttConfig.deviceEnabled) {
      _snack('Dictado en dispositivo deshabilitado.', UiIntent.warning);
      return;
    }
    try {
      await _startBackupRecording();
      await _dictation.start(onPartial: (text, _) {
        if (!mounted) return;
        setState(() {
          _chatController.text = text;
          _sttStatus = 'Escuchando…';
        });
      });
      if (!mounted) return;
      setState(() {
        _dictating = true;
        _sttStatus = 'Escuchando… pulse micrófono para detener.';
      });
    } catch (e) {
      _snack('No se pudo iniciar dictado: $e', UiIntent.danger);
    }
  }

  Future<void> _startAudioOnlyRecording() async {
    try {
      if (!await _audioRecorder.hasPermission()) {
        _snack('Permiso de micrófono denegado.', UiIntent.warning);
        return;
      }
      final dir = await getTemporaryDirectory();
      _pendingAudioPath =
          '${dir.path}/encounter_${DateTime.now().millisecondsSinceEpoch}.m4a';
      await _audioRecorder.start(
        const RecordConfig(encoder: AudioEncoder.aacLc, sampleRate: 44100),
        path: _pendingAudioPath!,
      );
      if (!mounted) return;
      setState(() {
        _audioOnlyRecording = true;
        _sttStatus = 'Grabando… pulse micrófono para detener.';
      });
    } catch (e) {
      _snack('No se pudo grabar audio: $e', UiIntent.danger);
    }
  }

  Future<void> _stopAudioOnlyRecording() async {
    if (await _audioRecorder.isRecording()) {
      await _audioRecorder.stop();
    }
    if (!mounted) return;
    setState(() {
      _audioOnlyRecording = false;
      _sttStatus = _pendingAudioPath != null
          ? 'Audio grabado. Pulse «Servidor» para transcribir.'
          : 'No se capturó audio.';
    });
  }

  Future<void> _startBackupRecording() async {
    if (!await _audioRecorder.hasPermission()) return;
    final dir = await getTemporaryDirectory();
    _pendingAudioPath =
        '${dir.path}/encounter_${DateTime.now().millisecondsSinceEpoch}.m4a';
    await _audioRecorder.start(
      const RecordConfig(encoder: AudioEncoder.aacLc, sampleRate: 44100),
      path: _pendingAudioPath!,
    );
  }

  Future<void> _stopBackupRecording() async {
    if (await _audioRecorder.isRecording()) {
      await _audioRecorder.stop();
    }
  }

  Future<String?> _audioPathToBase64(String path) async {
    try {
      final bytes = await XFile(path).readAsBytes();
      return 'data:audio/m4a;base64,${base64Encode(bytes)}';
    } catch (_) {
      return null;
    }
  }

  Future<void> _transcribirEnServidor() async {
    if (!_sttConfig.serverEnabled) {
      _snack('Transcripción en servidor deshabilitada.', UiIntent.warning);
      return;
    }
    if (_pendingAudioPath == null) {
      _snack('Grabe con el micrófono antes de usar servidor.', UiIntent.warning);
      return;
    }
    setState(() => _isAnalyzing = true);
    try {
      final b64 = await _audioPathToBase64(_pendingAudioPath!);
      if (b64 == null) throw Exception('No se pudo leer el audio');
      final text = await _encounterApi.transcribirServidor(audioBase64: b64);
      if (!mounted) return;
      setState(() {
        _chatController.text = text;
        _lastDictation = DeviceDictationResult(
          text: text,
          confidence: 0.9,
          durationMs: 0,
          engine: 'server',
          locale: 'es_AR',
        );
        _sttStatus = 'Transcripción de servidor aplicada.';
      });
    } catch (e) {
      _snack('Error STT servidor: $e', UiIntent.danger);
    } finally {
      if (mounted) setState(() => _isAnalyzing = false);
    }
  }

  Future<Map<String, dynamic>?> _operationalContextForCapture() async {
    final prefs = await SharedPreferences.getInstance();
    final pes = prefs.getInt('id_profesional_efector_servicio') ?? 0;
    if (pes <= 0) return null;
    final servicio = prefs.getInt('servicio_id');
    return {
      'id_profesional_efector_servicio': pes,
      if (servicio != null && servicio > 0) 'servicio_actual': servicio,
    };
  }

  String _mensajeErrorCaptura(Object e) {
    final raw = e is Exception ? e.toString() : '$e';
    final msg = raw.startsWith('Exception: ')
        ? raw.substring('Exception: '.length)
        : raw;
    return msg;
  }

  String _dictationStatusMessage(DeviceDictationResult result, String text) {
    final trimmed = text.trim();
    if (trimmed.isEmpty) {
      return 'No se detectó voz. Intente de nuevo o escriba la consulta.';
    }
    if (DeviceSttLocalQuality.isAcceptable(trimmed, result)) {
      return 'Dictado listo. Revise y envíe.';
    }
    if (_sttConfig.serverEnabled) {
      return 'Calidad baja. Corrija el texto, use «Transcribir en servidor» o envíe para reintentar con audio.';
    }
    return 'Calidad baja. Corrija el texto si hace falta y envíe.';
  }

  /// Solo envía metadatos STT cuando aportan; evita bloquear texto tipeado o corregido.
  Future<({
    Map<String, dynamic>? stt,
    String? audioBase64,
    bool sttForceServer,
  })> _resolveSpeechPayloadForAnalyze(String text) async {
    final last = _lastDictation;
    if (last == null) {
      return (stt: null, audioBase64: null, sttForceServer: false);
    }

    final dictationText = last.text.trim();
    if (text != dictationText) {
      return (stt: null, audioBase64: null, sttForceServer: false);
    }

    if (DeviceSttLocalQuality.isAcceptable(text, last)) {
      return (
        stt: last.toSttPayload(),
        audioBase64: null,
        sttForceServer: false,
      );
    }

    if (_sttConfig.serverEnabled && _pendingAudioPath != null) {
      final audioB64 = await _audioPathToBase64(_pendingAudioPath!);
      if (audioB64 != null && audioB64.isNotEmpty) {
        return (
          stt: null,
          audioBase64: audioB64,
          sttForceServer: true,
        );
      }
    }

    return (stt: null, audioBase64: null, sttForceServer: false);
  }

  void _clearCaptureDraft() {
    setState(() {
      _captureReview = null;
      _lastAnalysis = null;
      _draftText = null;
      _stagedItemIds = {};
      _lastDictation = null;
      _sttStatus = '';
      _chatController.clear();
    });
  }

  void _editCaptureDraft() {
    final draft = _draftText ?? '';
    setState(() {
      _captureReview = null;
      _lastAnalysis = null;
      _stagedItemIds = {};
      _sttStatus = '';
      _chatController.text = draft;
    });
    _chatFocusNode.requestFocus();
  }

  void _toggleStagedItem(String id, bool selected) {
    setState(() {
      if (selected) {
        _stagedItemIds.add(id);
      } else {
        _stagedItemIds.remove(id);
      }
    });
  }

  Future<void> _analizarConsulta() async {
    final text = _chatController.text.trim();
    if (text.isEmpty) {
      _snack('Escriba o dicte la consulta.', UiIntent.warning);
      return;
    }
    if (!_tieneContextoCaptura) {
      _snack('Falta contexto de atención (parent).', UiIntent.warning);
      return;
    }
    setState(() {
      _isAnalyzing = true;
      _sttStatus = '';
      _captureReview = null;
      _lastAnalysis = null;
      _draftText = null;
      _stagedItemIds = {};
    });
    try {
      final speech = await _resolveSpeechPayloadForAnalyze(text);
      final res = await _encounterApi.analizar(
        consulta: text,
        idPersona: widget.personaId,
        parent: widget.consultParent,
        parentId: widget.consultParentId,
        stt: speech.stt,
        audioBase64: speech.audioBase64,
        sttForceServer: speech.sttForceServer,
        userPerTabConfig: await _operationalContextForCapture(),
      );
      if (!mounted) return;
      final review = EncounterCaptureAnalysis.fromApiResponse(res);
      setState(() {
        _lastAnalysis = res;
        _draftText = text;
        _captureReview = review;
        _stagedItemIds = review.allItems
            .where((e) => e.isFromClinicalText)
            .map((e) => e.id)
            .toSet();
        if (_stagedItemIds.isEmpty && review.defaultStagedItemIds.isNotEmpty) {
          _stagedItemIds = review.defaultStagedItemIds.toSet();
        }
        // Si quedó vacío pero hay extracción, incluir todo (mejor de más que de menos).
        if (_stagedItemIds.isEmpty && review.hasExtractedContent) {
          _stagedItemIds = review.allItems.map((e) => e.id).toSet();
        }
        _chatController.clear();
        _sttStatus = '';
      });
    } catch (e) {
      _snack('Error al analizar: ${_mensajeErrorCaptura(e)}', UiIntent.danger);
    } finally {
      if (mounted) setState(() => _isAnalyzing = false);
    }
  }

  Future<void> _confirmarGuardado() async {
    final review = _captureReview;
    if (review == null || _lastAnalysis == null) {
      _snack('Analizá la consulta antes de confirmar.', UiIntent.warning);
      return;
    }
    if (review.systemError != null || !review.puedeConfirmar) {
      _snack('No se puede guardar: el análisis tiene errores.', UiIntent.warning);
      return;
    }
    if (review.tieneDatosFaltantes && _stagedItemIds.isEmpty) {
      _snack('Faltan datos obligatorios en el análisis.', UiIntent.warning);
      return;
    }
    // Si la IA extrajo ítems, exigir al menos uno tildado (evita guardar solo texto
    // y perder medicación/prácticas/indicaciones).
    if (review.hasExtractedContent && _stagedItemIds.isEmpty) {
      _snack(
        'Seleccioná al menos un ítem del análisis antes de confirmar.',
        UiIntent.warning,
      );
      return;
    }
    final saveIds = review.effectiveSaveItemIds(_stagedItemIds);
    final extraidos = review.toDatosExtraidos(saveIds);
    // Backup: extracción completa del analizar (el backend completa categorías omitidas).
    final analisisBackup = _resolveAnalisisDatosExtraidos(_lastAnalysis!);
    final textoOriginal =
        (_lastAnalysis!['texto_original'] ?? review.textoOriginal).toString();
    final textoProcesado = (_lastAnalysis!['texto_procesado'] ??
            review.textoProcesado ??
            _draftText ??
            textoOriginal)
        .toString();
    final idConfiguracion = _lastAnalysis!['id_configuracion'] is int
        ? _lastAnalysis!['id_configuracion'] as int
        : int.tryParse('${_lastAnalysis!['id_configuracion']}');
    final encounterId = _lastAnalysis!['encounter_id'] is int
        ? _lastAnalysis!['encounter_id'] as int
        : int.tryParse(
            '${_lastAnalysis!['encounter_id'] ?? _lastAnalysis!['id_consulta']}',
          );

    setState(() => _isSaving = true);
    try {
      await _encounterApi.guardar(
        idPersona: widget.personaId,
        parent: widget.consultParent,
        parentId: widget.consultParentId,
        datosExtraidos: extraidos,
        analisisDatosExtraidos: analisisBackup,
        textoOriginal: textoOriginal,
        textoProcesado: textoProcesado,
        idConfiguracion: idConfiguracion,
        encounterId: encounterId,
        userPerTabConfig: await _operationalContextForCapture(),
      );
      if (!mounted) return;
      _snack('Consulta guardada', UiIntent.success);
      _clearCaptureDraft();
      Navigator.of(context).pop(true);
    } catch (e) {
      _snack('Error al guardar: ${_mensajeErrorCaptura(e)}', UiIntent.danger);
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  Map<String, dynamic>? _resolveAnalisisDatosExtraidos(Map<String, dynamic> analysis) {
    final datos = analysis['datos'];
    if (datos is Map) {
      final map = Map<String, dynamic>.from(datos);
      final inner = map['datosExtraidos'];
      if (inner is Map) {
        return Map<String, dynamic>.from(inner);
      }
      if (map.keys.any((k) => k != 'Error')) {
        return map;
      }
    }
    final review = _captureReview;
    if (review != null && review.hasExtractedContent) {
      return review.toDatosExtraidos(
        review.allItems.map((e) => e.id).toSet(),
      );
    }
    return null;
  }

  Future<void> _enviarConsulta() async {
    if (!_tieneContextoCaptura) {
      if (!widget.soloVer) {
        _snack(
          'Defina contexto de consulta (parent) para captura con IA.',
          UiIntent.warning,
        );
      }
      return;
    }
    if (_enRevisionCaptura) return;
    await _analizarConsulta();
  }

  Widget _buildAnalyzingCard() {
    return BioCard.intent(
      intent: UiIntent.info,
      child: Row(
        children: [
          const SizedBox(
            width: 22,
            height: 22,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
          BioSpacing.gapW(BioSpacing.md),
          Expanded(
            child: Text(
              'Analizando la consulta…',
              style: BioTypography.body,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCaptureReviewPanel(EncounterCaptureAnalysis review) {
    return BioCard.intent(
      intent: UiIntent.primary,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Nota de esta atención', style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.sm),
          Text(
            'Revisá lo registrado y las sugerencias antes de confirmar.',
            style: BioTypography.caption.copyWith(color: context.bio.textMuted),
          ),
          BioSpacing.gapH(BioSpacing.md),
          Text('Texto registrado', style: BioTypography.overline),
          BioSpacing.gapH(BioSpacing.xs),
          Text(
            (_draftText ?? review.textoOriginal).trim().isNotEmpty
                ? (_draftText ?? review.textoOriginal)
                : review.textoOriginal,
            style: BioTypography.body,
          ),
          if (review.textoProcesado != null &&
              review.textoProcesado!.trim().isNotEmpty &&
              review.textoProcesado!.trim() != review.textoOriginal.trim()) ...[
            BioSpacing.gapH(BioSpacing.md),
            Text('Texto procesado', style: BioTypography.overline),
            BioSpacing.gapH(BioSpacing.xs),
            Text(review.textoProcesado!, style: BioTypography.bodySm),
          ],
          if (review.systemError != null) ...[
            BioSpacing.gapH(BioSpacing.md),
            BioAlert.danger(message: review.systemError!),
          ] else if (!review.hasExtractedContent) ...[
            BioSpacing.gapH(BioSpacing.md),
            BioAlert.info(
              message:
                  'La IA no extrajo datos estructurados. Podés confirmar igual con el texto registrado.',
            ),
          ] else ...[
            BioSpacing.gapH(BioSpacing.lg),
            Text(
              'Análisis y Sugerencias de la IA',
              style: BioTypography.overline,
            ),
            BioSpacing.gapH(BioSpacing.xs),
            const Wrap(
              spacing: BioSpacing.md,
              runSpacing: BioSpacing.xs,
              children: [
                _CaptureSourceLegend(
                  intent: UiIntent.neutral,
                  label: 'Del texto clínico',
                ),
                _CaptureSourceLegend(
                  intent: UiIntent.secondary,
                  label: 'Aporte de la IA',
                ),
              ],
            ),
            BioSpacing.gapH(BioSpacing.sm),
            ...review.categories.map((cat) {
              return Padding(
                padding: const EdgeInsets.only(bottom: BioSpacing.md),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(cat.title, style: BioTypography.title),
                        ),
                        if (cat.required)
                          BioBadge.danger('Requerido'),
                      ],
                    ),
                    BioSpacing.gapH(BioSpacing.sm),
                    if (cat.items.isEmpty)
                      Text(
                        cat.required
                            ? 'Falta información en esta categoría.'
                            : 'Sin datos en esta categoría.',
                        style: BioTypography.bodySm.copyWith(
                          color: cat.required
                              ? IntentPalette.of(UiIntent.danger).base
                              : context.bio.textMuted,
                        ),
                      )
                    else
                      Wrap(
                        spacing: BioSpacing.sm,
                        runSpacing: BioSpacing.sm,
                        children: cat.items.map((item) {
                          final selected = _stagedItemIds.contains(item.id);
                          final chipLabel =
                              item.subtitle != null && item.subtitle!.isNotEmpty
                                  ? '${item.label} (${item.subtitle})'
                                  : item.label;
                          return BioChip(
                            label: chipLabel,
                            selected: selected,
                            icon: selected ? Icons.check : null,
                            intent: item.source == EncounterCaptureItemSource.ai
                                ? UiIntent.secondary
                                : UiIntent.neutral,
                            onTap: _isSaving
                                ? null
                                : () => _toggleStagedItem(item.id, !selected),
                          );
                        }).toList(),
                      ),
                  ],
                ),
              );
            }),
          ],
          if (review.tieneDatosFaltantes) ...[
            BioSpacing.gapH(BioSpacing.sm),
            BioAlert.warning(
              message:
                  'Faltan datos obligatorios. Incluí al menos un ítem requerido antes de confirmar.',
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildCaptureActionsBar() {
    final review = _captureReview;
    final canConfirm = !_isSaving &&
        review != null &&
        review.puedeConfirmar &&
        review.systemError == null &&
        review.textoOriginal.trim().isNotEmpty &&
        !(review.tieneDatosFaltantes && _stagedItemIds.isEmpty) &&
        !(review.hasExtractedContent && _stagedItemIds.isEmpty);

    return Container(
      padding: const EdgeInsets.fromLTRB(
        BioSpacing.md,
        BioSpacing.sm,
        BioSpacing.md,
        BioSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: context.bio.paperSurface,
        border: Border(
          top: BorderSide(color: context.bio.paperBorderDefault),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: BioButton(
                  label: 'Editar',
                  intent: UiIntent.neutral,
                  variant: BioButtonVariant.soft,
                  onPressed: _isSaving ? null : _editCaptureDraft,
                ),
              ),
              BioSpacing.gapW(BioSpacing.sm),
              Expanded(
                child: BioButton(
                  label: 'Descartar',
                  intent: UiIntent.danger,
                  variant: BioButtonVariant.soft,
                  onPressed: _isSaving ? null : _clearCaptureDraft,
                ),
              ),
            ],
          ),
          BioSpacing.gapH(BioSpacing.sm),
          BioButton.primary(
            label: _isSaving ? 'Guardando…' : 'Confirmar y guardar',
            icon: _isSaving ? null : Icons.check,
            onPressed: canConfirm ? _confirmarGuardado : null,
          ),
        ],
      ),
    );
  }

  void _snack(String msg, UiIntent intent) {
    final palette = IntentPalette.of(intent);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(msg), backgroundColor: palette.base),
    );
  }

  Widget _buildChatInputBar(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final canCapture = _tieneContextoCaptura;
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (_sttStatus.isNotEmpty ||
            (_sttConfig.serverEnabled && _pendingAudioPath != null))
          Padding(
            padding: const EdgeInsets.only(
              left: BioSpacing.sm,
              right: BioSpacing.sm,
              bottom: BioSpacing.xs,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (_sttStatus.isNotEmpty)
                  Text(
                    _sttStatus,
                    style: BioTypography.caption.copyWith(color: cs.primary),
                  ),
                if (_sttConfig.serverEnabled &&
                    _pendingAudioPath != null &&
                    !_dictating &&
                    !_audioOnlyRecording) ...[
                  if (_sttStatus.isNotEmpty) BioSpacing.gapH(BioSpacing.xs),
                  TextButton.icon(
                    onPressed: _captureBusy ? null : _transcribirEnServidor,
                    icon: const Icon(Icons.cloud_upload_outlined, size: 18),
                    label: const Text('Transcribir en servidor'),
                  ),
                ],
              ],
            ),
          ),
        AssistantChatComposerBar(
          controller: _chatController,
          focusNode: _chatFocusNode,
          onSend: _enviarConsulta,
          isSending: _captureBusy,
          sendIcon: Icons.fact_check_outlined,
          hintText: canCapture
              ? 'Dictar o escribir la consulta…'
              : 'Escribir consulta…',
          maxLines: 6,
          onVoice: canCapture &&
                  (_sttConfig.deviceEnabled || _sttConfig.serverEnabled)
              ? _toggleDictation
              : null,
          voiceActive: _dictating || _audioOnlyRecording,
        ),
      ],
    );
  }
}

class _CaptureSourceLegend extends StatelessWidget {
  const _CaptureSourceLegend({
    required this.intent,
    required this.label,
  });

  final UiIntent intent;
  final String label;

  @override
  Widget build(BuildContext context) {
    final palette = IntentPalette.of(intent);
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 10,
          height: 10,
          decoration: BoxDecoration(
            color: palette.softBg,
            border: Border.all(color: palette.border),
            borderRadius: BorderRadius.circular(BioRadius.xs),
          ),
        ),
        const SizedBox(width: BioSpacing.xs),
        Text(
          label,
          style: BioTypography.caption.copyWith(color: context.bio.textMuted),
        ),
      ],
    );
  }
}
