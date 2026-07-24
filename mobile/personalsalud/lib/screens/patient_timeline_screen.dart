// lib/screens/patient_timeline_screen.dart
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';
import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models/encounter_capture_analysis.dart';
import '../models/pending_encounter_capture.dart';
import '../models/timeline_event.dart';
import '../services/historia_clinica_service.dart';
import '../services/consulta_guardar_service.dart';
import '../services/pending_encounter_capture_store.dart';

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
  final PendingEncounterCaptureStore _pendingStore =
      PendingEncounterCaptureStore.instance;
  List<PendingEncounterCapture> _pendingCaptures = [];
  String? _activePendingId;

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

  bool _autoOpenedReview = false;

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
    _loadPendingCaptures();
  }

  Future<void> _loadPendingCaptures() async {
    if (!_tieneContextoCaptura) return;
    final local = await _pendingStore.listForContext(
      personaId: widget.personaId,
      parent: widget.consultParent!,
      parentId: widget.consultParentId!,
    );
    final byId = <String, PendingEncounterCapture>{
      for (final item in local) item.id: item,
    };
    try {
      final remote = await _encounterApi.capturaListar(
        idPersona: widget.personaId,
        parent: widget.consultParent,
        parentId: widget.consultParentId,
      );
      for (final row in remote) {
        final clientId = row['client_capture_id']?.toString() ?? '';
        if (clientId.isEmpty) continue;
        var merged = PendingEncounterCapture.fromServerCapture(
          row,
          local: byId[clientId],
        );
        // Si el listado no trajo el análisis completo, pedirlo a /ver.
        final needsReview = merged.status ==
                PendingEncounterCaptureStatus.pendingSave ||
            merged.status == PendingEncounterCaptureStatus.failedSave;
        if (needsReview && merged.analysisResponse == null) {
          try {
            final detail = await _encounterApi.capturaVer(
              clientCaptureId: merged.id,
              captureId: merged.serverCaptureId,
            );
            final capture = detail['capture'];
            if (capture is Map) {
              merged = PendingEncounterCapture.fromServerCapture(
                Map<String, dynamic>.from(capture),
                local: merged,
              );
            }
          } catch (_) {}
        }
        byId[clientId] = merged;
        await _pendingStore.upsert(merged);
      }
    } catch (_) {
      // Sin red: solo locales.
    }
    final list = byId.values.toList()
      ..sort((a, b) => b.updatedAt.compareTo(a.updatedAt));
    if (!mounted) return;
    setState(() => _pendingCaptures = list);

    if (!_autoOpenedReview &&
        !_enRevisionCaptura &&
        _captureReview == null &&
        !_captureBusy) {
      PendingEncounterCapture? ready;
      for (final item in list) {
        final isReady = item.status ==
                PendingEncounterCaptureStatus.pendingSave ||
            item.status == PendingEncounterCaptureStatus.failedSave;
        if (isReady && item.analysisResponse != null) {
          ready = item;
          break;
        }
      }
      if (ready != null) {
        _autoOpenedReview = true;
        await _resumePendingCapture(ready);
      }
    }
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
                                  if (_pendingCaptures.isNotEmpty) ...[
                                    BioSpacing.gapH(BioSpacing.md),
                                    _buildPendingCapturesPanel(),
                                  ],
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
        if (_tieneContextoCaptura &&
            (_chatController.text.trim().isNotEmpty ||
                _pendingAudioPath != null)) {
          await _persistLocalDraft(
            texto: _chatController.text.trim().isEmpty
                ? '(audio pendiente de transcribir)'
                : _chatController.text.trim(),
            status: PendingEncounterCaptureStatus.draft,
            clearError: true,
          );
        }
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
        _sttStatus = 'Escuchando…';
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
        _sttStatus = 'Grabando…';
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
    final text = _chatController.text.trim();
    if (_tieneContextoCaptura && _pendingAudioPath != null) {
      await _persistLocalDraft(
        texto: text.isEmpty ? '(audio pendiente de transcribir)' : text,
        status: PendingEncounterCaptureStatus.draft,
        clearError: true,
      );
    }
    if (!mounted) return;
    setState(() {
      _audioOnlyRecording = false;
      _sttStatus = _pendingAudioPath != null
          ? 'Audio listo'
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
      return 'Sin voz detectada';
    }
    if (DeviceSttLocalQuality.isAcceptable(trimmed, result)) {
      return 'Listo';
    }
    if (_sttConfig.serverEnabled) {
      return 'Calidad baja';
    }
    return 'Revisá el texto';
  }

  void _clearCaptureDraft({bool deleteLocalPending = true}) {
    final pendingId = _activePendingId;
    setState(() {
      _captureReview = null;
      _lastAnalysis = null;
      _draftText = null;
      _stagedItemIds = {};
      _lastDictation = null;
      _sttStatus = '';
      _pendingAudioPath = null;
      _activePendingId = null;
      _chatController.clear();
    });
    if (deleteLocalPending && pendingId != null) {
      _pendingStore.delete(pendingId).then((_) => _loadPendingCaptures());
    }
  }

  Future<PendingEncounterCapture?> _persistLocalDraft({
    required String texto,
    PendingEncounterCaptureStatus status =
        PendingEncounterCaptureStatus.draft,
    String? error,
    Map<String, dynamic>? analysisResponse,
    List<String>? stagedItemIds,
    int? serverCaptureId,
    String? serverStage,
    bool? audioUploaded,
    bool clearError = false,
    bool clearAnalysis = false,
  }) async {
    if (!_tieneContextoCaptura) return null;
    final now = DateTime.now();
    final id = _activePendingId ?? _pendingStore.newId();
    String? audioName;
    final existing = await _pendingStore.getById(id);
    audioName = existing?.audioFileName;

    final tempAudio = _pendingAudioPath;
    if (tempAudio != null && tempAudio.isNotEmpty) {
      final alreadyDurable = !kIsWeb &&
          tempAudio.contains('/pending-encounters/');
      if (!alreadyDurable) {
        try {
          final imported = await _pendingStore.importAudioFile(
            captureId: id,
            sourcePath: tempAudio,
          );
          if (imported != null) {
            audioName = imported;
            if (!kIsWeb) {
              final durable = await _pendingStore.absoluteAudioPath(
                PendingEncounterCapture(
                  id: id,
                  personaId: widget.personaId,
                  parent: widget.consultParent!,
                  parentId: widget.consultParentId!,
                  texto: texto,
                  status: status,
                  createdAt: now,
                  updatedAt: now,
                  audioFileName: imported,
                ),
              );
              if (durable != null) {
                _pendingAudioPath = durable;
              }
            }
          }
        } catch (e, st) {
          debugPrint('[captura] importAudio omitido: $e\n$st');
        }
      }
    }

    Map<String, dynamic>? sttPayload;
    final last = _lastDictation;
    if (last != null && texto.trim() == last.text.trim()) {
      sttPayload = last.toSttPayload();
    }

    final item = PendingEncounterCapture(
      id: id,
      personaId: widget.personaId,
      parent: widget.consultParent!,
      parentId: widget.consultParentId!,
      texto: texto,
      status: status,
      createdAt: existing?.createdAt ?? now,
      updatedAt: now,
      serverCaptureId: serverCaptureId ?? existing?.serverCaptureId,
      serverStage: serverStage ?? existing?.serverStage,
      audioFileName: audioName,
      stt: sttPayload ?? existing?.stt,
      lastError: clearError ? null : (error ?? existing?.lastError),
      analysisResponse: clearAnalysis
          ? null
          : (analysisResponse ?? existing?.analysisResponse),
      stagedItemIds: stagedItemIds ?? existing?.stagedItemIds ?? const [],
      audioUploaded: audioUploaded ?? existing?.audioUploaded ?? false,
    );
    await _pendingStore.upsert(item);
    _activePendingId = id;
    // Actualizar lista local sin re-sincronizar servidor (evita cuelgues / race).
    if (mounted) {
      final next = List<PendingEncounterCapture>.from(_pendingCaptures);
      final idx = next.indexWhere((e) => e.id == item.id);
      if (idx >= 0) {
        next[idx] = item;
      } else {
        next.insert(0, item);
      }
      setState(() => _pendingCaptures = next);
    }
    return item;
  }

  Future<void> _deletePendingCapture(String id) async {
    final existing = await _pendingStore.getById(id);
    try {
      if (existing?.serverCaptureId != null ||
          (existing?.id.isNotEmpty ?? false)) {
        await _encounterApi.capturaDescartar(
          clientCaptureId: id,
          captureId: existing?.serverCaptureId,
        );
      }
    } catch (_) {
      // Si no hay red, igual borramos local.
    }
    await _pendingStore.delete(id);
    if (_activePendingId == id) {
      _clearCaptureDraft(deleteLocalPending: false);
    }
    await _loadPendingCaptures();
    if (mounted) {
      _snack('Eliminada', UiIntent.neutral);
    }
  }

  Future<void> _resumePendingCapture(PendingEncounterCapture item) async {
    if (_captureBusy) return;
    var current = item;
    final needsReview = current.status ==
            PendingEncounterCaptureStatus.pendingSave ||
        current.status == PendingEncounterCaptureStatus.failedSave;
    if (needsReview && current.analysisResponse == null) {
      try {
        final detail = await _encounterApi.capturaVer(
          clientCaptureId: current.id,
          captureId: current.serverCaptureId,
        );
        final capture = detail['capture'];
        if (capture is Map) {
          current = PendingEncounterCapture.fromServerCapture(
            Map<String, dynamic>.from(capture),
            local: current,
          );
          await _pendingStore.upsert(current);
        }
      } catch (_) {}
    }

    final audioPath = await _pendingStore.absoluteAudioPath(current);
    if (!mounted) return;

    if ((current.status == PendingEncounterCaptureStatus.pendingSave ||
            current.status == PendingEncounterCaptureStatus.failedSave) &&
        current.analysisResponse != null) {
      final review =
          EncounterCaptureAnalysis.fromApiResponse(current.analysisResponse!);
      setState(() {
        _activePendingId = current.id;
        _pendingAudioPath = audioPath;
        _lastAnalysis = current.analysisResponse;
        _draftText = current.texto;
        _captureReview = review;
        _stagedItemIds = current.stagedItemIds.isNotEmpty
            ? current.stagedItemIds.toSet()
            : review.allItems.map((e) => e.id).toSet();
        _chatController.clear();
        _sttStatus = current.status == PendingEncounterCaptureStatus.failedSave
            ? (current.lastError?.trim().isNotEmpty == true
                ? current.lastError!
                : 'No se pudo guardar')
            : '';
      });
      return;
    }

    setState(() {
      _activePendingId = current.id;
      _pendingAudioPath = audioPath;
      _captureReview = null;
      _lastAnalysis = null;
      _draftText = null;
      _stagedItemIds = {};
      _chatController.text = current.texto.startsWith('(audio')
          ? ''
          : current.texto;
      _sttStatus = current.lastError != null && current.lastError!.isNotEmpty
          ? current.lastError!
          : '';
    });
  }

  Future<void> _retryPendingCapture(PendingEncounterCapture item) async {
    if (_captureBusy) return;
    await _resumePendingCapture(item);
    if (!mounted) return;
    if (item.status == PendingEncounterCaptureStatus.pendingSave ||
        item.status == PendingEncounterCaptureStatus.failedSave) {
      if (_captureReview != null) {
        if (item.status == PendingEncounterCaptureStatus.failedSave) {
          await _confirmarGuardado();
        }
        // pendingSave: ya quedó en revisión; el usuario confirma desde la barra.
      }
      return;
    }
    await _analizarConsulta(
      fromStatus: item.status,
      forcePipeline: true,
    );
  }

  /// Acción primaria del card pendiente según checkpoint.
  String _pendingPrimaryActionLabel(PendingEncounterCapture item) {
    switch (item.status) {
      case PendingEncounterCaptureStatus.draft:
        return 'Continuar';
      case PendingEncounterCaptureStatus.pendingUpload:
        return 'Subir';
      case PendingEncounterCaptureStatus.pendingStt:
        return 'Transcribir';
      case PendingEncounterCaptureStatus.pendingAnalyze:
        return 'Analizar';
      case PendingEncounterCaptureStatus.pendingSave:
        return 'Revisar';
      case PendingEncounterCaptureStatus.failedSave:
        return 'Guardar';
    }
  }

  Future<void> _onPendingPrimaryAction(PendingEncounterCapture item) async {
    if (_captureBusy) return;
    switch (item.status) {
      case PendingEncounterCaptureStatus.pendingSave:
        await _resumePendingCapture(item);
        return;
      case PendingEncounterCaptureStatus.failedSave:
        await _retryPendingCapture(item);
        return;
      case PendingEncounterCaptureStatus.draft:
      case PendingEncounterCaptureStatus.pendingUpload:
      case PendingEncounterCaptureStatus.pendingStt:
      case PendingEncounterCaptureStatus.pendingAnalyze:
        await _retryPendingCapture(item);
        return;
    }
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
    if (draft.trim().isNotEmpty && _tieneContextoCaptura) {
      _persistLocalDraft(
        texto: draft.trim(),
        status: PendingEncounterCaptureStatus.draft,
        clearAnalysis: true,
        clearError: true,
      );
    }
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

  Future<void> _analizarConsulta({
    PendingEncounterCaptureStatus? fromStatus,
    bool forcePipeline = false,
  }) async {
    final text = _chatController.text.trim();
    if (text.isEmpty && _pendingAudioPath == null) {
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

    final startStatus = fromStatus ??
        (text.isEmpty
            ? PendingEncounterCaptureStatus.pendingUpload
            : PendingEncounterCaptureStatus.pendingAnalyze);

    try {
      // Primero persistir en el dispositivo (texto + audio); luego pipeline servidor.
      final draft = await _persistLocalDraft(
        texto: text.isEmpty ? '(audio pendiente de transcribir)' : text,
        status: startStatus,
        clearError: true,
        clearAnalysis: true,
      );
      if (draft == null) {
        return;
      }

      final ctx = await _operationalContextForCapture();
      final last = _lastDictation;
      Map<String, dynamic>? sttMeta;
      var needsServerStt = text.isEmpty;
      if (last != null && text.isNotEmpty && text == last.text.trim()) {
        sttMeta = last.toSttPayload();
        needsServerStt = !DeviceSttLocalQuality.isAcceptable(text, last);
      } else if (text.isEmpty) {
        needsServerStt = true;
      }

      var stage = draft.serverStage;
      var serverId = draft.serverCaptureId;
      var audioUploaded = draft.audioUploaded;
      var transcript = text;

      final skipUpload = forcePipeline &&
          (fromStatus == PendingEncounterCaptureStatus.pendingStt ||
              fromStatus == PendingEncounterCaptureStatus.pendingAnalyze) &&
          (draft.serverCaptureId != null || draft.audioUploaded);

      if (!skipUpload) {
        final audioPath = await _pendingStore.absoluteAudioPath(draft) ??
            _pendingAudioPath;
        try {
          final created = await _encounterApi.capturaCrearOSubir(
            clientCaptureId: draft.id,
            idPersona: widget.personaId,
            parent: widget.consultParent,
            parentId: widget.consultParentId,
            texto: text.isEmpty ? null : text,
            stt: sttMeta,
            audioPath: audioPath,
            sttForceServer: needsServerStt && (audioPath != null),
            userPerTabConfig: ctx,
          );
          final capture = created['capture'];
          if (capture is Map) {
            final map = Map<String, dynamic>.from(capture);
            stage = map['stage']?.toString();
            serverId = map['id'] is int
                ? map['id'] as int
                : int.tryParse('${map['id']}');
            audioUploaded = map['has_audio'] == true;
            final t = map['transcript']?.toString() ?? '';
            if (t.isNotEmpty) transcript = t;
          }
          await _persistLocalDraft(
            texto: transcript.isEmpty
                ? '(audio pendiente de transcribir)'
                : transcript,
            status: PendingEncounterCapture.statusFromServerStage(
              stage ?? 'UPLOADED',
            ),
            serverCaptureId: serverId,
            serverStage: stage,
            audioUploaded: audioUploaded,
            clearError: true,
          );
        } catch (e) {
          await _persistLocalDraft(
            texto: text.isEmpty ? '(audio pendiente de transcribir)' : text,
            status: PendingEncounterCaptureStatus.pendingUpload,
            error: _mensajeErrorCaptura(e),
          );
          if (!mounted) return;
          _snack(_mensajeErrorCaptura(e), UiIntent.danger);
          return;
        }
      }

      final needsStt = stage == 'UPLOADED' ||
          stage == 'STT_FAILED' ||
          (needsServerStt && transcript.isEmpty) ||
          fromStatus == PendingEncounterCaptureStatus.pendingStt;

      if (needsStt &&
          fromStatus != PendingEncounterCaptureStatus.pendingAnalyze) {
        try {
          final sttRes = await _encounterApi.capturaTranscribir(
            clientCaptureId: draft.id,
            captureId: serverId,
            force: stage == 'STT_FAILED',
            userPerTabConfig: ctx,
          );
          final capture = sttRes['capture'];
          if (capture is Map) {
            final map = Map<String, dynamic>.from(capture);
            stage = map['stage']?.toString();
            serverId = map['id'] is int
                ? map['id'] as int
                : int.tryParse('${map['id']}');
            final t = map['transcript']?.toString() ?? '';
            if (t.isNotEmpty) {
              transcript = t;
              if (mounted) {
                setState(() => _chatController.text = t);
              }
            }
          }
          await _persistLocalDraft(
            texto: transcript,
            status: PendingEncounterCapture.statusFromServerStage(
              stage ?? 'TRANSCRIBED',
            ),
            serverCaptureId: serverId,
            serverStage: stage,
            audioUploaded: true,
            clearError: true,
          );
        } catch (e) {
          await _persistLocalDraft(
            texto: transcript.isEmpty
                ? '(audio pendiente de transcribir)'
                : transcript,
            status: PendingEncounterCaptureStatus.pendingStt,
            serverCaptureId: serverId,
            serverStage: stage ?? 'STT_FAILED',
            audioUploaded: audioUploaded,
            error: _mensajeErrorCaptura(e),
          );
          if (!mounted) return;
          _snack(_mensajeErrorCaptura(e), UiIntent.danger);
          return;
        }
      }

      if (transcript.trim().isEmpty) {
        await _persistLocalDraft(
          texto: '(audio pendiente de transcribir)',
          status: PendingEncounterCaptureStatus.pendingStt,
          serverCaptureId: serverId,
          serverStage: stage,
          error: 'Sin texto para analizar.',
        );
        if (!mounted) return;
        _snack('No hay texto para analizar.', UiIntent.warning);
        return;
      }

      final analyzed = await _encounterApi.capturaAnalizar(
        clientCaptureId: draft.id,
        captureId: serverId,
        consulta: transcript,
        force: fromStatus == PendingEncounterCaptureStatus.pendingAnalyze,
        userPerTabConfig: ctx,
      );
      final capture = analyzed['capture'];
      Map<String, dynamic> analysisPayload = analyzed;
      if (capture is Map) {
        final map = Map<String, dynamic>.from(capture);
        stage = map['stage']?.toString();
        serverId = map['id'] is int
            ? map['id'] as int
            : int.tryParse('${map['id']}');
        // Preferir payload completo del análisis (incluye capture_review).
        if (map['analysis'] is Map) {
          analysisPayload = Map<String, dynamic>.from(map['analysis'] as Map);
        }
        // Completar con campos top-level del capture si faltan.
        for (final key in [
          'capture_review',
          'texto_original',
          'texto_procesado',
          'datosExtraidos',
          'analysis_cache_token',
          'id_configuracion',
          'encounter_id',
          'id_consulta',
          'puede_confirmar',
          'tiene_datos_faltantes',
        ]) {
          if (!analysisPayload.containsKey(key) && map[key] != null) {
            analysisPayload[key] = map[key];
          }
        }
        // Legacy: datos.datosExtraidos → datosExtraidos top-level.
        final datos = analysisPayload['datos'];
        if (datos is Map &&
            analysisPayload['datosExtraidos'] == null &&
            datos['datosExtraidos'] != null) {
          analysisPayload['datosExtraidos'] = datos['datosExtraidos'];
        }
      }

      if (!mounted) return;
      final review = EncounterCaptureAnalysis.fromApiResponse(analysisPayload);
      final staged = review.allItems.map((e) => e.id).toSet();
      if (staged.isEmpty && review.defaultStagedItemIds.isNotEmpty) {
        staged.addAll(review.defaultStagedItemIds);
      }
      await _persistLocalDraft(
        texto: transcript,
        status: PendingEncounterCaptureStatus.pendingSave,
        analysisResponse: analysisPayload,
        stagedItemIds: staged.toList(),
        serverCaptureId: serverId,
        serverStage: stage ?? 'READY_FOR_REVIEW',
        audioUploaded: audioUploaded,
        clearError: true,
      );
      setState(() {
        _lastAnalysis = analysisPayload;
        _draftText = transcript;
        _captureReview = review;
        _stagedItemIds = staged;
        _chatController.clear();
        _sttStatus = '';
      });
    } catch (e, st) {
      debugPrint('[captura] analizar falló: $e\n$st');
      try {
        await _persistLocalDraft(
          texto: text.isEmpty ? '(audio pendiente de transcribir)' : text,
          status: PendingEncounterCaptureStatus.pendingAnalyze,
          error: _mensajeErrorCaptura(e),
        );
      } catch (_) {}
      if (!mounted) return;
      _snack(_mensajeErrorCaptura(e), UiIntent.danger);
    } finally {
      if (mounted) setState(() => _isAnalyzing = false);
    }
  }

  Future<void> _confirmarGuardado() async {
    final review = _captureReview;
    if (review == null || _lastAnalysis == null) {
      _snack('Analizá antes de guardar.', UiIntent.warning);
      return;
    }
    if (review.systemError != null || !review.puedeConfirmar) {
      _snack('No se puede guardar: el análisis tiene errores.', UiIntent.warning);
      return;
    }
    if (review.tieneDatosFaltantes) {
      _snack(
        review.datosFaltantesMensaje?.trim().isNotEmpty == true
            ? review.datosFaltantesMensaje!
            : 'Faltan categorías o campos obligatorios. Completá el texto y volvé a analizar.',
        UiIntent.warning,
      );
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
      // Persistir payload localmente antes de subir (reintento si falla la red).
      await _persistLocalDraft(
        texto: textoOriginal,
        status: PendingEncounterCaptureStatus.pendingSave,
        analysisResponse: _lastAnalysis,
        stagedItemIds: _stagedItemIds.toList(),
        clearError: true,
      );

      final clientId = _activePendingId;
      Map<String, dynamic> guardado;
      if (clientId != null && clientId.isNotEmpty) {
        final out = await _encounterApi.capturaGuardar(
          clientCaptureId: clientId,
          datosExtraidos: extraidos,
          analisisDatosExtraidos: analisisBackup,
          stagedItemIds: _stagedItemIds.toList(),
          textoOriginal: textoOriginal,
          textoProcesado: textoProcesado,
          userPerTabConfig: await _operationalContextForCapture(),
        );
        final nested = out['guardar'];
        guardado = nested is Map
            ? Map<String, dynamic>.from(nested)
            : out;
      } else {
        guardado = await _encounterApi.guardar(
          idPersona: widget.personaId,
          parent: widget.consultParent,
          parentId: widget.consultParentId,
          datosExtraidos: extraidos,
          analisisDatosExtraidos: analisisBackup,
          analysisCacheToken: _lastAnalysis!['analysis_cache_token']?.toString(),
          textoOriginal: textoOriginal,
          textoProcesado: textoProcesado,
          idConfiguracion: idConfiguracion,
          encounterId: encounterId,
          userPerTabConfig: await _operationalContextForCapture(),
        );
      }
      if (!mounted) return;
      final aviso = _mensajePersistidoIncompleto(
            extraidos,
            guardado,
            analisisBackup: analisisBackup,
          ) ??
          (guardado['persist_incomplete'] == true
              ? (guardado['message']?.toString() ??
                  'Encounter guardado con datos incompletos.')
              : null);
      debugPrint(
        '[captura] guardar resultado aviso=${aviso ?? 'ok'} '
        'persistido=${guardado['persistido']} '
        'diagnostico=${guardado['diagnostico_guardar']} '
        'log_id=${guardado['log_id']}',
      );
      if (aviso != null) {
        // No hacer pop inmediato: el SnackBar se pierde al cerrar la pantalla.
        await _mostrarResultadoGuardado(
          titulo: 'Guardado incompleto',
          mensaje: aviso,
          intent: UiIntent.warning,
          detalle: _detalleDiagnosticoGuardado(guardado),
        );
      } else {
        _snack('Consulta guardada', UiIntent.success);
      }
      final doneId = _activePendingId;
      _clearCaptureDraft(deleteLocalPending: false);
      if (doneId != null) {
        await _pendingStore.delete(doneId);
        await _loadPendingCaptures();
      }
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } catch (e, st) {
      debugPrint('[captura] Error al guardar: $e\n$st');
      await _persistLocalDraft(
        texto: textoOriginal,
        status: PendingEncounterCaptureStatus.failedSave,
        analysisResponse: _lastAnalysis,
        stagedItemIds: _stagedItemIds.toList(),
        error: _mensajeErrorCaptura(e),
      );
      if (!mounted) return;
      await _mostrarResultadoGuardado(
        titulo: 'No se pudo guardar',
        mensaje: _mensajeErrorCaptura(e),
        intent: UiIntent.danger,
      );
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  /// Si el cliente mandó medicación y el backend no creó filas, avisar (deploy/stage).
  String? _mensajePersistidoIncompleto(
    Map<String, dynamic> extraidos,
    Map<String, dynamic> guardado, {
    Map<String, dynamic>? analisisBackup,
  }) {
    final persistido = guardado['persistido'];
    if (persistido is! Map) return null;
    const medKeys = [
      'Medicación',
      'Medicacion',
      'ConsultaMedicamentos',
    ];
    const motivoKeys = [
      'Motivos de consulta',
      'ConsultaMotivos',
    ];
    final expectedMeds = _categoryHasRows(extraidos, medKeys) ||
        _categoryHasRows(analisisBackup ?? const {}, medKeys) ||
        (_captureReview?.categories.any(
              (c) =>
                  (c.title.toLowerCase().contains('medic') ||
                      c.model == 'ConsultaMedicamentos') &&
                  c.items.isNotEmpty,
            ) ??
            false);
    final expectedMotivos = _categoryHasRows(extraidos, motivoKeys) ||
        _categoryHasRows(analisisBackup ?? const {}, motivoKeys);
    final meds = persistido['medication_requests'];
    final medCount = meds is int ? meds : int.tryParse('$meds') ?? 0;
    final srs = persistido['service_requests'];
    final srCount = srs is int ? srs : int.tryParse('$srs') ?? 0;
    if (expectedMeds && medCount <= 0) {
      final diag = guardado['diagnostico_guardar'];
      final fuente = diag is Map ? diag['backup_fuentes'] : null;
      final staged = diag is Map ? diag['staged_counts'] : null;
      final finalCounts = diag is Map ? diag['final_counts'] : null;
      return 'La medicación no quedó persistida.\n'
          'staged=$staged\nfinal=$finalCounts\nbackup=$fuente\n'
          'log_id=${guardado['log_id'] ?? '-'}';
    }
    if (persistido['note'] != true) {
      return 'La nota clínica no quedó en el encounter.';
    }
    if (persistido['reason_text'] != true && expectedMotivos) {
      return 'Los motivos no quedaron en reason_text '
          '(log_id=${guardado['log_id'] ?? '-'}).';
    }
    // Prácticas/indicaciones esperadas en el análisis pero sin service_request.
    final expectedSr = _categoryHasRows(extraidos, const [
          'Indicaciones',
          'ConsultaIndicaciones',
          'Prácticas realizadas',
          'Practicas realizadas',
          'ConsultaPracticas',
        ]) ||
        _categoryHasRows(analisisBackup ?? const {}, const [
          'Indicaciones',
          'ConsultaIndicaciones',
          'Prácticas realizadas',
          'Practicas realizadas',
          'ConsultaPracticas',
        ]);
    if (expectedSr && srCount <= 0) {
      return 'Indicaciones/prácticas no quedaron persistidas '
          '(service_requests=0, log_id=${guardado['log_id'] ?? '-'}).';
    }
    return null;
  }

  String _detalleDiagnosticoGuardado(Map<String, dynamic> guardado) {
    final parts = <String>[];
    final persistido = guardado['persistido'];
    if (persistido is Map) {
      parts.add('persistido: $persistido');
    }
    final diag = guardado['diagnostico_guardar'];
    if (diag is Map) {
      parts.add('staged: ${diag['staged_counts']}');
      parts.add('final: ${diag['final_counts']}');
      parts.add('backup: ${diag['backup_fuentes']}');
      parts.add('cache: ${diag['cache']}');
      parts.add('por_modelo: ${diag['por_modelo']}');
    }
    if (guardado['log_id'] != null) {
      parts.add('log_id: ${guardado['log_id']}');
    }
    return parts.join('\n');
  }

  Future<void> _mostrarResultadoGuardado({
    required String titulo,
    required String mensaje,
    required UiIntent intent,
    String? detalle,
  }) async {
    if (!mounted) return;
    final palette = IntentPalette.of(intent);
    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) {
        return AlertDialog(
          title: Text(titulo),
          content: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(mensaje),
                if (detalle != null && detalle.trim().isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Text(
                    detalle,
                    style: Theme.of(ctx).textTheme.bodySmall,
                  ),
                ],
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(ctx).pop(),
              style: TextButton.styleFrom(foregroundColor: palette.base),
              child: const Text('Entendido'),
            ),
          ],
        );
      },
    );
  }

  bool _categoryHasRows(Map<String, dynamic> extraidos, List<String> keys) {
    for (final key in keys) {
      final v = extraidos[key];
      if (v is List && v.isNotEmpty) return true;
      if (v is Map && v.isNotEmpty) return true;
      if (v is String && v.trim().isNotEmpty) return true;
    }
    return false;
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

  Widget _buildPendingCapturesPanel() {
    // Con análisis listo: se muestra como "Nota de esta atención", no como card.
    final items = _pendingCaptures.where((e) {
      if (_enRevisionCaptura && e.id == _activePendingId) return false;
      final ready = e.status == PendingEncounterCaptureStatus.pendingSave ||
          e.status == PendingEncounterCaptureStatus.failedSave;
      if (ready && e.analysisResponse != null) return false;
      return true;
    }).toList();
    if (items.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final item in items) ...[
          _buildPendingCaptureRow(item),
          if (item != items.last) BioSpacing.gapH(BioSpacing.sm),
        ],
      ],
    );
  }

  String _pendingStatusLabel(PendingEncounterCaptureStatus status) {
    switch (status) {
      case PendingEncounterCaptureStatus.draft:
        return 'Borrador';
      case PendingEncounterCaptureStatus.pendingUpload:
        return 'Sin subir';
      case PendingEncounterCaptureStatus.pendingStt:
        return 'Sin transcribir';
      case PendingEncounterCaptureStatus.pendingAnalyze:
        return 'Sin analizar';
      case PendingEncounterCaptureStatus.pendingSave:
        return 'Para revisar';
      case PendingEncounterCaptureStatus.failedSave:
        return 'No guardado';
    }
  }

  UiIntent _pendingStatusIntent(PendingEncounterCaptureStatus status) {
    switch (status) {
      case PendingEncounterCaptureStatus.failedSave:
      case PendingEncounterCaptureStatus.pendingUpload:
      case PendingEncounterCaptureStatus.pendingStt:
      case PendingEncounterCaptureStatus.pendingAnalyze:
        return UiIntent.warning;
      case PendingEncounterCaptureStatus.pendingSave:
        return UiIntent.info;
      case PendingEncounterCaptureStatus.draft:
        return UiIntent.neutral;
    }
  }

  Widget _buildPendingCaptureRow(PendingEncounterCapture item) {
    final previewRaw = item.texto.trim();
    final preview = previewRaw.isEmpty || previewRaw.startsWith('(audio')
        ? (item.hasAudio ? 'Audio' : 'Sin texto')
        : (previewRaw.length > 90
            ? '${previewRaw.substring(0, 90)}…'
            : previewRaw);
    final intent = _pendingStatusIntent(item.status);
    final primary = _pendingPrimaryActionLabel(item);

    return BioCard.intent(
      intent: intent,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(_pendingStatusLabel(item.status), style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.xs),
          Text(preview, style: BioTypography.body),
          if (item.lastError != null &&
              item.lastError!.trim().isNotEmpty &&
              item.status != PendingEncounterCaptureStatus.pendingSave) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              item.lastError!,
              style: BioTypography.caption.copyWith(
                color: IntentPalette.of(UiIntent.danger).base,
              ),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ],
          BioSpacing.gapH(BioSpacing.sm),
          Row(
            children: [
              Expanded(
                child: BioButton(
                  label: primary,
                  intent: UiIntent.primary,
                  variant: BioButtonVariant.soft,
                  onPressed: _captureBusy
                      ? null
                      : () => _onPendingPrimaryAction(item),
                ),
              ),
              BioSpacing.gapW(BioSpacing.sm),
              BioButton(
                label: 'Eliminar',
                intent: UiIntent.danger,
                variant: BioButtonVariant.soft,
                onPressed:
                    _captureBusy ? null : () => _deletePendingCapture(item.id),
              ),
            ],
          ),
        ],
      ),
    );
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
              'Analizando…',
              style: BioTypography.body,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCaptureReviewPanel(EncounterCaptureAnalysis review) {
    final sectionTitleStyle = BioTypography.title;
    final categoryTitleStyle = BioTypography.bodySm.copyWith(
      fontWeight: FontWeight.w600,
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Center(
          child: Text(
            'Nota de esta atención',
            textAlign: TextAlign.center,
            style: BioTypography.title.copyWith(
              decoration: TextDecoration.underline,
            ),
          ),
        ),
        BioSpacing.gapH(BioSpacing.md),
        Text('Texto registrado', style: sectionTitleStyle),
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
          Text('Texto procesado', style: sectionTitleStyle),
          BioSpacing.gapH(BioSpacing.xs),
          Text(review.textoProcesado!, style: BioTypography.bodySm),
        ],
        if (review.systemError != null) ...[
          BioSpacing.gapH(BioSpacing.md),
          BioAlert.danger(message: review.systemError!),
        ] else if (!review.hasExtractedContent) ...[
          BioSpacing.gapH(BioSpacing.md),
          BioAlert.info(
            message: 'Sin datos estructurados. Se guardará el texto.',
          ),
        ] else ...[
          BioSpacing.gapH(BioSpacing.lg),
          Text('Resultado del procesamiento', style: sectionTitleStyle),
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
                        child: Text(cat.title, style: categoryTitleStyle),
                      ),
                      if (cat.required) BioBadge.danger('Requerido'),
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
            message: review.datosFaltantesMensaje?.trim().isNotEmpty == true
                ? review.datosFaltantesMensaje!
                : 'Faltan categorías o campos obligatorios. Completá el dictado/texto (dosis, frecuencia, etc.) y volvé a analizar. No se puede confirmar hasta completarlos.',
          ),
        ],
      ],
    );
  }

  Widget _buildCaptureActionsBar() {
    final review = _captureReview;
    final canConfirm = !_isSaving &&
        review != null &&
        review.puedeConfirmar &&
        review.systemError == null &&
        review.textoOriginal.trim().isNotEmpty &&
        !review.tieneDatosFaltantes &&
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
          BioButton.primary(
            label: _isSaving ? 'Guardando…' : 'Guardar',
            icon: _isSaving ? null : Icons.check,
            onPressed: canConfirm ? _confirmarGuardado : null,
          ),
          BioSpacing.gapH(BioSpacing.sm),
          Row(
            children: [
              Expanded(
                child: BioButton(
                  label: 'Editar texto',
                  intent: UiIntent.neutral,
                  variant: BioButtonVariant.soft,
                  onPressed: _isSaving ? null : _editCaptureDraft,
                ),
              ),
              BioSpacing.gapW(BioSpacing.sm),
              Expanded(
                child: BioButton(
                  label: 'Eliminar',
                  intent: UiIntent.danger,
                  variant: BioButtonVariant.soft,
                  onPressed: _isSaving ? null : _clearCaptureDraft,
                ),
              ),
            ],
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
