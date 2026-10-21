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
import '../services/emergency_guardia_api.dart';
import '../services/pending_encounter_capture_store.dart';
import 'emergency/emergency_triage_screen.dart';
import 'emergency/manchester_triage_colors.dart';

class PatientTimelineScreen extends StatefulWidget {
  final int personaId;
  final String? authToken;

  /// true = solo ver historia clínica (sin formulario); false = barra para escribir notas
  final bool soloVer;

  /// Minimal view of a saved encounter (patient and professional details only).
  final bool encounterSummaryLoaded;

  /// Domain parent context aligned with web / `validarPermisoAtencion`.
  final String? parentType;
  final int? parentId;

  const PatientTimelineScreen({
    Key? key,
    required this.personaId,
    this.authToken,
    this.soloVer = true,
    this.encounterSummaryLoaded = false,
    this.parentType,
    this.parentId,
  }) : super(key: key);

  @override
  State<PatientTimelineScreen> createState() => _PatientTimelineScreenState();
}

class _PatientTimelineScreenState extends State<PatientTimelineScreen> {
  final HistoriaClinicaService _historiaClinicaService =
      HistoriaClinicaService();
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
  /// issue_id → valor elegido (ninguno viene preseleccionado).
  Map<String, dynamic> _issueResolutions = {};
  /// open problem key `condition:123` / `care_plan:456` → value.
  Map<String, dynamic> _openProblemResolutions = {};
  final Map<String, TextEditingController> _issueCustomControllers = {};
  String _sttStatus = '';
  SttClientConfig _sttConfig = SttClientConfig.defaults;
  bool _sttConfigLoaded = false;
  bool _audioOnlyRecording = false;
  bool _editingCaptureDraft = false;
  EncounterCaptureAnalysis? _editBackupReview;
  Map<String, dynamic>? _editBackupLastAnalysis;
  String _editBackupDraft = '';
  Set<String> _editBackupStagedItemIds = {};
  Map<String, dynamic> _editBackupIssueResolutions = {};
  Map<String, dynamic> _editBackupOpenProblemResolutions = {};
  final PendingEncounterCaptureStore _pendingStore =
      PendingEncounterCaptureStore.instance;
  List<PendingEncounterCapture> _pendingCaptures = [];
  String? _activePendingId;

  final TextEditingController _chatController = TextEditingController();
  final FocusNode _chatFocusNode = FocusNode();

  bool get _captureBusy => _isAnalyzing || _isSaving;

  bool get _enRevisionCaptura => _captureReview != null;
  bool get _showCaptureBar {
    if (widget.encounterSummaryLoaded) return false;
    final capturaApi = _historiaClinicaData?.capturaPermitida;
    if (capturaApi == false) return false;
    return !widget.soloVer ||
        (widget.parentType != null &&
            widget.parentType!.isNotEmpty &&
            widget.parentId != null);
  }

  bool get _isLoadedEncounterSummary =>
      widget.encounterSummaryLoaded ||
      _historiaClinicaData?.capturaPermitida == false;

  String _tlEpisodioFilter = 'all';

  bool get _esCapturaInternacion =>
      (widget.parentType ?? '').toUpperCase() == EncounterParentType.internacion;

  bool get _esCapturaGuardia =>
      (widget.parentType ?? '').toUpperCase() == EncounterParentType.guardia;

  bool get _esCapturaEpisodio => _esCapturaInternacion || _esCapturaGuardia;

  bool _autoOpenedReview = false;

  @override
  void initState() {
    super.initState();
    if (widget.authToken != null) {
      _historiaClinicaService.authToken = widget.authToken;
      _encounterApi.authToken = widget.authToken;
    }
    _loadSttConfig();
    _cargarHistoriaClinica();
    _loadPendingCaptures();
  }

  Future<void> _loadPendingCaptures() async {
    if (!_tieneContextoCaptura) return;
    final local = await _pendingStore.listForContext(
      personaId: widget.personaId,
      parent: widget.parentType!,
      parentId: widget.parentId!,
    );
    final byId = <String, PendingEncounterCapture>{
      for (final item in local) item.id: item,
    };
    try {
      final remote = await _encounterApi.capturaListar(
        idPersona: widget.personaId,
        parent: widget.parentType,
        parentId: widget.parentId,
      );
      for (final row in remote) {
        final clientId = row['client_capture_id']?.toString() ?? '';
        if (clientId.isEmpty) continue;
        var merged = PendingEncounterCapture.fromServerCapture(
          row,
          local: byId[clientId],
        );
        // listar es liviano: si hace falta revisión y no hay análisis local, pedir /ver.
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
      setState(() {
        _sttConfig = cfg;
        _sttConfigLoaded = true;
        _sttStatus = '';
      });
      debugPrint(
        '[stt-config] loaded device=${cfg.deviceEnabled} '
        'source=${cfg.deviceEnabledSource} server=${cfg.serverEnabled} '
        'serverConfigured=${cfg.serverConfigured} '
        'provider=${cfg.proveedorServidor}',
      );
    } catch (e, st) {
      debugPrint('[stt-config] load failed: $e\n$st');
      if (!mounted) return;
      setState(() {
        _sttConfig = SttClientConfig.unavailable;
        _sttConfigLoaded = true;
        _sttStatus = 'No se pudo cargar la configuración de voz. Dictado deshabilitado.';
      });
    }
  }

  @override
  void dispose() {
    _chatController.dispose();
    _chatFocusNode.dispose();
    for (final c in _issueCustomControllers.values) {
      c.dispose();
    }
    _issueCustomControllers.clear();
    super.dispose();
  }

  Future<void> _cargarHistoriaClinica() async {
    setState(() {
      _isLoading = true;
      _errorMessage = '';
    });

    try {
      final int? turnoId = widget.parentType == EncounterParentType.turno
          ? widget.parentId
          : null;
      final HistoriaClinicaResponse data;
      if (widget.encounterSummaryLoaded) {
        data = await _historiaClinicaService.getEncounterAsStaff(
          turnoId: turnoId,
          encounterId: widget.parentType == EncounterParentType.encounter
              ? widget.parentId
              : null,
        );
      } else {
        data = await _historiaClinicaService.getHistoriaClinica(
          widget.personaId,
          turnoId: turnoId,
          encounterId: widget.parentType == EncounterParentType.encounter
              ? widget.parentId
              : null,
          parent: (turnoId == null &&
                  widget.parentType != null &&
                  widget.parentType != EncounterParentType.encounter)
              ? widget.parentType
              : null,
          parentId: (turnoId == null &&
                  widget.parentType != null &&
                  widget.parentType != EncounterParentType.encounter)
              ? widget.parentId
              : null,
        );
      }
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
    return 'Error al cargar historia clínica: ${userFriendlyErrorMessage(e)}';
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: BioAppBar(
        title: _isLoadedEncounterSummary
            ? 'Consulta cargada'
            : (_esCapturaGuardia
                ? 'Atención · guardia'
                : (_esCapturaInternacion
                    ? 'Evolución · internación'
                    : 'Historia clínica')),
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
                            padding: const EdgeInsets.symmetric(
                              horizontal: BioSpacing.sm,
                              vertical: BioSpacing.md,
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                _buildPacienteHeader(
                                    _historiaClinicaData!.persona),
                                if (_isLoadedEncounterSummary) ...[
                                  if (_historiaClinicaData!
                                      .documentacionMedico.tieneDatos) ...[
                                    BioSpacing.gapH(BioSpacing.md),
                                    _buildDocumentacionMedico(
                                      _historiaClinicaData!.documentacionMedico,
                                    ),
                                  ],
                                  if (_historiaClinicaData!
                                          .patientEncounterReasons
                                          .motivosIntake
                                          ?.tieneContenido ==
                                      true) ...[
                                    BioSpacing.gapH(BioSpacing.md),
                                    _buildMotivosIntake(
                                      _historiaClinicaData!
                                          .patientEncounterReasons
                                          .motivosIntake!,
                                    ),
                                  ],
                                  if ((_historiaClinicaData!
                                              .patientEncounterReasons
                                              .resumen ??
                                          '')
                                      .trim()
                                      .isNotEmpty) ...[
                                    BioSpacing.gapH(BioSpacing.md),
                                    _buildEncounterReasons(
                                        _historiaClinicaData!),
                                  ],
                                  if (_historiaClinicaData!.carePackCohorte
                                          ?.tieneContenido ==
                                      true) ...[
                                    BioSpacing.gapH(BioSpacing.md),
                                    _buildCarePackCohorte(
                                      _historiaClinicaData!.carePackCohorte!,
                                    ),
                                  ],
                                ] else ...[
                                  if (_historiaClinicaData!
                                      .documentacionMedico.tieneDatos) ...[
                                    BioSpacing.gapH(BioSpacing.md),
                                    _buildDocumentacionMedico(
                                      _historiaClinicaData!.documentacionMedico,
                                    ),
                                  ],
                                  BioSpacing.gapH(BioSpacing.md),
                                  _buildInformacionMedica(
                                      _historiaClinicaData!.informacionMedica),
                                  BioSpacing.gapH(BioSpacing.md),
                                  if (_esCapturaEpisodio)
                                    _buildSignosVitalesEpisodio(
                                      _historiaClinicaData!.signosVitalesEpisodio,
                                    )
                                  else
                                    _buildSignosVitales(
                                        _historiaClinicaData!.signosVitales),
                                  if (_esCapturaEpisodio) ...[
                                    BioSpacing.gapH(BioSpacing.md),
                                    _buildEpisodioBanner(
                                      _historiaClinicaData!.contextoEpisodio,
                                    ),
                                    BioSpacing.gapH(BioSpacing.md),
                                    _buildTimelineEpisodio(
                                      _historiaClinicaData!.timelineEpisodio,
                                    ),
                                  ] else ...[
                                    BioSpacing.gapH(BioSpacing.md),
                                    if (_historiaClinicaData!
                                            .patientEncounterReasons
                                            .motivosIntake
                                            ?.tieneContenido ==
                                        true) ...[
                                      _buildMotivosIntake(
                                        _historiaClinicaData!
                                            .patientEncounterReasons
                                            .motivosIntake!,
                                      ),
                                      BioSpacing.gapH(BioSpacing.md),
                                    ],
                                    _buildEncounterReasons(_historiaClinicaData!),
                                    if (_historiaClinicaData!.carePackCohorte
                                            ?.tieneContenido ==
                                        true) ...[
                                      BioSpacing.gapH(BioSpacing.md),
                                      _buildCarePackCohorte(
                                        _historiaClinicaData!.carePackCohorte!,
                                      ),
                                    ],
                                  ],
                                  if (_pendingCaptures.isNotEmpty) ...[
                                    BioSpacing.gapH(BioSpacing.md),
                                    _buildPendingCapturesPanel(),
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
                        if (!_isLoadedEncounterSummary && _enRevisionCaptura)
                          _buildCaptureActionsBar(),
                        if (!_isLoadedEncounterSummary &&
                            _showCaptureBar &&
                            !_enRevisionCaptura)
                          _buildChatInputBar(context),
                      ],
                    ),
    );
  }

  Widget _buildPacienteHeader(PersonaData persona) {
    final meta = <String>[];
    if (!_isLoadedEncounterSummary && persona.edad != null) {
      meta.add('${persona.edad} años');
    }
    final genero = (persona.generoTexto ?? '').trim();
    if (!_isLoadedEncounterSummary && genero.isNotEmpty) {
      meta.add(genero);
    }

    return BioCard.intent(
      intent: UiIntent.primary,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Text(
              persona.nombreCompleto,
              style: BioTypography.h3,
            ),
          ),
          if (meta.isNotEmpty) ...[
            BioSpacing.gapW(BioSpacing.sm),
            Text(
              meta.join(' · '),
              style: BioTypography.title.copyWith(
                color: context.bio.textMuted,
              ),
              textAlign: TextAlign.right,
            ),
          ],
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
            Text(
              'Sin datos registrados en esta consulta.',
              style: BioTypography.bodySm,
            )
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

  Widget _buildSignosVitalesEpisodio(SignosVitalesEpisodio? sv) {
    final tiene = sv != null && sv.tieneDatos;
    final ultimos = sv?.ultimos ?? const <String, SignosVitalesEpisodioUltimo>{};
    final chips = <Widget>[];
    void addChip(String text) {
      chips.add(
        BioBadge(label: text, intent: UiIntent.neutral),
      );
    }

    final ta = ultimos['ta'];
    if (ta != null && (ta.sistolica != null || ta.diastolica != null)) {
      addChip(
        'TA ${(ta.sistolica ?? '—')}/${(ta.diastolica ?? '—')}'
        '${ta.at != null ? ' · ${ta.at}' : ''}',
      );
    }
    for (final key in ['fc', 'fr', 'sat_o2', 'temp', 'glucemia', 'glasgow']) {
      final u = ultimos[key];
      if (u == null || u.value == null) continue;
      final label = u.label ?? key;
      final unit = u.unit ?? '';
      addChip(
        '$label: ${u.value}${unit.isNotEmpty ? ' $unit' : ''}'
        '${u.at != null ? ' · ${u.at}' : ''}',
      );
    }

    return BioCard.intent(
      intent: UiIntent.info,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Signos vitales actuales',
                  style: BioTypography.title,
                ),
              ),
              if (sv != null && sv.totalPoints > 0)
                Text(
                  '${sv.totalPoints} medición${sv.totalPoints == 1 ? '' : 'es'}',
                  style: BioTypography.caption
                      .copyWith(color: context.bio.textMuted),
                ),
            ],
          ),
          BioSpacing.gapH(BioSpacing.md),
          if (!tiene)
            Text(
              'Sin signos vitales. Se cargan en la captura de la consulta (texto/audio) o en el triage de admisión.',
              style: BioTypography.bodySm
                  .copyWith(color: context.bio.textMuted),
            )
          else ...[
            if (chips.isNotEmpty)
              Wrap(
                spacing: BioSpacing.xs,
                runSpacing: BioSpacing.xs,
                children: chips,
              ),
            if (sv!.series.isNotEmpty) ...[
              BioSpacing.gapH(BioSpacing.md),
              ExpansionTile(
                tilePadding: EdgeInsets.zero,
                title: Text(
                  'Historial (${sv.totalPoints} mediciones)',
                  style: BioTypography.title,
                ),
                children: [
                  for (final s in sv.series) ...[
                    Padding(
                      padding: const EdgeInsets.only(bottom: BioSpacing.xs),
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: Text(
                          '${s.label.isNotEmpty ? s.label : s.metric}'
                          '${s.unit.isNotEmpty ? ' (${s.unit})' : ''}',
                          style: BioTypography.bodySm
                              .copyWith(fontWeight: FontWeight.w600),
                        ),
                      ),
                    ),
                    for (final p in s.points.reversed.take(5))
                      Padding(
                        padding: const EdgeInsets.only(bottom: 2),
                        child: Align(
                          alignment: Alignment.centerLeft,
                          child: Text(
                            '${p.at}: ${p.value}${s.unit.isNotEmpty ? ' ${s.unit}' : ''}'
                            '${p.source.isNotEmpty ? ' · ${p.source}' : ''}',
                            style: BioTypography.caption
                                .copyWith(color: context.bio.textMuted),
                          ),
                        ),
                      ),
                    BioSpacing.gapH(BioSpacing.xs),
                  ],
                ],
              ),
            ],
          ],
        ],
      ),
    );
  }

  Widget _buildTimelineEpisodio(TimelineEpisodioFeed? feed) {
    final all = feed?.items ?? const <TimelineEpisodioItem>[];
    final filters = <MapEntry<String, String>>[
      const MapEntry('all', 'Todos'),
      const MapEntry('clinico', 'Clínico'),
      const MapEntry('enfermeria', 'Enfermería'),
      const MapEntry('pedidos', 'Pedidos'),
      const MapEntry('farmacos', 'Fármacos'),
      const MapEntry('circuito', 'Circuito'),
    ];
    Set<String>? allowed;
    switch (_tlEpisodioFilter) {
      case 'clinico':
        allowed = {'evolucion_medica', 'triage'};
        break;
      case 'enfermeria':
        allowed = {'atencion_enfermeria'};
        break;
      case 'pedidos':
        allowed = {'pedido', 'resultado_lab', 'interconsulta'};
        break;
      case 'farmacos':
        allowed = {'medicacion', 'administracion'};
        break;
      case 'circuito':
        allowed = {'circuito', 'triage'};
        break;
      default:
        allowed = null;
    }
    final items = allowed == null
        ? all
        : all.where((e) => allowed!.contains(e.type)).toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text('Registro del episodio', style: BioTypography.title),
            ),
            if (all.isNotEmpty)
              Text(
                '${all.length} hito${all.length == 1 ? '' : 's'}',
                style: BioTypography.caption
                    .copyWith(color: context.bio.textMuted),
              ),
          ],
        ),
        BioSpacing.gapH(BioSpacing.sm),
        Wrap(
          spacing: BioSpacing.xs,
          runSpacing: BioSpacing.xs,
          children: [
            for (final f in filters)
              ChoiceChip(
                label: Text(f.value),
                selected: _tlEpisodioFilter == f.key,
                onSelected: (_) {
                  setState(() => _tlEpisodioFilter = f.key);
                },
              ),
          ],
        ),
        BioSpacing.gapH(BioSpacing.sm),
        if (items.isEmpty)
          Text(
            all.isEmpty
                ? 'Sin hitos registrados en este episodio.'
                : 'Sin hitos para este filtro.',
            style: BioTypography.bodySm.copyWith(color: context.bio.textMuted),
          )
        else
          for (final it in items) ...[
            BioCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Wrap(
                    spacing: BioSpacing.xs,
                    runSpacing: BioSpacing.xs,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      BioBadge(label: it.typeLabel, intent: UiIntent.neutral),
                      if (it.occurredAt.isNotEmpty)
                        Text(
                          formatEpisodioFecha(it.occurredAt),
                          style: BioTypography.caption
                              .copyWith(color: context.bio.textMuted),
                        ),
                      if (it.actorNombre != null && it.actorNombre!.isNotEmpty)
                        Text(
                          it.actorNombre!,
                          style: BioTypography.caption
                              .copyWith(color: context.bio.textMuted),
                        ),
                    ],
                  ),
                  BioSpacing.gapH(BioSpacing.xs),
                  Text(it.summary, style: BioTypography.body),
                ],
              ),
            ),
            BioSpacing.gapH(BioSpacing.sm),
          ],
      ],
    );
  }

  Widget _buildEpisodioBanner(ContextoEpisodioHistoria? ctx) {
    final esGuardia = ctx?.tipo.isNotEmpty == true
        ? ctx!.esGuardia
        : _esCapturaGuardia;
    final tituloTipo = esGuardia ? 'Guardia' : 'Internación';
    final tituloParts = <String>[tituloTipo];
    if (ctx?.ingresoAt != null && ctx!.ingresoAt!.isNotEmpty) {
      tituloParts.add('Ingreso ${formatEpisodioFecha(ctx.ingresoAt)}');
    }

    final estado = ctx?.estadoLabel ?? ctx?.estado ?? 'En curso';
    final motivo = (ctx?.motivo != null && ctx!.motivo!.isNotEmpty)
        ? ctx.motivo!
        : 'Sin motivo registrado';
    final triageScale = (ctx?.triageScale != null && ctx!.triageScale!.isNotEmpty)
        ? ctx.triageScale!
        : null;
    final triageLevel = ctx?.triageLevel;
    final triageLabel =
        ctx?.triageLevelLabel ?? (triageLevel != null ? 'Nivel $triageLevel' : null);

    Widget metaCell(String label, String value) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label.toUpperCase(),
            style: BioTypography.caption.copyWith(
              color: context.bio.textMuted,
              fontWeight: FontWeight.w600,
            ),
          ),
          Text(value, style: BioTypography.body),
        ],
      );
    }

    Widget triageBadge() {
      final bg = ManchesterTriageColors.backgroundForLevel(triageLevel);
      final fg = ManchesterTriageColors.foregroundForLevel(triageLevel);
      return Container(
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(BioRadius.xs),
        ),
        padding: const EdgeInsets.symmetric(
          horizontal: BioSpacing.sm,
          vertical: 2,
        ),
        child: Text(
          triageLabel ?? 'Triage',
          style: BioTypography.caption.copyWith(
            color: fg,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
    }

    return BioCard.intent(
      intent: esGuardia ? UiIntent.danger : UiIntent.info,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (triageLevel != null) ...[
            Wrap(
              spacing: BioSpacing.xs,
              runSpacing: BioSpacing.xs,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                triageBadge(),
                if (triageScale != null)
                  Text(
                    triageScale,
                    style: BioTypography.caption
                        .copyWith(color: context.bio.textMuted),
                  ),
              ],
            ),
            BioSpacing.gapH(BioSpacing.sm),
          ],
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: metaCell('Episodio', tituloParts.join(' · ')),
              ),
              BioSpacing.gapW(BioSpacing.md),
              Expanded(child: metaCell('Estado', estado)),
            ],
          ),
          BioSpacing.gapH(BioSpacing.sm),
          metaCell('Motivo / ingreso', motivo),
          if (ctx != null && ctx.acciones.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Wrap(
              spacing: BioSpacing.xs,
              runSpacing: BioSpacing.xs,
              children: [
                for (final a in ctx.acciones)
                  a.id == 'editar_triage'
                      ? BioButton.outlinePrimary(
                          label: a.label,
                          size: BioButtonSize.sm,
                          onPressed: () => _onEpisodioAccion(a),
                        )
                      : BioButton.danger(
                          label: a.label,
                          size: BioButtonSize.sm,
                          onPressed: () => _onEpisodioAccion(a),
                        ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Future<void> _onEpisodioAccion(EpisodioAccionHistoria accion) async {
    if (accion.id == 'editar_triage') {
      final guardiaId = widget.parentId ??
          _historiaClinicaData?.contextoEpisodio?.episodioId ??
          0;
      if (guardiaId <= 0) return;
      final token = widget.authToken;
      if (token == null || token.isEmpty) return;
      final ctx = _historiaClinicaData?.contextoEpisodio;
      final persona = _historiaClinicaData?.persona;
      final ok = await Navigator.of(context).push<bool>(
        MaterialPageRoute(
          builder: (_) => EmergencyTriageScreen(
            guardiaId: guardiaId,
            pacienteNombre: persona?.nombreCompleto ?? 'Paciente',
            api: EmergencyGuardiaApi(authToken: token),
            isRetriage: ctx?.triageLevel != null,
            initialLevel: ctx?.triageLevel,
            initialReason: ctx?.motivo,
          ),
        ),
      );
      if (ok == true && mounted) {
        await _cargarHistoriaClinica();
      }
      return;
    }

    if (accion.id != 'egreso_estructurado' ||
        accion.apiRoute == null ||
        accion.apiRoute!.isEmpty) {
      return;
    }
    final uri = Uri.parse(resolveApiAbsoluteUrl(accion.apiRoute!));
    if (!mounted) return;
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => UiJsonScreen(
          apiAbsoluteUrl: uri.toString(),
          authToken: widget.authToken,
          appClient: 'bioenlace-personalsalud',
          title: accion.label,
          onSubmitSuccess: (_) async {
            await _cargarHistoriaClinica();
          },
        ),
      ),
    );
    await _cargarHistoriaClinica();
  }

  Widget _buildEncounterReasons(HistoriaClinicaResponse hc) {
    final mp = hc.patientEncounterReasons;
    final resumen =
        (mp.resumen ??
                mp.resumenIa ??
                hc.informacionMedica.encounterReasonSummary ??
                '')
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
      widget.parentType != null &&
      widget.parentType!.isNotEmpty &&
      widget.parentId != null;

  Future<void> _toggleDictation() async {
    if (_captureBusy) return;
    if (!_sttConfigLoaded) {
      _snack('La configuración de voz todavía se está cargando.', UiIntent.warning);
      return;
    }
    if (_dictating || _audioOnlyRecording) {
      if (_audioOnlyRecording) {
        await _stopAudioOnlyRecording();
      } else {
        final result = await _dictation.stop();
        final audioStopped = await _stopAudioOnlyRecording(transcribe: false);
        if (!mounted) return;
        setState(() {
          _dictating = false;
          _lastDictation = result;
          if (result.text.trim().isNotEmpty) {
            _chatController.text = result.text.trim();
          }
          _sttStatus = _dictationStatusMessage(result, _chatController.text);
        });
        if (!audioStopped) return;
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
        final text = _chatController.text.trim();
        final needsServer = _sttConfig.serverEnabled &&
            _pendingAudioPath != null &&
            (text.isEmpty ||
                !DeviceSttLocalQuality.isAcceptable(
                  text,
                  result,
                  minConfidence:
                      _sttConfig.minConfidenceForProfile('captura_clinica'),
                ));
        if (needsServer) {
          await _transcribirAudioPendiente();
        }
      }
      return;
    }
    if (!_sttConfig.deviceEnabled && !_sttConfig.serverEnabled) {
      _snack('Dictado por voz deshabilitado.', UiIntent.warning);
      return;
    }
    if (!_sttConfig.deviceEnabled) {
      await _startAudioOnlyRecording();
      return;
    }
    try {
      final audioStarted = await _startAudioOnlyRecording(audioOnly: false);
      if (!audioStarted) return;
      await _dictation.start(
        onPartial: (text, _) {
          if (!mounted) return;
          setState(() {
            _chatController.text = text;
            _sttStatus = 'Escuchando…';
          });
        },
        listenMode: DeviceListenMode.dictation,
        preferOnDevice: true,
      );
      if (!mounted) {
        await _audioRecorder.stop();
        return;
      }
      setState(() {
        _dictating = true;
        _sttStatus = 'Escuchando…';
      });
    } catch (e, st) {
      await _stopAudioOnlyRecording(transcribe: false);
      debugPrint('[stt-device] start failed: $e\n$st');
      _snack('No se pudo iniciar dictado: $e', UiIntent.danger);
    }
  }

  Future<bool> _startAudioOnlyRecording({bool audioOnly = true}) async {
    try {
      if (!await _audioRecorder.hasPermission()) {
        if (kIsWeb) {
          await _showMicrophonePermissionDialog(permissionRequestFailed: true);
        } else {
          _snack(
            'Para grabar el audio, habilitá el permiso de micrófono en la app.',
            UiIntent.danger,
          );
        }
        return false;
      }
      final extension = kIsWeb ? 'webm' : 'm4a';
      final filename =
          'encounter_${DateTime.now().millisecondsSinceEpoch}.$extension';
      final path = kIsWeb
          ? filename
          : '${(await getTemporaryDirectory()).path}/$filename';
      await _audioRecorder.start(
        RecordConfig(
          encoder: kIsWeb ? AudioEncoder.opus : AudioEncoder.aacLc,
          sampleRate: 44100,
          numChannels: 1,
          androidConfig: const AndroidRecordConfig(useLegacy: true),
        ),
        path: path,
      );
      if (!await _audioRecorder.isRecording()) {
        throw StateError(
          'El navegador no inició la grabación. Verificá el micrófono y el permiso de acceso.',
        );
      }
      _pendingAudioPath = path;
      if (!mounted) {
        await _audioRecorder.stop();
        return false;
      }
      setState(() {
        _audioOnlyRecording = audioOnly;
        _sttStatus = 'Grabando…';
      });
      return true;
    } catch (e) {
      if (kIsWeb) {
        await _showMicrophonePermissionDialog();
        return false;
      }
      final error = e.toString().toLowerCase();
      final message = error.contains('notallowederror') ||
              error.contains('permission denied') ||
              error.contains('denied')
          ? 'El navegador bloqueó el acceso al micrófono. Revisá los permisos y volvé a intentar.'
          : error.contains('notfounderror') ||
                  error.contains('devicesnotfounderror')
              ? 'No se encontró un micrófono disponible.'
              : kIsWeb
                  ? 'No se pudo acceder al micrófono. Verificá los permisos del navegador y que la página esté abierta mediante una conexión segura (HTTPS). Detalle: $e'
                  : 'No se pudo grabar audio: $e';
      _snack(message, UiIntent.danger);
      return false;
    }
  }

  Future<void> _showMicrophonePermissionDialog({
    bool permissionRequestFailed = false,
  }) async {
    if (!mounted) return;
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        icon: const Icon(Icons.mic_off_outlined),
        title: const Text('Micrófono no habilitado'),
        content: Text(
          permissionRequestFailed
              ? 'No se pudo habilitar el micrófono. Verificá que haya uno disponible y que el permiso esté habilitado en la configuración del navegador o del dispositivo. Después, volvé a intentar.'
              : 'Para grabar, habilitá el acceso al micrófono. Podés solicitar el permiso ahora. Si no aparece el aviso, revisá los permisos del navegador o del dispositivo.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Cerrar'),
          ),
          FilledButton.icon(
            onPressed: () async {
              Navigator.of(dialogContext).pop();
              try {
                final granted = await _audioRecorder.hasPermission();
                if (!mounted) return;
                if (granted) {
                  await _startAudioOnlyRecording();
                } else {
                  await _showMicrophonePermissionDialog(
                    permissionRequestFailed: true,
                  );
                }
              } catch (_) {
                if (!mounted) return;
                await _showMicrophonePermissionDialog(
                  permissionRequestFailed: true,
                );
              }
            },
            icon: const Icon(Icons.mic),
            label: Text(
              permissionRequestFailed
                  ? 'Volver a intentar'
                  : 'Habilitar micrófono',
            ),
          ),
        ],
      ),
    );
  }

  Future<bool> _stopAudioOnlyRecording({bool transcribe = true}) async {
    String? audioPath;
    try {
      audioPath = await _audioRecorder.stop();
    } catch (e) {
      _pendingAudioPath = null;
      if (!mounted) return false;
      setState(() {
        _audioOnlyRecording = false;
        _sttStatus = 'No se pudo capturar el audio.';
      });
      _snack('No se pudo finalizar la grabación: $e', UiIntent.danger);
      return false;
    }
    if (!mounted) return false;
    if (audioPath == null || audioPath.isEmpty) {
      _pendingAudioPath = null;
      setState(() {
        _audioOnlyRecording = false;
        _sttStatus = 'No se capturó audio.';
      });
      _snack(
        'No se capturó audio. Conectá un micrófono y volvé a intentar.',
        UiIntent.warning,
      );
      return false;
    }
    _pendingAudioPath = audioPath;
    setState(() {
      _audioOnlyRecording = false;
      _sttStatus = 'Procesando audio…';
    });
    if (transcribe && _sttConfig.serverEnabled) {
      await _transcribirAudioPendiente();
    }
    return true;
  }

  Future<String?> _audioPathToBase64(String path) async {
    final bytes = await XFile(path).readAsBytes();
    if (bytes.isEmpty) return null;
    final mimeType = kIsWeb ? 'audio/webm' : 'audio/m4a';
    return 'data:$mimeType;base64,${base64Encode(bytes)}';
  }

  Future<void> _transcribirAudioPendiente() async {
    if (!_sttConfig.serverEnabled || _pendingAudioPath == null) {
      return;
    }
    setState(() {
      _isAnalyzing = true;
      _sttStatus = 'Transcribiendo…';
    });
    try {
      final b64 = await _audioPathToBase64(_pendingAudioPath!);
      if (b64 == null) {
        if (mounted) {
          setState(() {
            _sttStatus = 'No se capturó audio.';
          });
          await _persistLocalDraft(
            texto: '(audio pendiente de transcribir)',
            status: PendingEncounterCaptureStatus.pendingStt,
            error: 'No se pudo leer el audio para transcribirlo.',
          );
          _snack(
            'No se pudo leer el audio. El borrador local se conserva.',
            UiIntent.warning,
          );
        }
        return;
      }
      final text = await _encounterApi.transcribirServidor(audioBase64: b64);
      if (text.trim().isEmpty) {
        if (mounted) {
          setState(() {
            _sttStatus = 'No se detectó voz en el audio.';
          });
          await _persistLocalDraft(
            texto: '(audio pendiente de transcribir)',
            status: PendingEncounterCaptureStatus.pendingStt,
            error: 'No se detectó voz en el audio.',
          );
          _snack(
            'No se detectó voz en el audio. El audio quedó guardado en el borrador.',
            UiIntent.warning,
          );
        }
        return;
      }
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
        _sttStatus = 'Listo';
      });
      if (_tieneContextoCaptura && text.trim().isNotEmpty) {
        await _persistLocalDraft(
          texto: text.trim(),
          status: PendingEncounterCaptureStatus.draft,
          clearError: true,
        );
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _sttStatus = 'No se pudo transcribir el audio';
        });
        await _persistLocalDraft(
          texto: _chatController.text.trim().isEmpty
              ? '(audio pendiente de transcribir)'
              : _chatController.text.trim(),
          status: PendingEncounterCaptureStatus.pendingStt,
          error: _mensajeErrorCaptura(e),
        );
      }
      _snack('Error al transcribir: $e', UiIntent.danger);
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
    final friendly = userFriendlyErrorMessage(e);
    if (isRetryableNetworkError(e)) {
      return '$friendly La nota quedó en el teléfono; reintentá cuando haya conexión.';
    }
    return friendly;
  }

  String _dictationStatusMessage(DeviceDictationResult result, String text) {
    final trimmed = text.trim();
    if (trimmed.isEmpty) {
      return 'Sin voz detectada';
    }
    if (DeviceSttLocalQuality.isAcceptable(
      trimmed,
      result,
      minConfidence: _sttConfig.minConfidenceForProfile('captura_clinica'),
    )) {
      return 'Listo';
    }
    if (_sttConfig.serverEnabled) {
      return 'Mejorando transcripción…';
    }
    return 'Revisá el texto';
  }

  void _clearIssueResolutions() {
    for (final c in _issueCustomControllers.values) {
      c.dispose();
    }
    _issueCustomControllers.clear();
    _issueResolutions = {};
    _openProblemResolutions = {};
  }

  TextEditingController _issueCustomController(String issueId) {
    return _issueCustomControllers.putIfAbsent(
      issueId,
      () => TextEditingController(),
    );
  }

  void _clearCaptureDraft({bool deleteLocalPending = true}) {
    final pendingId = _activePendingId;
    setState(() {
      _captureReview = null;
      _lastAnalysis = null;
      _draftText = null;
      _stagedItemIds = {};
      _clearIssueResolutions();
      _clearEditBackup();
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
                  parent: widget.parentType!,
                  parentId: widget.parentId!,
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
      parent: widget.parentType!,
      parentId: widget.parentId!,
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
    
    // Actualizar estado inmediatamente para evitar doble tap
    setState(() {
      _pendingCaptures.removeWhere((item) => item.id == id);
      if (_activePendingId == id) {
        _clearCaptureDraft(deleteLocalPending: false);
      }
    });

    // Eliminar del backend si tiene serverCaptureId
    if (existing?.serverCaptureId != null) {
      try {
        await _encounterApi.capturaDescartar(
          clientCaptureId: id,
          captureId: existing?.serverCaptureId,
        );
        print('✓ Captura eliminada del servidor: $id (server: ${existing?.serverCaptureId})');
      } catch (e) {
        print('✗ Error al eliminar captura del servidor: $e');
        _snack('Error al eliminar del servidor', UiIntent.danger);
        // Revertir el cambio local si falló el backend
        await _loadPendingCaptures();
        return;
      }
    }

    // Eliminar localmente
    await _pendingStore.delete(id);
    
    // Recargar desde el servidor para asegurar consistencia
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
        _clearIssueResolutions();
        _stagedItemIds = current.stagedItemIds.isNotEmpty
            ? current.stagedItemIds.toSet()
            : review.allItems.map((e) => e.id).toSet();
        _chatController.clear();
        _sttStatus = current.status == PendingEncounterCaptureStatus.failedSave
            ? (current.lastError?.trim().isNotEmpty == true
                ? userFriendlyErrorMessage(current.lastError!)
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
      _clearIssueResolutions();
      _chatController.text = current.texto.startsWith('(audio')
          ? ''
          : current.texto;
      _sttStatus = current.lastError != null && current.lastError!.isNotEmpty
          ? userFriendlyErrorMessage(current.lastError!)
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
    await _analyzeEncounter(
      fromStatus: item.status,
      forcePipeline: true,
    );
  }

  /// Acción primaria del card pendiente según checkpoint.
  String _pendingPrimaryActionLabel(PendingEncounterCapture item) {
    switch (item.status) {
      case PendingEncounterCaptureStatus.draft:
      return 'Generar Notas Clínicas';
      case PendingEncounterCaptureStatus.pendingUpload:
        return 'Subir';
      case PendingEncounterCaptureStatus.pendingStt:
        return 'Transcribir';
      case PendingEncounterCaptureStatus.pendingAnalyze:
        return 'Generar Notas Clínicas';
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

  void _clearEditBackup() {
    _editingCaptureDraft = false;
    _editBackupReview = null;
    _editBackupLastAnalysis = null;
    _editBackupDraft = '';
    _editBackupStagedItemIds = {};
    _editBackupIssueResolutions = {};
    _editBackupOpenProblemResolutions = {};
  }

  void _editCaptureDraft() {
    final draft = _draftText ?? '';
    final backupResolutions = Map<String, dynamic>.from(_issueResolutions);
    final backupOpenProblems =
        Map<String, dynamic>.from(_openProblemResolutions);
    setState(() {
      _editBackupReview = _captureReview;
      _editBackupLastAnalysis = _lastAnalysis;
      _editBackupDraft = draft;
      _editBackupStagedItemIds = Set<String>.from(_stagedItemIds);
      _editBackupIssueResolutions = backupResolutions;
      _editBackupOpenProblemResolutions = backupOpenProblems;
      _editingCaptureDraft = true;
      _captureReview = null;
      _lastAnalysis = null;
      _stagedItemIds = {};
      _clearIssueResolutions();
      _sttStatus = '';
      _chatController.text = draft;
    });
    _chatFocusNode.requestFocus();
  }

  void _cancelEditCaptureDraft() {
    final review = _editBackupReview;
    final analysis = _editBackupLastAnalysis;
    final draft = _editBackupDraft;
    final staged = Set<String>.from(_editBackupStagedItemIds);
    final issues = Map<String, dynamic>.from(_editBackupIssueResolutions);
    final openProblems =
        Map<String, dynamic>.from(_editBackupOpenProblemResolutions);
    setState(() {
      _captureReview = review;
      _lastAnalysis = analysis;
      _draftText = draft;
      _stagedItemIds = staged;
      _issueResolutions = issues;
      _openProblemResolutions = openProblems;
      _chatController.text = draft;
      _sttStatus = '';
      _clearEditBackup();
    });
  }

  void _toggleStagedItem(String id, bool selected) {
    setState(() {
      if (selected) {
        _stagedItemIds.add(id);
      } else {
        _stagedItemIds.remove(id);
        final prefix = '$id:';
        _issueResolutions.removeWhere((key, _) => key.startsWith(prefix));
        for (final entry in _issueCustomControllers.entries.toList()) {
          if (entry.key.startsWith(prefix)) {
            entry.value.clear();
          }
        }
      }
    });
  }

  Future<void> _analyzeEncounter({
    PendingEncounterCaptureStatus? fromStatus,
    bool forcePipeline = false,
  }) async {
    if (_dictating || _audioOnlyRecording) {
      await _toggleDictation();
      if (_dictating || _audioOnlyRecording) return;
    }
    final text = _chatController.text.trim();
    if (text.isEmpty && _pendingAudioPath == null) {
      _snack(
        _esCapturaEpisodio
            ? 'Escribí o dictá la evolución.'
            : 'Escriba o dicte la consulta.',
        UiIntent.warning,
      );
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
      _clearEditBackup();
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
      var needsServerStt = text.isEmpty && _sttConfig.serverEnabled;
      if (last != null && text.isNotEmpty && text == last.text.trim()) {
        sttMeta = last.toSttPayload();
        needsServerStt = _sttConfig.serverEnabled &&
            !DeviceSttLocalQuality.isAcceptable(
              text,
              last,
              minConfidence:
                  _sttConfig.minConfidenceForProfile('captura_clinica'),
            );
      } else if (text.isEmpty) {
        needsServerStt = _sttConfig.serverEnabled;
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
            parent: widget.parentType,
            parentId: widget.parentId,
            transcript: text.isEmpty ? null : text,
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

      final needsStt = _sttConfig.serverEnabled &&
          (stage == 'UPLOADED' ||
              stage == 'STT_FAILED' ||
              (needsServerStt && transcript.isEmpty) ||
              fromStatus == PendingEncounterCaptureStatus.pendingStt);

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

      if (transcript.trim().isEmpty && !_sttConfig.serverEnabled) {
        const message =
            'No se obtuvo texto del dictado. El audio quedó guardado; escribí la consulta para continuar.';
        await _persistLocalDraft(
          texto: '(audio pendiente de transcribir)',
          status: PendingEncounterCaptureStatus.pendingStt,
          serverCaptureId: serverId,
          serverStage: stage,
          audioUploaded: audioUploaded,
          error: message,
        );
        if (mounted) _snack(message, UiIntent.warning);
        return;
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
        transcript: transcript,
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
      final staged = <String>{};
      if (review.defaultStagedItemIds.isNotEmpty) {
        staged.addAll(review.defaultStagedItemIds);
      } else {
        staged.addAll(
          review.allItems
              .where((i) => i.isFromClinicalText && !i.alreadyActive)
              .map((e) => e.id),
        );
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
        _clearIssueResolutions();
        _clearEditBackup();
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

  bool _allStagedIssuesResolved(EncounterCaptureAnalysis review) {
    for (final issue in review.issues) {
      final m = RegExp(r'^(.*)::(\d+):').firstMatch(issue.id);
      final stagedId = m != null ? '${m.group(1)}::${m.group(2)}' : issue.id;
      if (!_stagedItemIds.contains(stagedId)) {
        continue;
      }
      if (_issueResolutions.containsKey(issue.id)) {
        final v = _issueResolutions[issue.id];
        if (v != null && v.toString().trim().isNotEmpty) continue;
      }
      if (issue.allowCustom) {
        final custom = _issueCustomControllers[issue.id]?.text.trim() ?? '';
        if (custom.isNotEmpty) continue;
      }
      return false;
    }
    return true;
  }

  Future<void> _confirmarGuardado() async {
    final review = _captureReview;
    if (review == null || _lastAnalysis == null) {
      _snack('Analizá antes de guardar.', UiIntent.warning);
      return;
    }
    if (review.systemError != null) {
      _snack('No se puede guardar: el análisis tiene errores de sistema.', UiIntent.warning);
      return;
    }
    if (review.episodeNoteDuplicate) {
      String? dupMsg;
      for (final a in review.advisories) {
        if (a.code == 'episode_note_duplicate' && a.message.trim().isNotEmpty) {
          dupMsg = a.message;
          break;
        }
      }
      _snack(
        dupMsg ??
            'Editá el texto: la nota es casi idéntica a una evolución previa del episodio.',
        UiIntent.warning,
      );
      return;
    }
    if (!_allStagedIssuesResolved(review)) {
      _snack(
        'Completá los datos faltantes marcados antes de confirmar.',
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
            '${_lastAnalysis!['encounter_id']}',
          );

    final resolutions = <String, dynamic>{};
    for (final issue in review.issues) {
      if (_issueResolutions.containsKey(issue.id)) {
        resolutions[issue.id] = _issueResolutions[issue.id];
        continue;
      }
      if (issue.allowCustom) {
        final custom = _issueCustomControllers[issue.id]?.text.trim() ?? '';
        if (custom.isNotEmpty) {
          resolutions[issue.id] = custom;
        }
      }
    }

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
      final conditionResolutions = <String, dynamic>{};
      final carePlanResolutions = <String, dynamic>{};
      _openProblemResolutions.forEach((key, value) {
        final parts = key.split(':');
        if (parts.length != 2) return;
        if (parts[0] == 'care_plan') {
          carePlanResolutions[parts[1]] = value;
        } else {
          conditionResolutions[parts[1]] = value;
        }
      });
      Map<String, dynamic> guardado;
      if (clientId != null && clientId.isNotEmpty) {
      final out = await _encounterApi.capturaGuardar(
          clientCaptureId: clientId,
          datosExtraidos: extraidos,
          // Draft en servidor: el pipeline usa el análisis persistido; no reenviar backup.
          stagedItemIds: _stagedItemIds.toList(),
          textoOriginal: textoOriginal,
          textoProcesado: textoProcesado,
          userPerTabConfig: await _operationalContextForCapture(),
          conditionResolutions: conditionResolutions,
          carePlanResolutions: carePlanResolutions,
          resolutions: resolutions.isEmpty ? null : resolutions,
        );
        final nested = out['guardar'];
        guardado = nested is Map
            ? Map<String, dynamic>.from(nested)
            : out;
      } else {
        guardado = await _encounterApi.guardar(
          idPersona: widget.personaId,
          parent: widget.parentType,
          parentId: widget.parentId,
          datosExtraidos: extraidos,
          // Sin draft de captura: solo token de cache (si hay), no mapa completo duplicado.
          analysisCacheToken: _lastAnalysis!['analysis_cache_token']?.toString(),
          textoOriginal: textoOriginal,
          textoProcesado: textoProcesado,
          idConfiguracion: idConfiguracion,
          encounterId: encounterId,
          userPerTabConfig: await _operationalContextForCapture(),
          conditionResolutions: conditionResolutions,
          carePlanResolutions: carePlanResolutions,
          resolutions: resolutions.isEmpty ? null : resolutions,
        );
      }
      if (!mounted) return;
      final aviso = guardado['persist_incomplete'] == true
          ? (guardado['message']?.toString() ??
              'Encounter guardado con datos incompletos.')
          : null;
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
      }
      final doneId = _activePendingId;
      _clearCaptureDraft(deleteLocalPending: false);
      if (doneId != null) {
        await _pendingStore.delete(doneId);
        await _loadPendingCaptures();
      }
      if (!mounted) return;
      // El aviso de éxito lo muestra la pantalla anterior (home / agenda).
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

  Future<void> _sendEncounter() async {
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
    await _analyzeEncounter();
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
        Text(
          'Notas pendientes en el teléfono',
          style: BioTypography.overline.copyWith(
            color: context.bio.textMuted,
          ),
        ),
        BioSpacing.gapH(BioSpacing.sm),
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

  Widget _buildPendingCaptureRow(PendingEncounterCapture item) {
    final previewRaw = item.texto.trim();
    final preview = previewRaw.isEmpty || previewRaw.startsWith('(audio')
        ? (item.hasAudio ? 'Audio' : 'Sin texto')
        : (previewRaw.length > 90
            ? '${previewRaw.substring(0, 90)}…'
            : previewRaw);
    final primary = _pendingPrimaryActionLabel(item);
    final rawError = item.lastError?.trim() ?? '';
    final friendlyError = rawError.isNotEmpty &&
            item.status != PendingEncounterCaptureStatus.pendingSave
        ? userFriendlyErrorMessage(rawError)
        : null;
    final warning = IntentPalette.of(UiIntent.warning);

    // Cola local: fondo hundido + borde suave, no BioCard.intent (evita
    // confundirse con condición / signos / motivos).
    return Container(
      padding: const EdgeInsets.all(BioSpacing.md),
      decoration: BoxDecoration(
        color: PaperPalette.paper100,
        borderRadius: BorderRadius.circular(BioRadius.sm),
        border: Border.all(color: warning.border, width: BorderWidth.thin),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                Icons.cloud_off_outlined,
                size: 18,
                color: warning.softFg,
              ),
              BioSpacing.gapW(BioSpacing.sm),
              Expanded(
                child: Text(
                  'Nota pendiente',
                  style: BioTypography.title.copyWith(color: warning.softFg),
                ),
              ),
              BioBadge.warning(_pendingStatusLabel(item.status)),
            ],
          ),
          BioSpacing.gapH(BioSpacing.xs),
          Text(
            preview,
            style: BioTypography.caption.copyWith(
              color: context.bio.textMuted,
            ),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          if (friendlyError != null) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              friendlyError,
              style: BioTypography.caption.copyWith(color: warning.softFg),
              maxLines: 3,
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
        if (review.advisories.isNotEmpty) ...[
          BioSpacing.gapH(BioSpacing.md),
          ...review.advisories.map((adv) {
            final intent = adv.severity == 'danger'
                ? UiIntent.danger
                : (adv.severity == 'info' ? UiIntent.info : UiIntent.warning);
            return Padding(
              padding: const EdgeInsets.only(bottom: BioSpacing.sm),
              child: BioAlert(
                intent: intent,
                message: adv.message,
              ),
            );
          }),
        ],
        if (review.episodeNoteDuplicate && review.advisories.isEmpty) ...[
          BioSpacing.gapH(BioSpacing.sm),
          BioAlert.danger(
            message:
                'Esta nota es casi idéntica a una evolución previa. Editá el texto para documentar los cambios.',
          ),
        ],
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
          BioSpacing.gapH(BioSpacing.sm),
          ...review.categories.map((cat) {
            final delTexto =
                cat.items.where((i) => i.isFromClinicalText).toList();
            final sugeridos =
                cat.items.where((i) => !i.isFromClinicalText).toList();

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
                    ),
                  ...delTexto.map(
                    (item) => _buildCaptureItemBlock(review, item),
                  ),
                  if (sugeridos.isNotEmpty) ...[
                    if (delTexto.isNotEmpty) BioSpacing.gapH(BioSpacing.sm),
                    ...sugeridos.map(
                      (item) => Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _buildCaptureItemBlock(review, item),
                          Text(
                            'Sugerido por IA',
                            style: BioTypography.caption.copyWith(
                              color:
                                  IntentPalette.of(UiIntent.secondary).base,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            );
          }),
        ],
        if (review.missingCategories.isNotEmpty) ...[
          BioSpacing.gapH(BioSpacing.sm),
          BioAlert.warning(
            message:
                'Faltan categorías obligatorias: ${review.missingCategories.join(', ')}.',
          ),
        ],
        ...review.orphanIssuesForStaged(_stagedItemIds).map(_buildCaptureIssueBlock),
        if (review.hasOpenProblems) ...[
          BioSpacing.gapH(BioSpacing.md),
          Text('Problemas y tratamientos abiertos', style: sectionTitleStyle),
          BioSpacing.gapH(BioSpacing.xs),
          Text(
            'Opcional: indicá el estado al cerrar. Si no elegís, se mantienen.',
            style: BioTypography.caption.copyWith(color: context.bio.textMuted),
          ),
          BioSpacing.gapH(BioSpacing.sm),
          ...review.openProblems!.conditions
              .map((item) => _buildOpenProblemBlock(item)),
          ...review.openProblems!.carePlans
              .map((item) => _buildOpenProblemBlock(item)),
        ],
      ],
    );
  }

  Widget _buildOpenProblemBlock(EncounterOpenProblemItem item) {
    final key = '${item.kind}:${item.id}';
    final selected = _openProblemResolutions[key];
    return Padding(
      padding: const EdgeInsets.only(bottom: BioSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            item.label,
            style: BioTypography.bodySm.copyWith(fontWeight: FontWeight.w600),
          ),
          if (item.detail != null && item.detail!.isNotEmpty)
            Text(
              item.detail!,
              style: BioTypography.caption.copyWith(color: context.bio.textMuted),
            ),
          if (item.statusLabel != null && item.statusLabel!.isNotEmpty)
            Text(
              item.statusLabel!,
              style: BioTypography.caption.copyWith(color: context.bio.textMuted),
            ),
          BioSpacing.gapH(BioSpacing.sm),
          Wrap(
            spacing: BioSpacing.sm,
            runSpacing: BioSpacing.sm,
            children: item.options.map((opt) {
              final isSelected =
                  selected != null && selected.toString() == opt.value.toString();
              return BioChip(
                label: opt.label,
                selected: isSelected,
                icon: isSelected ? Icons.check : null,
                intent: UiIntent.neutral,
                onTap: _isSaving
                    ? null
                    : () {
                        setState(() {
                          if (isSelected) {
                            _openProblemResolutions.remove(key);
                          } else {
                            _openProblemResolutions[key] = opt.value;
                          }
                        });
                      },
              );
            }).toList(),
          ),
        ],
      ),
    );
  }

  Widget _buildCaptureIssueBlock(EncounterCaptureIssue issue) {
    final selected = _issueResolutions[issue.id];
    return Padding(
      padding: const EdgeInsets.only(bottom: BioSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (issue.field.isNotEmpty)
            Text(
              issue.field,
              style: BioTypography.caption.copyWith(color: context.bio.textMuted),
            ),
          BioSpacing.gapH(BioSpacing.sm),
          if (issue.options.isNotEmpty)
            Wrap(
              spacing: BioSpacing.sm,
              runSpacing: BioSpacing.sm,
              children: issue.options.map((opt) {
                final isSelected = selected != null &&
                    selected.toString() == opt.value.toString();
                return BioChip(
                  label: opt.label,
                  selected: isSelected,
                  icon: isSelected ? Icons.check : null,
                  intent: UiIntent.neutral,
                  onTap: _isSaving
                      ? null
                      : () {
                          setState(() {
                            if (isSelected) {
                              _issueResolutions.remove(issue.id);
                            } else {
                              _issueResolutions[issue.id] = opt.value;
                              _issueCustomControllers[issue.id]?.clear();
                            }
                          });
                        },
                );
              }).toList(),
            ),
          if (issue.allowCustom) ...[
            BioSpacing.gapH(BioSpacing.sm),
            TextField(
              controller: _issueCustomController(issue.id),
              enabled: !_isSaving,
              decoration: InputDecoration(
                hintText: issue.options.isEmpty
                    ? 'Completá el valor'
                    : 'Otra opción…',
                isDense: true,
                border: const OutlineInputBorder(),
              ),
              onChanged: (value) {
                final trimmed = value.trim();
                setState(() {
                  if (trimmed.isEmpty) {
                    // Solo limpia si el valor actual era custom (no chip).
                    final current = _issueResolutions[issue.id];
                    final matchesChip = issue.options.any(
                      (o) => o.value.toString() == current?.toString(),
                    );
                    if (!matchesChip) {
                      _issueResolutions.remove(issue.id);
                    }
                  } else {
                    _issueResolutions[issue.id] = trimmed;
                  }
                });
              },
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildCaptureItemBlock(
    EncounterCaptureAnalysis review,
    EncounterCaptureItem item,
  ) {
    final incomplete = review.incompleteForItem(item.id);
    final itemIssues = review.issuesForItem(item.id);
    final selected = _stagedItemIds.contains(item.id);
    return Padding(
      padding: const EdgeInsets.only(bottom: BioSpacing.sm),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildCaptureItemChip(item, incomplete: incomplete != null),
          if (selected && incomplete != null && itemIssues.isEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              incomplete.message,
              style: BioTypography.bodySm.copyWith(
                color: IntentPalette.of(UiIntent.danger).base,
              ),
            ),
          ],
          if (selected && itemIssues.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.sm),
            Text(
              'Completar datos',
              style: BioTypography.bodySm.copyWith(
                fontWeight: FontWeight.w600,
                color: IntentPalette.of(UiIntent.danger).base,
              ),
            ),
            BioSpacing.gapH(BioSpacing.xs),
            ...itemIssues.map(_buildCaptureIssueBlock),
          ],
        ],
      ),
    );
  }

  Widget _buildCaptureItemChip(
    EncounterCaptureItem item, {
    bool incomplete = false,
  }) {
    final selected = _stagedItemIds.contains(item.id);
    final label = item.subtitle != null && item.subtitle!.isNotEmpty
        ? '${item.label} (${item.subtitle})'
        : item.label;

    return BioChip(
      label: label,
      selected: selected,
      filled: incomplete && selected,
      icon: selected
          ? Icons.check_circle
          : (incomplete ? Icons.add_circle_outline : Icons.add_circle_outline),
      intent: incomplete
          ? UiIntent.danger
          : (item.alreadyActive && !selected
              ? UiIntent.neutral
              : (item.isFromClinicalText
                  ? UiIntent.neutral
                  : UiIntent.secondary)),
      onTap: _isSaving ? null : () => _toggleStagedItem(item.id, !selected),
    );
  }

  Widget _buildCaptureActionsBar() {
    final review = _captureReview;
    final canConfirm = !_isSaving &&
        review != null &&
        review.systemError == null &&
        !review.episodeNoteDuplicate &&
        review.textoOriginal.trim().isNotEmpty &&
        _allStagedIssuesResolved(review);

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
                  onPressed: _isSaving || _activePendingId == null
                      ? null
                      : () => _deletePendingCapture(_activePendingId!),
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
        if (_editingCaptureDraft)
          Padding(
            padding: const EdgeInsets.only(
              left: BioSpacing.sm,
              right: BioSpacing.sm,
              bottom: BioSpacing.xs,
            ),
            child: Align(
              alignment: Alignment.centerLeft,
              child: TextButton.icon(
                onPressed: _captureBusy ? null : _cancelEditCaptureDraft,
                icon: const Icon(Icons.arrow_back, size: 18),
                label: const Text('Cancelar edición'),
              ),
            ),
          ),
        if (_sttStatus.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(
              left: BioSpacing.sm,
              right: BioSpacing.sm,
              bottom: BioSpacing.xs,
            ),
            child: Text(
              _sttStatus,
              style: BioTypography.caption.copyWith(color: cs.primary),
            ),
          ),
        AssistantChatComposerBar(
          controller: _chatController,
          focusNode: _chatFocusNode,
          onSend: _sendEncounter,
          isSending: _captureBusy,
          sendIcon: Icons.fact_check_outlined,
          hintText: canCapture
              ? (_esCapturaEpisodio
                  ? 'Dictar o escribir la evolución…'
                  : 'Dictar o escribir la consulta…')
              : (_esCapturaEpisodio
                  ? 'Escribir evolución…'
                  : 'Escribir consulta…'),
          maxLines: 6,
          onVoice: canCapture &&
                  _sttConfigLoaded &&
                  (_sttConfig.deviceEnabled || _sttConfig.serverEnabled)
              ? _toggleDictation
              : null,
          voiceActive: _dictating || _audioOnlyRecording,
        ),
      ],
    );
  }
}
